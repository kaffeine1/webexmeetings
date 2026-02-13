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
 * Library functions for mod_webexmeetings
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add webexmeetings instance
 *
 * @param stdClass $data
 * @param mod_webexmeetings_mod_form $mform
 * @return int new instance id
 */
function webexmeetings_add_instance($data, $mform = null) {
    global $DB, $USER;
    
    $data->timecreated = time();
    $data->timemodified = time();
    
    // Set host email from current user
    $data->host_email = $USER->email;
    
    // Set site URL from plugin settings
    $data->siteurl = get_config('mod_webexmeetings', 'site_url');
    
    // Create Webex meeting via API
    $webex_api = new \mod_webexmeetings\external\webex_api();
    try {
        $meeting_info = $webex_api->create_meeting($data);
        
        if ($meeting_info) {
            debugging('Webex API response: ' . json_encode($meeting_info), DEBUG_DEVELOPER);
            $data->meeting_id = $meeting_info->id;
            $data->join_url = $meeting_info->webLink ?? '';
            if (isset($meeting_info->password)) {
                $data->password = $meeting_info->password;
            }
        }
    } catch (\Exception $e) {
        $msg = webexmeetings_extract_error_message($e);
        debugging('Webex API create_meeting error: ' . $msg, DEBUG_DEVELOPER);
        \core\notification::add(get_string('meetingcreationerror', 'webexmeetings', $msg),
            \core\output\notification::NOTIFY_ERROR);
    }
    
    $data->id = $DB->insert_record('webexmeetings', $data);
    
    // Trigger event
    $event = \mod_webexmeetings\event\meeting_created::create(array(
        'objectid' => $data->id,
        'context' => context_module::instance($data->coursemodule),
    ));
    $event->trigger();
    
    return $data->id;
}

/**
 * Update webexmeetings instance
 *
 * @param stdClass $data
 * @param mod_webexmeetings_mod_form $mform
 * @return bool
 */
function webexmeetings_update_instance($data, $mform = null) {
    global $DB;
    
    $data->timemodified = time();
    $data->id = $data->instance;
    
    // Preserve host_email and meeting_id
    $existing = $DB->get_record('webexmeetings', ['id' => $data->id], 'host_email, meeting_id, siteurl, password');
    $data->host_email = $existing->host_email;
    $data->siteurl = $existing->siteurl;
    
    // Update Webex meeting via API
    if (!empty($existing->meeting_id)) {
        $data->meeting_id = $existing->meeting_id;
        $data->password = $existing->password;
        $webex_api = new \mod_webexmeetings\external\webex_api();
        try {
            $webex_api->update_meeting($data);
        } catch (\Exception $e) {
            $msg = webexmeetings_extract_error_message($e);
            debugging('Webex API update_meeting error: ' . $msg, DEBUG_DEVELOPER);
            \core\notification::add(get_string('meetingupdateerror', 'webexmeetings', $msg),
                \core\output\notification::NOTIFY_ERROR);
        }
    }
    
    return $DB->update_record('webexmeetings', $data);
}

/**
 * Delete webexmeetings instance
 *
 * @param int $id
 * @return bool
 */
function webexmeetings_delete_instance($id) {
    global $DB;
    
    if (!$meeting = $DB->get_record('webexmeetings', array('id' => $id))) {
        return false;
    }
    
    // Delete Webex meeting via API
    if ($meeting->meeting_id) {
        $webex_api = new \mod_webexmeetings\external\webex_api();
        try {
            $webex_api->delete_meeting($meeting->meeting_id);
        } catch (\Exception $e) {
            $msg = webexmeetings_extract_error_message($e);
            debugging('Webex API delete_meeting error: ' . $msg, DEBUG_DEVELOPER);
            \core\notification::add(get_string('meetingdeleteerror', 'webexmeetings', $msg),
                \core\output\notification::NOTIFY_ERROR);
        }
    }
    
    // Delete attendance records
    $DB->delete_records('webexmeetings_attendance', array('meetingid' => $id));
    $DB->delete_records('webexmeetings_sessions', array('meetingid' => $id));
    $DB->delete_records('webexmeetings_unmatched', array('meetingid' => $id));
    
    // Delete meeting record
    $DB->delete_records('webexmeetings', array('id' => $id));
    
    return true;
}

/**
 * Return the list of view actions
 *
 * @return array
 */
function webexmeetings_get_view_actions() {
    return array('view', 'view all', 'view attendance');
}

/**
 * Return the list of post actions
 *
 * @return array
 */
function webexmeetings_get_post_actions() {
    return array('join meeting');
}

/**
 * Supports feature
 *
 * @param string $feature
 * @return mixed
 */
function webexmeetings_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function webexmeetings_reset_userdata($data) {
    global $DB;
    
    $status = array();
    
    if (!empty($data->reset_webexmeetings_attendance)) {
        $meetingssql = "SELECT wm.id
                         FROM {webexmeetings} wm
                        WHERE wm.course=?";
        $params = array($data->courseid);
        
        $DB->delete_records_select('webexmeetings_attendance', "meetingid IN ($meetingssql)", $params);
        $DB->delete_records_select('webexmeetings_sessions', "meetingid IN ($meetingssql)", $params);
        
        $status[] = array(
            'component' => get_string('modulenameplural', 'webexmeetings'),
            'item' => get_string('attendancerecords', 'webexmeetings'),
            'error' => false
        );
    }
    
    return $status;
}

