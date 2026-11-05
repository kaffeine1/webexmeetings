<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace mod_webexmeetings\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Site-wide Webex OAuth connection and token renewal.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth {
    /** @var string Component owning the credentials. */
    public const COMPONENT = 'mod_webexmeetings';
    /** @var int Maximum lifetime of a pending authorisation. */
    public const STATE_TTL = 600;
    /** @var string Fixed Webex authorisation endpoint. */
    public const AUTHORIZE_URL = 'https://webexapis.com/v1/authorize';
    /** @var string Fixed Webex token endpoint. */
    public const TOKEN_URL = 'https://webexapis.com/v1/access_token';

    /** @return \moodle_url The canonical callback, independent of request parameters. */
    public static function redirect_uri(): \moodle_url {
        return new \moodle_url('/mod/webexmeetings/oauth_callback.php');
    }

    /** @return \moodle_url Local connection management page. */
    public static function management_url(): \moodle_url {
        return new \moodle_url('/mod/webexmeetings/oauth.php');
    }

    /** @return \moodle_url Administrator page for choosing the user who may authorise Webex. */
    public static function delegate_url(): \moodle_url {
        return new \moodle_url('/mod/webexmeetings/oauth_delegate.php');
    }

    /** @return bool Whether the current Moodle user may start or complete Webex authorisation. */
    public static function can_connect(): bool {
        global $USER;
        if (has_capability('moodle/site:config', \context_system::instance())) {
            return true;
        }
        $delegateid = (int)(self::configuration()->oauth_delegate_userid ?? 0);
        return $delegateid > 0 && (int)$USER->id === $delegateid &&
            self::eligible_delegate($delegateid);
    }

    /** @param int $userid Moodle user ID. @return bool Whether this account can be delegated. */
    public static function eligible_delegate(int $userid): bool {
        global $DB;
        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }
        $user = $DB->get_record('user', ['id' => $userid], 'id,deleted,suspended,confirmed');
        return $user && !$user->deleted && !$user->suspended && $user->confirmed;
    }

    /** @return void */
    private static function require_connect_permission(): void {
        if (!self::can_connect()) {
            require_capability('moodle/site:config', \context_system::instance());
        }
    }

    /** @param int $userid Authorised Moodle user, or zero to revoke delegation. */
    public function set_delegate_userid(int $userid): void {
        global $CFG;
        require_capability('moodle/site:config', \context_system::instance());
        if ($userid < 0 || ($userid !== 0 && !self::eligible_delegate($userid))) {
            throw new \moodle_exception('oauthinvaliddelegate', self::COMPONENT);
        }
        if (array_key_exists('oauth_delegate_userid', $CFG->forced_plugin_settings[self::COMPONENT] ?? [])) {
            throw new \moodle_exception('oauthforcedsettings', self::COMPONENT);
        }
        $lock = $this->lock();
        try {
            set_config('oauth_delegate_userid', $userid, self::COMPONENT);
        } finally {
            $lock->release();
        }
    }

    /** @param string $profile Permission profile. @return string Space-separated Webex scopes. */
    public static function scopes(string $profile = 'host'): string {
        if ($profile === 'organization') {
            return 'meeting:admin_schedule_read meeting:admin_schedule_write meeting:admin_participants_read spark:people_read';
        }
        if ($profile !== 'host') {
            throw new \moodle_exception('oauthinvalidprofile', self::COMPONENT);
        }
        return 'meeting:schedules_read meeting:schedules_write meeting:participants_read spark:people_read';
    }

    /** @return \stdClass Current settings, including config.php overrides. */
    public static function configuration(): \stdClass {
        global $CFG, $DB;
        // Read through to the DB: another node may have rotated the token while this request waited for the lock.
        $config = $DB->get_records_menu('config_plugins', ['plugin' => self::COMPONENT], '', 'name,value');
        foreach ($CFG->forced_plugin_settings[self::COMPONENT] ?? [] as $key => $value) {
            if (is_scalar($value)) {
                $config[$key] = (string)$value;
            } else {
                unset($config[$key]);
            }
        }
        return (object)$config;
    }

    /** @param \stdClass $config Settings. @return string Digest binding credentials and the selected permissions. */
    private static function fingerprint(\stdClass $config): string {
        return hash('sha256', json_encode([
            trim($config->client_id ?? ''), trim($config->client_secret ?? ''),
            $config->oauth_scope_profile ?? 'host', self::redirect_uri()->out(false),
        ]));
    }

    /** @param \stdClass $config Settings. @return string Digest also binding the active refresh token. */
    private static function token_fingerprint(\stdClass $config): string {
        return hash('sha256', self::fingerprint($config) . ':' . trim($config->refresh_token ?? ''));
    }

    /** @param \stdClass $config Settings. @return string Authorisation binding, unaffected by routine token rotation. */
    private static function authorization_fingerprint(\stdClass $config): string {
        return hash('sha256', self::fingerprint($config) . ':' . ($config->oauth_connection_id ?? '') .
            ':' . ($config->auth_method ?? '') . ':' . ($config->oauth_delegate_userid ?? '0'));
    }

    /** @return string Local configuration status, without making any Webex request. */
    public static function status(): string {
        $config = self::configuration();
        if (empty($config->refresh_token)) {
            return 'oauthnotconnected';
        }
        if (!empty($config->oauth_authorized_fingerprint) &&
                !hash_equals($config->oauth_authorized_fingerprint, self::fingerprint($config))) {
            return 'oauthconfigchanged';
        }
        return ($config->auth_method ?? '') === 'oauth' ? 'oauthconnectionstored' : 'oauthconnectioninactive';
    }

    /** @param \stdClass $config Settings. @return void */
    private static function require_credentials(\stdClass $config): void {
        if (trim($config->client_id ?? '') === '' || trim($config->client_secret ?? '') === '') {
            throw new \moodle_exception('oauthmissingclient', self::COMPONENT);
        }
    }

    /** @return void Reject settings that cannot be updated by a successful authorisation. */
    private static function require_writable_connection(): void {
        global $CFG;
        $forced = $CFG->forced_plugin_settings[self::COMPONENT] ?? [];
        if (array_key_exists('refresh_token', $forced) ||
                (isset($forced['auth_method']) && $forced['auth_method'] !== 'oauth')) {
            throw new \moodle_exception('oauthforcedsettings', self::COMPONENT);
        }
    }

    /** @return \moodle_url Start a permitted user's session-bound PKCE authorisation. */
    public function begin(): \moodle_url {
        global $SESSION, $USER;
        self::require_connect_permission();
        self::require_writable_connection();
        $config = self::configuration();
        self::require_credentials($config);
        $redirecturi = self::redirect_uri()->out(false);
        if (parse_url($redirecturi, PHP_URL_SCHEME) !== 'https') {
            throw new \moodle_exception('oauthhttpsrequired', self::COMPONENT);
        }
        $scopes = self::scopes($config->oauth_scope_profile ?? 'host');
        $state = bin2hex(random_bytes(32));
        $verifier = bin2hex(random_bytes(32));
        $SESSION->mod_webexmeetings_oauth = [
            'statehash' => hash('sha256', $state),
            'expires' => time() + self::STATE_TTL,
            'userid' => $USER->id,
            'verifier' => $verifier,
            'fingerprint' => self::authorization_fingerprint($config),
            'redirecturi' => $redirecturi,
            'scopes' => $scopes,
        ];
        return new \moodle_url(self::AUTHORIZE_URL, [
            'response_type' => 'code', 'client_id' => trim($config->client_id),
            'redirect_uri' => $redirecturi, 'scope' => $scopes, 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256', 'prompt' => 'select_account',
        ]);
    }

    /**
     * Validate and consume a callback before exchanging the one-time code.
     *
     * @param string $code Authorisation code, never logged or displayed.
     * @param string $state Opaque state returned by Webex.
     * @param string $error Provider error identifier, never displayed verbatim.
     * @return void
     */
    public function complete(
        #[\SensitiveParameter]
        string $code,
        #[\SensitiveParameter]
        string $state,
        string $error = ''
    ): void {
        global $SESSION, $USER;
        self::require_connect_permission();
        $pending = $SESSION->mod_webexmeetings_oauth ?? null;
        if (!is_array($pending) || empty($pending['statehash']) || strlen($state) !== 64 ||
                !hash_equals($pending['statehash'], hash('sha256', $state)) || $pending['userid'] != $USER->id) {
            throw new \moodle_exception('oauthinvalidstate', self::COMPONENT);
        }
        unset($SESSION->mod_webexmeetings_oauth);
        if ($pending['expires'] <= time()) {
            throw new \moodle_exception('oauthexpiredstate', self::COMPONENT);
        }
        if ($error !== '') {
            throw new \moodle_exception('oauthdenied', self::COMPONENT);
        }
        if ($code === '' || strlen($code) > 4096) {
            throw new \moodle_exception('oauthinvalidcode', self::COMPONENT);
        }
        self::require_writable_connection();
        $lock = $this->lock();
        try {
            $config = self::configuration();
            if (!hash_equals($pending['fingerprint'], self::authorization_fingerprint($config))) {
                throw new \moodle_exception('oauthconfigchanged', self::COMPONENT);
            }
            $tokens = $this->request_tokens([
                'grant_type' => 'authorization_code', 'code' => $code,
                'redirect_uri' => $pending['redirecturi'], 'code_verifier' => $pending['verifier'],
                'client_id' => trim($config->client_id), 'client_secret' => trim($config->client_secret),
            ], true);
            if (isset($tokens->scope)) {
                $granted = preg_split('/\s+/', trim($tokens->scope));
                if (array_diff(explode(' ', $pending['scopes']), $granted)) {
                    throw new \moodle_exception('oauthmissingscopes', self::COMPONENT);
                }
            }
            if (!hash_equals($pending['fingerprint'], self::authorization_fingerprint(self::configuration()))) {
                throw new \moodle_exception('oauthconfigchanged', self::COMPONENT);
            }
            $this->store_tokens($tokens, $config, true);
        } finally {
            $lock->release();
        }
    }

    /** @return string An access token shared by web requests and cron workers. */
    public function access_token(): string {
        $lock = $this->lock();
        try {
            $config = self::configuration();
            if (($config->auth_method ?? '') !== 'oauth') {
                $token = trim($config->personal_token ?? '');
                if ($token === '') {
                    throw new \moodle_exception('missingcredentials', self::COMPONENT);
                }
                return $token;
            }
            self::require_credentials($config);
            if (trim($config->refresh_token ?? '') === '') {
                throw new \moodle_exception('oauthnotconnected', self::COMPONENT);
            }
            if (!empty($config->oauth_authorized_fingerprint) &&
                    !hash_equals($config->oauth_authorized_fingerprint, self::fingerprint($config))) {
                throw new \moodle_exception('oauthconfigchanged', self::COMPONENT);
            }
            $fingerprint = self::token_fingerprint($config);
            if (!empty($config->oauth_access_token) && (int)($config->oauth_access_expires ?? 0) > time() + 60 &&
                    hash_equals($fingerprint, $config->oauth_access_fingerprint ?? '')) {
                return $config->oauth_access_token;
            }
            $tokens = $this->request_tokens([
                'grant_type' => 'refresh_token', 'client_id' => trim($config->client_id),
                'client_secret' => trim($config->client_secret), 'refresh_token' => trim($config->refresh_token),
            ]);
            if (!hash_equals($fingerprint, self::token_fingerprint(self::configuration()))) {
                throw new \moodle_exception('oauthconfigchanged', self::COMPONENT);
            }
            $this->store_tokens($tokens, $config);
            return $tokens->access_token;
        } finally {
            $lock->release();
        }
    }

    /** @return void Remove the local connection; do not claim to revoke the Cisco grant. */
    public function disconnect(): void {
        global $SESSION, $DB;
        require_capability('moodle/site:config', \context_system::instance());
        self::require_writable_connection();
        $lock = $this->lock();
        try {
            $transaction = $DB->start_delegated_transaction();
            foreach (['refresh_token', 'oauth_access_token', 'oauth_access_expires', 'oauth_access_fingerprint',
                    'oauth_authorized_fingerprint', 'oauth_connected_at'] as $name) {
                unset_config($name, self::COMPONENT);
            }
            // Do not silently fall back to a previously configured personal token.
            set_config('auth_method', 'oauth', self::COMPONENT);
            set_config('oauth_connection_id', bin2hex(random_bytes(16)), self::COMPONENT);
            $transaction->allow_commit();
            unset($SESSION->mod_webexmeetings_oauth);
        } finally {
            $lock->release();
        }
    }

    /** @return \core\lock\lock Distributed lock for token rotation and connection changes. */
    protected function lock(): \core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory('mod_webexmeetings_oauth')->get_lock('connection', 20);
        if (!$lock) {
            throw new \moodle_exception('oauthbusy', self::COMPONENT);
        }
        return $lock;
    }

    /**
     * Fixed-destination HTTPS transport. Protected for HTTP test doubles.
     *
     * @param array $params Form parameters, including secrets.
     * @return array HTTP status and body, never logged.
     */
    protected function transport(
        #[\SensitiveParameter]
        array $params
    ): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl(['debug' => false]);
        $curl->setHeader(['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']);
        $body = $curl->post(self::TOKEN_URL, http_build_query($params, '', '&', PHP_QUERY_RFC3986), [
            'CURLOPT_FOLLOWLOCATION' => false, 'CURLOPT_MAXREDIRS' => 0,
            'CURLOPT_CONNECTTIMEOUT' => 10, 'CURLOPT_TIMEOUT' => 20,
            'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2,
        ]);
        return [(int)($curl->get_info()['http_code'] ?? 0), $body];
    }

    /** @param array $params OAuth request. @param bool $initial Is this the initial exchange? @return \stdClass */
    private function request_tokens(
        #[\SensitiveParameter]
        array $params,
        bool $initial = false
    ): \stdClass {
        try {
            [$status, $body] = $this->transport($params);
        } catch (\Throwable $e) {
            // Transport exceptions can contain request bodies: never pass them to the UI or task logs.
            throw new \moodle_exception('oauthrequestfailed', self::COMPONENT);
        }
        $tokens = is_string($body) ? json_decode($body) : null;
        if ($status < 200 || $status >= 300 || !is_object($tokens)) {
            throw new \moodle_exception('oauthrequestfailed', self::COMPONENT);
        }
        if (!is_string($tokens->access_token ?? null) || trim($tokens->access_token) === '' ||
                strlen($tokens->access_token) > 16384 || preg_match('/[\x00-\x20\x7f]/', trim($tokens->access_token)) ||
                !is_numeric($tokens->expires_in ?? null) ||
                (int)$tokens->expires_in <= 0 || (int)$tokens->expires_in > 31536000 ||
                (isset($tokens->token_type) && (!is_string($tokens->token_type) ||
                    strtolower($tokens->token_type) !== 'bearer')) ||
                (isset($tokens->scope) && !is_string($tokens->scope))) {
            throw new \moodle_exception('oauthinvalidresponse', self::COMPONENT);
        }
        if (($initial || isset($tokens->refresh_token)) &&
                (!is_string($tokens->refresh_token ?? null) || trim($tokens->refresh_token) === '' ||
                    strlen($tokens->refresh_token) > 16384 ||
                    preg_match('/[\x00-\x20\x7f]/', trim($tokens->refresh_token)))) {
            throw new \moodle_exception('oauthinvalidresponse', self::COMPONENT);
        }
        $tokens->access_token = trim($tokens->access_token);
        if (isset($tokens->refresh_token)) {
            $tokens->refresh_token = trim($tokens->refresh_token);
        }
        return $tokens;
    }

    /** @param \stdClass $tokens Validated response. @param \stdClass $config Settings. @param bool $initial New grant. */
    private function store_tokens(
        #[\SensitiveParameter]
        \stdClass $tokens,
        #[\SensitiveParameter]
        \stdClass $config,
        bool $initial = false
    ): void {
        global $DB, $CFG;
        $newrefresh = $tokens->refresh_token ?? $config->refresh_token;
        $forced = $CFG->forced_plugin_settings[self::COMPONENT] ?? [];
        if (isset($forced['refresh_token']) && $newrefresh !== $forced['refresh_token']) {
            throw new \moodle_exception('oauthforcedsettings', self::COMPONENT);
        }
        $config = clone $config;
        $config->refresh_token = $newrefresh;
        $transaction = $DB->start_delegated_transaction();
        try {
            set_config('refresh_token', $newrefresh, self::COMPONENT);
            set_config('oauth_access_token', $tokens->access_token, self::COMPONENT);
            set_config('oauth_access_expires', time() + (int)$tokens->expires_in, self::COMPONENT);
            set_config('oauth_access_fingerprint', self::token_fingerprint($config), self::COMPONENT);
            if ($initial) {
                set_config('auth_method', 'oauth', self::COMPONENT);
                set_config('oauth_authorized_fingerprint', self::fingerprint($config), self::COMPONENT);
                set_config('oauth_connected_at', time(), self::COMPONENT);
                set_config('oauth_connection_id', bin2hex(random_bytes(16)), self::COMPONENT);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback(new \moodle_exception('oauthsavefailed', self::COMPONENT));
        }
    }
}
