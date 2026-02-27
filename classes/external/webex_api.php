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
     * Get access token
     * Webex supports multiple auth methods:
     * - Bot token (static, configured in settings)
     * - OAuth2 with refresh token
     *
     * @return string
     */
    private function get_access_token() {
        // Check if we have a valid cached token
        // Cache control temporarily disabled to force reading the new token
        // $cache = \cache::make('mod_webexmeetings', 'oauth_token');
        // $cached_token = $cache->get('access_token');
        // 
        // if ($cached_token && $cached_token['expires'] > time()) {
        //     $this->access_token = trim($cached_token['token']);
        //     return $this->access_token;
        // }
        
        // Check Authentication Method
        $auth_method = get_config('mod_webexmeetings', 'auth_method');
        
        if ($auth_method !== 'oauth') {
            // Check if using Personal Access Token (static)
            $personal_token = get_config('mod_webexmeetings', 'personal_token');
            if (!empty($personal_token)) {
                $this->access_token = trim($personal_token);
                // Cache personal token for 24 hours (or its natural lifespan)
                // $cache->set('access_token', array(
                //     'token' => $this->access_token,
                //     'expires' => time() + 86400
                // ));
                return $this->access_token;
            }
        } else {
            // OAuth2 refresh token flow
            $client_id = get_config('mod_webexmeetings', 'client_id');
            $client_secret = get_config('mod_webexmeetings', 'client_secret');
            $refresh_token = get_config('mod_webexmeetings', 'refresh_token');
            
            if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
                throw new \moodle_exception('missingcredentials', 'mod_webexmeetings');
            }
            
            $token_url = self::API_BASE_URL . '/access_token';
            
            $params = array(
                'grant_type' => 'refresh_token',
                'client_id' => trim($client_id),
                'client_secret' => trim($client_secret),
                'refresh_token' => trim($refresh_token),
            );
            
            $curl = new \curl();
            $curl->setHeader(array('Content-Type: application/x-www-form-urlencoded'));
            $this->rate_limit_check();
            $response = $curl->post($token_url, http_build_query($params));
            $data = json_decode($response);
            
            // Debug the OAuth flow
            $debug_file = sys_get_temp_dir() . '/webex_debug.txt';
            file_put_contents($debug_file, "==== OAUTH TOKEN REFRESH ====\n" . print_r($data, true) . "\n\n", FILE_APPEND);
            
            if (isset($data->access_token)) {
                $this->access_token = $data->access_token;
                
                // Cache token
                // $cache->set('access_token', array(
                //     'token' => $data->access_token,
                //     'expires' => time() + ($data->expires_in ?? 3600) - 300 // 5 minutes buffer
                // ));
                
                // Update refresh token if a new one was provided
                if (isset($data->refresh_token)) {
                    set_config('refresh_token', $data->refresh_token, 'mod_webexmeetings');
                }
                
                return $this->access_token;
            }
            
            throw new \moodle_exception('failedtogetaccesstoken', 'mod_webexmeetings', '', null, $response);
        }
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
     * Create a Webex meeting
     *
     * @param stdClass $meeting_data
     * @return stdClass|false
     */
    public function create_meeting($meeting_data) {
        $url = self::API_BASE_URL . '/meetings';
        
        // Convert Moodle times to ISO format
        $start_time = date('Y-m-d\TH:i:s\Z', $meeting_data->start_time);
        $end_time = isset($meeting_data->end_time) && $meeting_data->end_time ? 
                    date('Y-m-d\TH:i:s\Z', $meeting_data->end_time) : 
                    date('Y-m-d\TH:i:s\Z', $meeting_data->start_time + 3600);
        
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

        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        );
        
        // Debug Request
        $debug_req = array(
            'date' => date('Y-m-d H:i:s'),
            'url' => $url,
            'method' => 'POST',
            'auth' => 'Bearer ***' . substr($this->access_token, -5), // Show only last 5 chars
            'api_host_email_setting' => $api_host_email,
            'payload' => $meeting
        );
        $debug_file = sys_get_temp_dir() . '/webex_debug.txt';
        file_put_contents($debug_file, "==== WEBEX API REQUEST ====\n" . print_r($debug_req, true) . "\n", FILE_APPEND);
        
        $curl = new \curl();
        $curl->setHeader($headers);
        $this->rate_limit_check();
        $response = $curl->post($url, json_encode($meeting));

        // Debug Response
        $info = $curl->get_info();
        $debug_res = array(
            'http_code' => $info['http_code'] ?? 'unknown',
            'raw_response' => $response
        );
        file_put_contents($debug_file, "==== WEBEX API RESPONSE ====\n" . print_r($debug_res, true) . "\n\n", FILE_APPEND);
        
        $result = json_decode($response);
        
        if (isset($result->id)) {
            return $result;
        }

        $message = isset($result->message) ? $result->message : $response;
        if (isset($result->errors) && is_array($result->errors)) {
            $message = implode('; ', array_map(function($err) {
                return $err->description ?? '';
            }, $result->errors));
        }
        throw new \Exception('Failed to create Webex meeting: ' . $message);
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

        $url = self::API_BASE_URL . '/meetings/' . $meeting_data->meeting_id;
        
        $update_data = array(
            'title' => $meeting_data->name,
            'start' => date('Y-m-d\TH:i:s\Z', $meeting_data->start_time),
            'end' => date('Y-m-d\TH:i:s\Z', $meeting_data->end_time),
        );

        if (!empty($meeting_data->password)) {
            $update_data['password'] = $meeting_data->password;
        }
        
        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        );
        
        $curl = new \curl();
        $curl->setHeader($headers);
        $this->rate_limit_check();
        $response = $curl->put($url, json_encode($update_data));

        $result = json_decode($response);
        if ($response === false || (isset($result->message) && !isset($result->id))) {
            $message = isset($result->message) ? $result->message : $response;
            throw new \Exception('Failed to update Webex meeting: ' . $message);
        }

        return true;
    }
    
    /**
     * Delete a Webex meeting
     *
     * @param string $meeting_id Meeting identifier
     * @return bool
     */
    public function delete_meeting($meeting_id) {
        $url = self::API_BASE_URL . '/meetings/' . $meeting_id;
        
        $headers = array(
            'Authorization: Bearer ' . $this->access_token
        );
        
        $curl = new \curl();
        $curl->setHeader($headers);
        $this->rate_limit_check();
        $response = $curl->delete($url);

        $result = json_decode($response);
        if ($response === false || (isset($result->message) && !isset($result->id))) {
            $message = isset($result->message) ? $result->message : ($response ?: 'Unknown error');
            throw new \Exception('Failed to delete Webex meeting: ' . $message);
        }

        return true;
    }
    
    /**
     * Get attendance/participants for a meeting
     * NOTE: Webex participant data may not be available until ~24h after meeting ends
     *
     * @param string $meeting_id
     * @return array|false
     */
    public function get_attendance($meeting_id) {
        // Use meetingParticipants endpoint
        $url = self::API_BASE_URL . '/meetingParticipants?meetingId=' . urlencode($meeting_id);
        
        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        );
        
        $all_items = array();
        
        // Paginate through results
        while ($url) {
            $curl = new \curl();
            $curl->setHeader($headers);
            $this->rate_limit_check();
            $response = $curl->get($url);
            
            $result = json_decode($response);
            
            if (isset($result->message)) {
                if (strpos($result->message, 'not found') !== false ||
                    strpos($result->message, 'No data') !== false) {
                    return false;
                }
                throw new \Exception('Errore API Webex: ' . $result->message);
            }
            
            if (isset($result->items) && !empty($result->items)) {
                $all_items = array_merge($all_items, $result->items);
            }
            
            // Check for pagination
            $url = null;
            $info = $curl->get_info();
            // Webex uses Link headers for pagination
            if (isset($info['link'])) {
                if (preg_match('/<([^>]+)>;\s*rel="next"/', $info['link'], $matches)) {
                    $url = $matches[1];
                }
            }
        }
        
        if (empty($all_items)) {
            return false;
        }
        
        return $this->process_attendance_records($all_items, $meeting_id);
    }
    
    /**
     * Process attendance records from Webex API
     *
     * @param array $records
     * @param string $meetingid
     * @return array
     */
    private function process_attendance_records($records, $meetingid) {
        global $DB;
        
        $processed = array();
        $unmatched = array();
        
        foreach ($records as $record) {
            // Try to match the user
            $user = $this->match_webex_user($record);
            
            if ($user) {
                $attendance = new \stdClass();
                $attendance->userid = $user->id;
                $attendance->email = $record->email ?? '';
                $attendance->display_name = $record->displayName ?? '';
                $attendance->host = $record->host ?? false;
                
                // Process join/leave times
                $attendance->sessions = array();
                if (isset($record->devices) && is_array($record->devices)) {
                    foreach ($record->devices as $device) {
                        $session = new \stdClass();
                        $session->join_time = isset($device->joinedTime) ? strtotime($device->joinedTime) : 0;
                        $session->leave_time = isset($device->leftTime) ? strtotime($device->leftTime) : 0;
                        $session->duration = ($session->leave_time && $session->join_time) ?
                            ($session->leave_time - $session->join_time) : 0;
                        $attendance->sessions[] = $session;
                    }
                } else {
                    // Single session from joinedTime/leftTime at top level
                    $session = new \stdClass();
                    $session->join_time = isset($record->joinedTime) ? strtotime($record->joinedTime) : 0;
                    $session->leave_time = isset($record->leftTime) ? strtotime($record->leftTime) : 0;
                    $session->duration = ($session->leave_time && $session->join_time) ?
                        ($session->leave_time - $session->join_time) : 0;
                    if ($session->join_time) {
                        $attendance->sessions[] = $session;
                    }
                }
                
                $attendance->total_duration = 0;
                foreach ($attendance->sessions as $s) {
                    $attendance->total_duration += $s->duration;
                }
                
                $processed[] = $attendance;
            } else {
                // Unmatched user
                $unmatched_user = new \stdClass();
                $unmatched_user->meetingid = $meetingid;
                $unmatched_user->webex_email = $record->email ?? '';
                $unmatched_user->webex_displayname = $record->displayName ?? '';
                $unmatched_user->webex_id = $record->id ?? '';
                $unmatched_user->timecreated = time();
                
                // Get join/leave times
                if (isset($record->joinedTime)) {
                    $unmatched_user->join_time = strtotime($record->joinedTime);
                } else {
                    $unmatched_user->join_time = 0;
                }
                if (isset($record->leftTime)) {
                    $unmatched_user->leave_time = strtotime($record->leftTime);
                } else {
                    $unmatched_user->leave_time = 0;
                }
                $unmatched_user->duration = ($unmatched_user->leave_time && $unmatched_user->join_time) ?
                    ($unmatched_user->leave_time - $unmatched_user->join_time) : 0;
                
                // Check if already exists
                $existing = $DB->get_record('webexmeetings_unmatched', array(
                    'meetingid' => $meetingid,
                    'webex_email' => $unmatched_user->webex_email
                ));
                
                if (!$existing) {
                    $DB->insert_record('webexmeetings_unmatched', $unmatched_user);
                } else {
                    $unmatched_user->id = $existing->id;
                    $DB->update_record('webexmeetings_unmatched', $unmatched_user);
                }
                
                $unmatched[] = $unmatched_user;
            }
        }
        
        if (!empty($unmatched)) {
            debugging('Found ' . count($unmatched) . ' unmatched Webex users for meeting ' . $meetingid, DEBUG_DEVELOPER);
        }
        
        return $processed;
    }
    
    /**
     * Match Webex user with Moodle user
     *
     * @param stdClass $webex_user
     * @return stdClass|false
     */
    private function match_webex_user($webex_user) {
        global $DB;
        
        // Try to match by email
        if (!empty($webex_user->email)) {
            $user = $DB->get_record('user', array('email' => $webex_user->email));
            if ($user) {
                return $user;
            }
        }
        
        // Try to match by username (email prefix)
        if (!empty($webex_user->email)) {
            $username = strtolower(explode('@', $webex_user->email)[0]);
            $user = $DB->get_record('user', array('username' => $username));
            if ($user) {
                return $user;
            }
        }
        
        return false;
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
            $rrule .= ";UNTIL=" . date('Ymd\THis\Z', $meeting_data->recurrence_end);
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
        $url = self::API_BASE_URL . '/meetings/' . $meeting_id;
        
        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        );
        
        $curl = new \curl();
        $curl->setHeader($headers);
        $this->rate_limit_check();
        $response = $curl->get($url);
        
        $result = json_decode($response);
        
        if (isset($result->id)) {
            return $result;
        }
        
        return false;
    }

    /**
     * Test the API connection
     *
     * @return bool
     * @throws \Exception on failure
     */
    public function test_connection() {
        $url = self::API_BASE_URL . '/people/me';

        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        );

        $debug_file = sys_get_temp_dir() . '/webex_debug.txt';
        $debug_req = array(
            'date' => date('Y-m-d H:i:s'),
            'action' => 'test_connection',
            'url' => $url,
            'auth_header_sent' => 'Bearer ***' . substr($this->access_token, -10),
            'token_length' => strlen($this->access_token)
        );
        file_put_contents($debug_file, "==== TEST CONNECTION REQUEST ====\n" . print_r($debug_req, true) . "\n", FILE_APPEND);

        $curl = new \curl();
        $curl->setHeader($headers);
        $this->rate_limit_check();
        $response = $curl->get($url);

        $info = $curl->get_info();
        $debug_res = array(
            'http_code' => $info['http_code'] ?? 'unknown',
            'raw_response' => $response
        );
        file_put_contents($debug_file, "==== TEST CONNECTION RESPONSE ====\n" . print_r($debug_res, true) . "\n\n", FILE_APPEND);

        $result = json_decode($response);

        if (isset($result->id)) {
            return true;
        }

        $message = isset($result->message) ? $result->message : 'Unknown error';
        throw new \Exception('Webex API connection failed: ' . $message);
    }
}