/**
 * Log user attendance
 *
 * @param int $meetingid
 * @param int $userid
 * @param string $action 'join' or 'leave'
 * @param string $sessionid
 * @return bool
 */
function webexmeetings_log_attendance($meetingid, $userid, $action, $sessionid = null) {
    global $DB;
    
    if ($action == 'join') {
        // Check if there's an existing open session
        $existing = $DB->get_record('webexmeetings_sessions', array(
            'meetingid' => $meetingid,
            'userid' => $userid,
            'leave_time' => 0
        ));
        
        if (!$existing) {
            $session = new stdClass();
            $session->meetingid = $meetingid;
            $session->userid = $userid;
            $session->sessionid = $sessionid ?: uniqid('session_');
            $session->join_time = time();
            $session->ip_address = getremoteaddr();
            
            $DB->insert_record('webexmeetings_sessions', $session);
        }
    } else if ($action == 'leave') {
        // Close the most recent open session
        $sql = "SELECT * FROM {webexmeetings_sessions} 
                WHERE meetingid = ? AND userid = ? AND leave_time = 0 
                ORDER BY join_time DESC LIMIT 1";
        
        if ($session = $DB->get_record_sql($sql, array($meetingid, $userid))) {
            $session->leave_time = time();
            $session->duration = $session->leave_time - $session->join_time;
            $DB->update_record('webexmeetings_sessions', $session);
            
            // Update or create attendance summary
            webexmeetings_update_attendance_summary($meetingid, $userid);
        }
    }
    
    return true;
}

/**
 * Update attendance summary for a user
 *
 * @param int $meetingid
 * @param int $userid
 */
function webexmeetings_update_attendance_summary($meetingid, $userid) {
    global $DB;
    
    // Calculate total attendance from all sessions
    $sql = "SELECT MIN(join_time) as first_join, 
                   MAX(leave_time) as last_leave,
                   SUM(duration) as total_duration
            FROM {webexmeetings_sessions}
            WHERE meetingid = ? AND userid = ? AND leave_time > 0";
    
    $stats = $DB->get_record_sql($sql, array($meetingid, $userid));
    
    if ($stats && $stats->first_join) {
        if ($attendance = $DB->get_record('webexmeetings_attendance', 
            array('meetingid' => $meetingid, 'userid' => $userid))) {
            // Update existing record
            $attendance->join_time = $stats->first_join;
            $attendance->leave_time = $stats->last_leave;
            $attendance->duration = $stats->total_duration;
            $DB->update_record('webexmeetings_attendance', $attendance);
        } else {
            // Create new record
            $attendance = new stdClass();
            $attendance->meetingid = $meetingid;
            $attendance->userid = $userid;
            $attendance->join_time = $stats->first_join;
            $attendance->leave_time = $stats->last_leave;
            $attendance->duration = $stats->total_duration;
            $DB->insert_record('webexmeetings_attendance', $attendance);
        }
    }
}

/**
 * Get attendance report for a meeting
 *
 * @param int $meetingid
 * @return array
 */
function webexmeetings_get_attendance_report($meetingid) {
    global $DB;
    
    $sql = "SELECT u.id, u.firstname, u.lastname, u.email,
                   wa.join_time, wa.leave_time, wa.duration,
                   COUNT(ws.id) as session_count
            FROM {user} u
            JOIN {webexmeetings_attendance} wa ON wa.userid = u.id
            LEFT JOIN {webexmeetings_sessions} ws ON ws.userid = u.id AND ws.meetingid = wa.meetingid
            WHERE wa.meetingid = ?
            GROUP BY u.id, u.firstname, u.lastname, u.email, wa.join_time, wa.leave_time, wa.duration
            ORDER BY u.lastname, u.firstname";
    
    return $DB->get_records_sql($sql, array($meetingid));
}

/**
 * Get detailed sessions for a user in a meeting
 *
 * @param int $meetingid
 * @param int $userid
 * @return array
 */
function webexmeetings_get_user_sessions($meetingid, $userid) {
    global $DB;

    return $DB->get_records('webexmeetings_sessions',
        array('meetingid' => $meetingid, 'userid' => $userid),
        'join_time ASC');
}

/**
 * Extract an error message from a Webex API exception.
 *
 * @param \Exception $e
 * @return string
 */
function webexmeetings_extract_error_message(\Exception $e) {
    $candidates = [$e->getMessage()];
    if (property_exists($e, 'debuginfo') && !empty($e->debuginfo)) {
        $candidates[] = $e->debuginfo;
    }

    foreach ($candidates as $candidate) {
        $decoded = json_decode($candidate);
        if ($decoded && isset($decoded->message)) {
            return $decoded->message;
        }
        if ($decoded && isset($decoded->errors) && is_array($decoded->errors)) {
            return implode('; ', array_map(function($err) {
                return $err->description ?? $err->message ?? '';
            }, $decoded->errors));
        }
        if (preg_match('/\{.*\}/', $candidate, $matches)) {
            $decoded = json_decode($matches[0]);
            if ($decoded && isset($decoded->message)) {
                return $decoded->message;
            }
        }
    }

    return $e->getMessage();
}
