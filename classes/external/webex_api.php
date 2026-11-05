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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cisco Webex API integration
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexmeetings\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Webex API class for managing meetings
 */
class webex_api {
    
    /** @var string Webex REST API base URL */
    const API_BASE_URL = 'https://webexapis.com/v1';
    
    /** @var string Access token */
    private $access_token;
    
    /** @var float Delay between consecutive API calls in seconds */
    private $rate_limit_delay = 1.0;

    /** @var float Timestamp of the last API call */
    private $last_api_call = 0.0;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->get_access_token();
    }
    
    /**
     * Get a shared OAuth access token or the configured legacy personal token.
     *
     * @return string
     */
    private function get_access_token() {
        $this->access_token = (new \mod_webexmeetings\local\oauth())->access_token();
        return $this->access_token;
    }

    /**
     * Rate limit API calls to avoid hitting service limits
     */
    private function rate_limit_check() {
        $now = microtime(true);
        if ($this->last_api_call > 0) {
            $elapsed = $now - $this->last_api_call;
            if ($elapsed < $this->rate_limit_delay) {
                $sleep = ($this->rate_limit_delay - $elapsed) * 1000000;
                usleep((int)$sleep);
            }
        }
        $this->last_api_call = microtime(true);
    }

    /**
     * Make a Webex request and keep response headers for Link-based pagination.
     *
     * @param string $method HTTP method
     * @param string $url Absolute Webex API URL
     * @param array|null $data JSON request body
     * @return array{body: mixed, headers: array}
     */
    protected function request(string $method, string $url, ?array $data = null): array {
        $curl = new \curl(['debug' => false]);
        $curl->setHeader([
            'Authorization: Bearer ' . $this->access_token,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
        $options = [
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
        ];
        $this->rate_limit_check();
        switch ($method) {
            case 'GET':
                $response = $curl->get($url, [], $options);
                break;
            case 'POST':
                $response = $curl->post($url, json_encode($data), $options);
                break;
            case 'PUT':
                $response = $curl->put($url, json_encode($data), $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, [], $options);
                break;
            default:
                throw new \coding_exception('Unsupported Webex request method');
        }
        $status = (int)($curl->get_info()['http_code'] ?? 0);
        if ($response === false || $status < 200 || $status >= 300) {
            throw new \Exception('Webex API request failed (HTTP ' . $status . ')');
        }
        $body = ($response === '' || $response === null) ? null : json_decode($response);
        if ($response !== '' && $response !== null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Webex API returned an invalid response');
        }
        return ['body' => $body, 'headers' => $curl->getResponse()];
    }

    /** @return array Paginated Webex items from one trusted endpoint. */
    private function list_items(string $url, string $path): array {
        $items = [];
        $seen = [];
        while ($url !== null) {
            $parts = parse_url($url);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' ||
                    ($parts['host'] ?? '') !== 'webexapis.com' || ($parts['path'] ?? '') !== $path ||
                    isset($parts['port']) || isset($parts['user']) || isset($parts['pass']) || isset($seen[$url]) ||
                    count($seen) >= 100) {
                throw new \Exception('Invalid Webex pagination URL');
            }
            $seen[$url] = true;
            $page = $this->request('GET', $url);
            if (!is_object($page['body']) || !isset($page['body']->items) || !is_array($page['body']->items)) {
                throw new \Exception('Webex API returned an invalid list');
            }
            $items = array_merge($items, $page['body']->items);
            $url = null;
            foreach ($page['headers'] as $name => $value) {
                if (strcasecmp($name, 'link') !== 0) {
                    continue;
                }
                foreach ((array)$value as $header) {
                    if (preg_match('/<([^>]+)>;\s*rel="?next"?/i', $header, $matches)) {
                        $url = $matches[1];
                        break 2;
                    }
                }
            }
        }
        return $items;
    }
    
    /**
     * Create a Webex meeting
     *
     * @param stdClass $meeting_data
     * @return stdClass|false
     */
    public function create_meeting($meeting_data) {
        $url = self::API_BASE_URL . '/meetings';
        
        // Convert Moodle times to ISO format
        $start_time = gmdate('Y-m-d\TH:i:s\Z', $meeting_data->start_time);
        $end_time = isset($meeting_data->end_time) && $meeting_data->end_time ? 
                    gmdate('Y-m-d\TH:i:s\Z', $meeting_data->end_time) :
                    gmdate('Y-m-d\TH:i:s\Z', $meeting_data->start_time + 3600);
        
        $meeting = array(
            'title' => $meeting_data->name,
            'start' => $start_time,
            'end' => $end_time,
            'enabledAutoRecordMeeting' => false,
            'allowAnyUserToBeCoHost' => false,
        );

        // Add site URL if configured
        $site_url = get_config('mod_webexmeetings', 'site_url');
        if (!empty($site_url)) {
            $meeting['siteUrl'] = $site_url;
        }
        
        // Add password if specified
        if (!empty($meeting_data->meeting_password)) {
            $meeting['password'] = $meeting_data->meeting_password;
        }
        
        // Set recurrence if needed
        if (!empty($meeting_data->recurring)) {
            $meeting['recurrence'] = $this->build_recurrence_pattern($meeting_data);
        }

        // Add explicit host email if configured in settings
        $api_host_email = get_config('mod_webexmeetings', 'api_host_email');
        if (!empty($api_host_email)) {
            $meeting['hostEmail'] = $api_host_email;
        }

        $result = $this->request('POST', $url, $meeting)['body'];
        if (!is_object($result) || empty($result->id) || empty($result->webLink)) {
            throw new \Exception('Webex API did not return a meeting ID and join link');
        }
        return $result;
    }
    
    /**
     * Update a Webex meeting
     *
     * @param stdClass $meeting_data
     * @return bool
     */
    public function update_meeting($meeting_data) {
        if (empty($meeting_data->meeting_id)) {
            return false;
        }

        $url = self::API_BASE_URL . '/meetings/' . rawurlencode($meeting_data->meeting_id);
        
        $update_data = array(
            'title' => $meeting_data->name,
            'start' => gmdate('Y-m-d\TH:i:s\Z', $meeting_data->start_time),
            'end' => gmdate('Y-m-d\TH:i:s\Z', $meeting_data->end_time),
        );

        if (!empty($meeting_data->meeting_password)) {
            $update_data['password'] = $meeting_data->meeting_password;
        }

        $this->request('PUT', $url, $update_data);
        return true;
    }
    
    /**
     * Delete a Webex meeting
     *
     * @param string $meeting_id Meeting identifier
     * @return bool
     */
    public function delete_meeting($meeting_id) {
        $url = self::API_BASE_URL . '/meetings/' . rawurlencode($meeting_id);
        $this->request('DELETE', $url);
        return true;
    }
    
    /**
     * Get attendance/participants for a meeting
     * @param string $meeting_id Scheduled Webex meeting ID
     * @param int $local_meeting_id Moodle activity ID for unmatched participants
     * @param int $start_time Scheduled start timestamp
     * @param int $end_time Scheduled end timestamp
     * @return array|false
     */
    public function get_attendance($meeting_id, int $local_meeting_id, int $start_time, int $end_time) {
        $scheduled = $this->get_meeting($meeting_id);
        if (!$scheduled) {
            throw new \Exception('Webex API did not return the scheduled meeting');
        }
        $seriesid = $scheduled->meetingSeriesId ?? $meeting_id;
        $from = max($start_time - DAYSECS, time() - 30 * DAYSECS);
        $to = max($end_time, time()) + DAYSECS;
        $query = http_build_query([
            'meetingSeriesId' => $seriesid,
            'meetingType' => 'meeting',
            'from' => gmdate('Y-m-d\TH:i:s\Z', $from),
            'to' => gmdate('Y-m-d\TH:i:s\Z', $to),
            'max' => 100,
        ], '', '&', PHP_QUERY_RFC3986);
        $instances = $this->list_items(self::API_BASE_URL . '/meetings?' . $query, '/v1/meetings');
        $all_items = [];
        foreach ($instances as $instance) {
            if (($instance->meetingType ?? '') !== 'meeting' ||
                    ($instance->meetingSeriesId ?? '') !== $seriesid || empty($instance->id)) {
                continue;
            }
            $url = self::API_BASE_URL . '/meetingParticipants?meetingId=' . rawurlencode($instance->id);
            $all_items = array_merge($all_items, $this->list_items($url, '/v1/meetingParticipants'));
        }
        if (!$all_items) {
            return false;
        }

        return $this->process_attendance_records($all_items, $local_meeting_id);
    }
    
    /**
     * Process attendance records from Webex API
     *
     * @param array $records
     * @param int $meetingid Moodle activity ID
     * @return array
     */
    private function process_attendance_records($records, $meetingid) {
        $processed = [];
        foreach ($records as $record) {
            $sessions = \mod_webexmeetings\local\attendance_mapping::sessions($record);
            $user = \mod_webexmeetings\local\attendance_mapping::match($record, (int)$meetingid);
            if (!$user) {
                \mod_webexmeetings\local\attendance_mapping::remember($record, (int)$meetingid, $sessions);
                continue;
            }
            $processed[] = (object)[
                'userid' => $user->id,
                'email' => $record->email ?? '',
                'display_name' => $record->displayName ?? '',
                'host' => $record->host ?? false,
                'sessions' => $sessions,
                'total_duration' => array_sum(array_column($sessions, 'duration')),
            ];
        }
        return $processed;
    }
    
    /**
     * Build recurrence pattern for meeting
     *
     * @param stdClass $meeting_data
     * @return string
     */
    private function build_recurrence_pattern($meeting_data) {
        // Webex uses ICAL-style RRULE for recurrence
        $pattern = $meeting_data->recurrence_pattern ?? 'weekly';
        $freq = strtoupper($pattern);
        
        $rrule = "FREQ={$freq};INTERVAL=1";
        
        if (!empty($meeting_data->recurrence_end)) {
            $rrule .= ";UNTIL=" . gmdate('Ymd\THis\Z', $meeting_data->recurrence_end);
        } else {
            $rrule .= ";COUNT=26"; // Default to 6 months of weekly meetings
        }
        
        return $rrule;
    }
    
    /**
     * Get meeting info
     *
     * @param string $meeting_id
     * @return stdClass|false
     */
    public function get_meeting($meeting_id) {
        $url = self::API_BASE_URL . '/meetings/' . rawurlencode($meeting_id);
        $result = $this->request('GET', $url)['body'];
        if (is_object($result) && isset($result->id)) {
            return $result;
        }
        
        return false;
    }

    /** Generate a short-lived attendee link using only the authenticated user's identity. */
    public function get_join_link(string $meetingid, string $email, string $displayname): string {
        if (trim($meetingid) === '') {
            throw new \moodle_exception('nomeetingid', 'mod_webexmeetings');
        }
        if (!validate_email($email) || preg_match('/@guest\.webex\.localhost$/i', $email) ||
                trim($displayname) === '' || \core_text::strlen($displayname) > 128 ||
                preg_match('/[\x00-\x1f\x7f]/', $displayname)) {
            throw new \moodle_exception('participantidentityunavailable', 'mod_webexmeetings');
        }
        $data = ['meetingId' => $meetingid, 'joinDirectly' => false, 'expirationMinutes' => 5,
            'email' => $email, 'displayName' => $displayname];
        $config = \mod_webexmeetings\local\oauth::configuration();
        if (($config->oauth_scope_profile ?? 'host') === 'organization' && !empty($config->api_host_email)) {
            $data['hostEmail'] = $config->api_host_email;
        }
        $response = $this->request('POST', self::API_BASE_URL . '/meetings/join', $data)['body'];
        $link = is_object($response) ? ($response->joinLink ?? null) : null;
        if (is_object($response) && isset($response->startLink) && $response->startLink === $link) {
            throw new \moodle_exception('personaljoinunavailable', 'mod_webexmeetings');
        }
        return self::validated_meeting_link($link, 'personaljoinunavailable');
    }

    /**
     * Generate a short-lived host link. Never persist this bearer-style URL.
     *
     * @param string $meetingid ID from the local activity, not user input.
     * @return string Validated Webex HTTPS start URL.
     */
    public function get_start_link(string $meetingid): string {
        if (trim($meetingid) === '') {
            throw new \moodle_exception('nomeetingid', 'mod_webexmeetings');
        }
        $data = ['meetingId' => $meetingid, 'joinDirectly' => false, 'expirationMinutes' => 5];
        $config = \mod_webexmeetings\local\oauth::configuration();
        if (($config->oauth_scope_profile ?? 'host') === 'organization' && !empty($config->api_host_email)) {
            $data['hostEmail'] = $config->api_host_email;
        }
        $response = $this->request('POST', self::API_BASE_URL . '/meetings/join', $data)['body'];
        $link = is_object($response) ? ($response->startLink ?? null) : null;
        return self::validated_meeting_link($link, 'startmeetingunavailable');
    }

    /** Validate bearer-style destinations without logging or persisting them. */
    private static function validated_meeting_link($link, string $errorcode): string {
        if (!is_string($link) || str_contains($link, '\\') || preg_match('/[\x00-\x20\x7f]/', $link)) {
            throw new \moodle_exception($errorcode, 'mod_webexmeetings');
        }
        $parts = parse_url($link);
        $host = strtolower($parts['host'] ?? '');
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' ||
                !str_ends_with($host, '.webex.com') || isset($parts['user']) || isset($parts['pass']) ||
                isset($parts['port']) || isset($parts['fragment'])) {
            throw new \moodle_exception($errorcode, 'mod_webexmeetings');
        }
        return $link;
    }

    /**
     * Test the API connection.
     *
     * @return bool
     * @throws \Exception on failure
     */
    public function test_connection() {
        $url = self::API_BASE_URL . '/people/me';

        $result = $this->request('GET', $url)['body'];
        if (is_object($result) && isset($result->id)) {
            $this->list_items(self::API_BASE_URL . '/meetings?meetingType=meetingSeries&max=1', '/v1/meetings');
            return true;
        }
        throw new \Exception('Webex API connection returned an invalid profile');
    }
}
