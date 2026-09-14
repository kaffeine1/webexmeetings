<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use mod_webexmeetings\local\oauth;
use mod_webexmeetings\local\oauth_stub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/fixtures/oauth_stub.php');

/**
 * OAuth security, persistence and existing-credential compatibility.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(oauth::class)]
final class oauth_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG, $SESSION;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->wwwroot = 'https://webex-qa.example';
        unset($SESSION->mod_webexmeetings_oauth);
        set_config('client_id', 'test-client-id', oauth::COMPONENT);
        set_config('client_secret', 'test-client-secret', oauth::COMPONENT);
        set_config('auth_method', 'oauth', oauth::COMPONENT);
    }

    /** @return array Valid fake token response. */
    private static function token_data(): array {
        return ['access_token' => 'fake-access-token', 'refresh_token' => 'fake-refresh-token',
            'expires_in' => 3600, 'token_type' => 'Bearer', 'scope' => oauth::scopes()];
    }

    /** @return oauth_stub HTTP client with one valid response. */
    private function client(): oauth_stub {
        $client = new oauth_stub();
        $client->responses[] = [200, json_encode(self::token_data())];
        return $client;
    }

    public function test_authorization_url_and_session_binding(): void {
        global $SESSION, $USER;
        $client = $this->client();
        $url = $client->begin();
        $params = $url->params();
        $pending = $SESSION->mod_webexmeetings_oauth;
        $this->assertSame(oauth::AUTHORIZE_URL, $url->out_omit_querystring());
        $this->assertSame('https://webex-qa.example/mod/webexmeetings/oauth_callback.php', $params['redirect_uri']);
        $this->assertSame('code', $params['response_type']);
        $this->assertSame('S256', $params['code_challenge_method']);
        $this->assertSame(oauth::scopes(), $params['scope']);
        $this->assertSame(hash('sha256', $params['state']), $pending['statehash']);
        $this->assertSame($USER->id, $pending['userid']);
        $this->assertEqualsWithDelta(time() + oauth::STATE_TTL, $pending['expires'], 2);
        $this->assertSame(64, strlen($pending['verifier']));
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '='),
            $params['code_challenge']);
        $this->assertStringNotContainsString('test-client-secret', $url->out(false));
        $this->assertArrayNotHasKey('client_secret', $pending);
        $this->assertNotSame($params['state'], $client->begin()->param('state'));
        $this->assertCount(0, $client->requests);
    }

    public function test_successful_connection_and_cached_token(): void {
        global $SESSION;
        set_config('auth_method', 'bot', oauth::COMPONENT);
        $client = $this->client();
        $url = $client->begin();
        $verifier = $SESSION->mod_webexmeetings_oauth['verifier'];
        $client->complete('fake-code', $url->param('state'));
        $config = oauth::configuration();
        $this->assertSame('oauth', $config->auth_method);
        $this->assertSame('fake-refresh-token', $config->refresh_token);
        $this->assertSame('fake-access-token', $config->oauth_access_token);
        $this->assertEqualsWithDelta(time() + 3600, $config->oauth_access_expires, 2);
        $this->assertNotEmpty($config->oauth_connection_id);
        $this->assertSame('oauthconnectionstored', oauth::status());
        $this->assertFalse(isset($SESSION->mod_webexmeetings_oauth));
        $this->assertSame('authorization_code', $client->requests[0]['grant_type']);
        $this->assertSame($verifier, $client->requests[0]['code_verifier']);
        $this->assertSame(oauth::redirect_uri()->out(false), $client->requests[0]['redirect_uri']);
        $this->assertSame('fake-access-token', $client->access_token());
        $this->assertSame('fake-access-token', (new oauth_stub())->access_token());
        $this->assertCount(1, $client->requests);
    }

    public function test_replayed_callback_is_rejected(): void {
        $client = $this->client();
        $url = $client->begin();
        $client->complete('fake-code', $url->param('state'));
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('oauthinvalidstate', oauth::COMPONENT));
        $client->complete('fake-code', $url->param('state'));
    }

    public function test_invalid_state_does_not_consume_the_valid_request(): void {
        global $SESSION;
        $client = $this->client();
        $url = $client->begin();
        foreach (['', 'wrong-state', str_repeat('0', 64)] as $state) {
            try {
                $client->complete('fake-code', $state);
                $this->fail('Invalid state accepted');
            } catch (\moodle_exception $e) {
                $this->assertSame('oauthinvalidstate', $e->errorcode);
            }
            $this->assertNotEmpty($SESSION->mod_webexmeetings_oauth);
            $this->assertCount(0, $client->requests);
        }
        $client->complete('fake-code', $url->param('state'));
        $this->assertSame('fake-refresh-token', get_config(oauth::COMPONENT, 'refresh_token'));
    }

    public function test_expired_state_is_consumed_without_request(): void {
        global $SESSION;
        $client = $this->client();
        $url = $client->begin();
        $SESSION->mod_webexmeetings_oauth['expires'] = time() - 1;
        try {
            $client->complete('fake-code', $url->param('state'));
            $this->fail('Expired state accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('oauthexpiredstate', $e->errorcode);
        }
        $this->assertFalse(isset($SESSION->mod_webexmeetings_oauth));
        $this->assertCount(0, $client->requests);
    }

    public function test_other_administrator_cannot_reuse_state(): void {
        global $USER;
        $client = $this->client();
        $url = $client->begin();
        $other = $this->getDataGenerator()->create_user();
        set_config('siteadmins', $USER->id . ',' . $other->id);
        $this->setUser($other);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('oauthinvalidstate', oauth::COMPONENT));
        $client->complete('fake-code', $url->param('state'));
    }

    public function test_missing_session_is_rejected(): void {
        global $SESSION;
        $client = $this->client();
        $url = $client->begin();
        unset($SESSION->mod_webexmeetings_oauth);
        $this->expectException(\moodle_exception::class);
        $client->complete('fake-code', $url->param('state'));
    }

    /** @return array */
    public static function callback_error_provider(): array {
        return [
            'denied' => ['', 'access_denied', 'oauthdenied'],
            'untrusted provider error' => ['', '<script>test-client-secret</script>', 'oauthdenied'],
            'missing code' => ['', '', 'oauthinvalidcode'],
            'oversized code' => [str_repeat('x', 4097), '', 'oauthinvalidcode'],
        ];
    }

    #[DataProvider('callback_error_provider')]
    public function test_callback_error_preserves_existing_tokens(string $code, string $error, string $expected): void {
        set_config('refresh_token', 'old-refresh-token', oauth::COMPONENT);
        $client = $this->client();
        $url = $client->begin();
        try {
            $client->complete($code, $url->param('state'), $error);
            $this->fail('Invalid callback accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame($expected, $e->errorcode);
            $this->assertStringNotContainsString('test-client-secret', $e->getMessage());
        }
        $this->assertSame('old-refresh-token', get_config(oauth::COMPONENT, 'refresh_token'));
        $this->assertCount(0, $client->requests);
    }

    /** @return array */
    public static function invalid_response_provider(): array {
        $valid = self::token_data();
        $cases = [
            'http error' => [503, json_encode($valid)],
            'redirect' => [302, json_encode($valid)],
            'invalid json' => [200, '<html>test-client-secret</html>'],
            'transport failure' => [0, false],
            'json array' => [200, '[]'],
        ];
        foreach ([
            'access_token' => [null, '', [], "abc\r\nInjected: value"],
            'refresh_token' => [null, '', [], "abc\r\nInjected: value"],
            'expires_in' => [null, 0, -1, [], 'invalid', 31536001],
            'token_type' => ['mac', []], 'scope' => [[], 'spark:people_read'],
        ] as $field => $values) {
            foreach ($values as $i => $value) {
                $response = $valid;
                $response[$field] = $value;
                $cases[$field . '-' . $i] = [200, json_encode($response)];
            }
        }
        return $cases;
    }

    #[DataProvider('invalid_response_provider')]
    public function test_invalid_responses_are_not_saved(int $status, $body): void {
        set_config('refresh_token', 'old-refresh-token', oauth::COMPONENT);
        $before = oauth::configuration();
        $client = new oauth_stub();
        $client->responses[] = [$status, $body];
        $url = $client->begin();
        try {
            $client->complete('fake-code', $url->param('state'));
            $this->fail('Invalid response accepted');
        } catch (\moodle_exception $e) {
            $this->assertContains($e->errorcode, ['oauthrequestfailed', 'oauthinvalidresponse', 'oauthmissingscopes']);
            $this->assertStringNotContainsString('test-client-secret', $e->getMessage());
            $this->assertEmpty($e->debuginfo);
        }
        $this->assertEquals($before, oauth::configuration());
    }

    public function test_transport_exception_does_not_leak_secrets(): void {
        $client = new oauth_stub();
        $client->responses[] = new \RuntimeException('test-client-secret fake-code fake-refresh-token');
        $url = $client->begin();
        try {
            $client->complete('fake-code', $url->param('state'));
            $this->fail('Transport error accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('oauthrequestfailed', $e->errorcode);
            $this->assertNull($e->getPrevious());
            $this->assertEmpty($e->debuginfo);
            $this->assertStringNotContainsString('test-client-secret', (string)$e);
        }
    }

    /** @return array */
    public static function changed_config_provider(): array {
        return [['client_id', 'other-id'], ['client_secret', 'other-secret'],
            ['oauth_scope_profile', 'organization'], ['auth_method', 'bot']];
    }

    #[DataProvider('changed_config_provider')]
    public function test_configuration_change_invalidates_pending_authorization(string $name, string $value): void {
        $client = $this->client();
        $url = $client->begin();
        set_config($name, $value, oauth::COMPONENT);
        try {
            $client->complete('fake-code', $url->param('state'));
            $this->fail('Configuration change ignored');
        } catch (\moodle_exception $e) {
            $this->assertSame('oauthconfigchanged', $e->errorcode);
        }
        $this->assertCount(0, $client->requests);
    }

    public function test_change_during_request_is_not_overwritten(): void {
        $client = $this->client();
        $url = $client->begin();
        $client->onrequest = fn() => set_config('client_id', 'new-client-id', oauth::COMPONENT);
        try {
            $client->complete('fake-code', $url->param('state'));
            $this->fail('Concurrent configuration change ignored');
        } catch (\moodle_exception $e) {
            $this->assertSame('oauthconfigchanged', $e->errorcode);
        }
        $this->assertSame('new-client-id', get_config(oauth::COMPONENT, 'client_id'));
        $this->assertFalse(get_config(oauth::COMPONENT, 'refresh_token'));
    }

    public function test_legacy_refresh_rotation_and_cache(): void {
        set_config('refresh_token', 'legacy-refresh-token', oauth::COMPONENT);
        $client = $this->client();
        $this->assertSame('fake-access-token', $client->access_token());
        $this->assertSame('legacy-refresh-token', $client->requests[0]['refresh_token']);
        $this->assertSame('refresh_token', $client->requests[0]['grant_type']);
        $this->assertSame('fake-refresh-token', get_config(oauth::COMPONENT, 'refresh_token'));
        $this->assertSame('fake-access-token', $client->access_token());
        $this->assertCount(1, $client->requests);
        set_config('oauth_access_expires', time() - 1, oauth::COMPONENT);
        $response = self::token_data();
        unset($response['refresh_token']);
        $response['access_token'] = 'renewed-access-token';
        $client->responses[] = [200, json_encode($response)];
        $this->assertSame('renewed-access-token', $client->access_token());
        $this->assertSame('fake-refresh-token', $client->requests[1]['refresh_token']);
        $this->assertSame('fake-refresh-token', get_config(oauth::COMPONENT, 'refresh_token'));
    }

    public function test_db_read_observes_rotation_despite_config_cache(): void {
        global $DB;
        set_config('refresh_token', 'old-refresh-token', oauth::COMPONENT);
        $this->assertSame('old-refresh-token', get_config(oauth::COMPONENT, 'refresh_token'));
        $DB->set_field('config_plugins', 'value', 'rotated-by-another-node',
            ['plugin' => oauth::COMPONENT, 'name' => 'refresh_token']);
        $client = $this->client();
        $client->access_token();
        $this->assertSame('rotated-by-another-node', $client->requests[0]['refresh_token']);
    }

    public function test_refresh_during_authorization_does_not_cancel_the_callback(): void {
        set_config('refresh_token', 'old-refresh-token', oauth::COMPONENT);
        $client = $this->client();
        $url = $client->begin();
        $client->access_token();
        $client->responses[] = [200, json_encode(self::token_data())];
        $client->complete('fake-code', $url->param('state'));
        $this->assertCount(2, $client->requests);
        $this->assertSame('authorization_code', $client->requests[1]['grant_type']);
    }

    public function test_older_authorization_cannot_replace_a_newer_connection(): void {
        global $SESSION;
        $client = $this->client();
        $oldurl = $client->begin();
        $oldpending = $SESSION->mod_webexmeetings_oauth;
        $newurl = $client->begin();
        $client->complete('new-code', $newurl->param('state'));
        $SESSION->mod_webexmeetings_oauth = $oldpending;
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('oauthconfigchanged', oauth::COMPONENT));
        $client->complete('old-code', $oldurl->param('state'));
    }

    public function test_changed_credentials_do_not_reuse_cached_token(): void {
        $client = $this->client();
        $url = $client->begin();
        $client->complete('fake-code', $url->param('state'));
        set_config('client_secret', 'new-secret', oauth::COMPONENT);
        $this->assertSame('oauthconfigchanged', oauth::status());
        $this->expectException(\moodle_exception::class);
        $client->access_token();
    }

    public function test_disconnect_keeps_client_credentials_and_blocks_fallback(): void {
        $client = $this->client();
        $url = $client->begin();
        $client->complete('fake-code', $url->param('state'));
        set_config('personal_token', 'old-personal-token', oauth::COMPONENT);
        $client->disconnect();
        $config = oauth::configuration();
        $this->assertSame('test-client-id', $config->client_id);
        $this->assertSame('test-client-secret', $config->client_secret);
        $this->assertSame('old-personal-token', $config->personal_token);
        $this->assertSame('oauth', $config->auth_method);
        $this->assertFalse(isset($config->refresh_token));
        $this->assertFalse(isset($config->oauth_access_token));
        $this->assertSame('oauthnotconnected', oauth::status());
        $this->expectException(\moodle_exception::class);
        $client->access_token();
    }

    public function test_personal_token_path_does_not_call_oauth(): void {
        set_config('auth_method', 'bot', oauth::COMPONENT);
        set_config('personal_token', ' personal-token ', oauth::COMPONENT);
        $client = new oauth_stub();
        $this->assertSame('personal-token', $client->access_token());
        $this->assertCount(0, $client->requests);
    }

    public function test_organization_profile_uses_only_admin_meeting_scopes_and_profile_read(): void {
        set_config('oauth_scope_profile', 'organization', oauth::COMPONENT);
        $url = $this->client()->begin();
        $this->assertSame('meeting:admin_schedule_read meeting:admin_schedule_write ' .
            'meeting:admin_participants_read spark:people_read', $url->param('scope'));
        $this->assertStringNotContainsString('spark:all', $url->param('scope'));
    }

    public function test_unrecognised_scope_profile_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        oauth::scopes('arbitrary-scope');
    }

    public function test_https_required_for_callback(): void {
        global $CFG;
        $CFG->wwwroot = 'http://webex-qa.example';
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('oauthhttpsrequired', oauth::COMPONENT));
        $this->client()->begin();
    }

    public function test_forced_refresh_token_cannot_be_overwritten_by_connect(): void {
        global $CFG;
        $CFG->forced_plugin_settings[oauth::COMPONENT]['refresh_token'] = 'forced-refresh-token';
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('oauthforcedsettings', oauth::COMPONENT));
        $this->client()->begin();
    }

    /** @return array */
    public static function admin_actions_provider(): array {
        return [['begin'], ['complete'], ['disconnect']];
    }

    #[DataProvider('admin_actions_provider')]
    public function test_administrative_actions_require_capability(string $action): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        $client = $this->client();
        $action === 'complete' ? $client->complete('fake-code', str_repeat('0', 64)) : $client->$action();
    }

    public function test_real_moodle_curl_transport_with_mock_response(): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        set_config('refresh_token', 'old-refresh-token', oauth::COMPONENT);
        \curl::mock_response(json_encode(self::token_data()));
        $this->assertSame('fake-access-token', (new oauth())->access_token());
    }
}
