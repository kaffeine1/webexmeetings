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
 * View a Webex meeting
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/webexmeetings/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID
$w = optional_param('w', 0, PARAM_INT);   // Webexmeetings instance ID

if ($id) {
    $cm = get_coursemodule_from_id('webexmeetings', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $webexmeeting = $DB->get_record('webexmeetings', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($w) {
    $webexmeeting = $DB->get_record('webexmeetings', array('id' => $w), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $webexmeeting->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('webexmeetings', $webexmeeting->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('invalidcoursemodule');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexmeetings:view', $context);

// Trigger course module viewed event
$event = \mod_webexmeetings\event\course_module_viewed::create(array(
    'objectid' => $webexmeeting->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('webexmeetings', $webexmeeting);
$event->trigger();

// Mark as viewed for completion
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Set page parameters
$PAGE->set_url('/mod/webexmeetings/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($webexmeeting->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($webexmeeting->name));

// Meeting description
if (!empty($webexmeeting->intro)) {
    echo $OUTPUT->box(format_module_intro('webexmeetings', $webexmeeting, $cm->id), 'generalbox mod_introbox', 'webexmeetingsintro');
}

// Meeting details
echo '<div class="webexmeeting-details card">';
echo '<div class="card-body">';
echo '<h5 class="card-title">' . get_string('meetingdetails', 'webexmeetings') . '</h5>';

// Start time
echo '<p><strong>' . get_string('starttime', 'webexmeetings') . ':</strong> ' .
     userdate($webexmeeting->start_time, get_string('strftimedaydatetime', 'langconfig')) . '</p>';

// End time
echo '<p><strong>' . get_string('endtime', 'webexmeetings') . ':</strong> ' .
     userdate($webexmeeting->end_time, get_string('strftimedaydatetime', 'langconfig')) . '</p>';

// Meeting status
$now = time();
if ($now < $webexmeeting->start_time) {
    $status = get_string('meetingnotstarted', 'webexmeetings');
    $statusclass = 'badge-info';
} else if ($now >= $webexmeeting->start_time && $now <= $webexmeeting->end_time) {
    $status = get_string('meetinginprogress', 'webexmeetings');
    $statusclass = 'badge-success';
} else {
    $status = get_string('meetingended', 'webexmeetings');
    $statusclass = 'badge-secondary';
}
echo '<p><strong>' . get_string('status') . ':</strong> <span class="badge ' . $statusclass . '">' . $status . '</span></p>';

// Recurring
if ($webexmeeting->recurring) {
    echo '<p><span class="badge badge-info">' . get_string('recurringmeeting', 'webexmeetings') . '</span></p>';
}

// Join button
if (!empty($webexmeeting->join_url)) {
    echo '<div class="mt-3">';
    echo '<a href="' . htmlspecialchars($webexmeeting->join_url) . '" target="_blank" ' .
         'class="btn btn-primary btn-lg" rel="noopener noreferrer">';
    echo '<i class="fa fa-video-camera"></i> ' . get_string('joinmeeting', 'webexmeetings');
    echo '</a>';
    echo '</div>';
    
    // Password display
    if (!empty($webexmeeting->password)) {
        echo '<p class="mt-2 text-muted"><small>' . get_string('meetingpassword', 'webexmeetings') . ': ' .
             '<span class="font-weight-bold">' . htmlspecialchars($webexmeeting->password) . '</span></small></p>';
    }
} else {
    echo '<div class="alert alert-warning mt-3">' . get_string('nojoinurl', 'webexmeetings') . '</div>';
}

echo '</div>';
echo '</div>';

// Attendance section for teachers
if (has_capability('mod/webexmeetings:viewattendance', $context)) {
    echo '<div class="webexmeeting-attendance card mt-4">';
    echo '<div class="card-body">';
    echo '<h5 class="card-title">' . get_string('attendancesummary', 'webexmeetings') . '</h5>';
    
    // Get attendance summary
    $attendance = webexmeetings_get_attendance_report($webexmeeting->id);
    
    if (!empty($attendance)) {
        echo '<table class="table table-sm table-striped">';
        echo '<thead><tr>';
        echo '<th>' . get_string('name') . '</th>';
        echo '<th>' . get_string('email') . '</th>';
        echo '<th>' . get_string('jointime', 'webexmeetings') . '</th>';
        echo '<th>' . get_string('leavetime', 'webexmeetings') . '</th>';
        echo '<th>' . get_string('duration', 'webexmeetings') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($attendance as $record) {
            echo '<tr>';
            echo '<td>' . fullname($record) . '</td>';
            echo '<td>' . $record->email . '</td>';
            echo '<td>' . ($record->join_time ? userdate($record->join_time, '%H:%M:%S %d/%m/%Y') : '-') . '</td>';
            echo '<td>' . ($record->leave_time ? userdate($record->leave_time, '%H:%M:%S %d/%m/%Y') : '-') . '</td>';
            echo '<td>' . ($record->duration ? format_time($record->duration) : '-') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Show full report link
        $attendanceurl = new moodle_url('/mod/webexmeetings/attendance.php', array('id' => $cm->id));
        echo '<a href="' . $attendanceurl->out() . '" class="btn btn-secondary">' .
             get_string('viewfullreport', 'webexmeetings') . '</a>';
    } else {
        echo '<p class="text-muted">' . get_string('noattendancedata', 'webexmeetings') . '</p>';
    }
    
    // Show last sync info
    if ($webexmeeting->last_sync) {
        echo '<p class="text-muted mt-2"><small>' . get_string('lastsync', 'webexmeetings') . ': ' .
             userdate($webexmeeting->last_sync, get_string('strftimedaydatetime', 'langconfig')) .
             ' (' . $webexmeeting->sync_status . ')</small></p>';
    }
    
    // Sync and unmatched users buttons
    if (has_capability('mod/webexmeetings:syncattendance', $context)) {
        $syncurl = new moodle_url('/mod/webexmeetings/sync.php', array('id' => $cm->id));
        echo '<a href="' . $syncurl->out() . '" class="btn btn-outline-primary mt-2">' .
             get_string('syncnow', 'webexmeetings') . '</a> ';
        
        // Check for unmatched users
        $unmatched_count = $DB->count_records('webexmeetings_unmatched', array(
            'meetingid' => $webexmeeting->id,
            'mapped_userid' => 0
        ));
        
        if ($unmatched_count > 0) {
            $unmatchedurl = new moodle_url('/mod/webexmeetings/unmatched_users.php', array('id' => $cm->id));
            echo '<a href="' . $unmatchedurl->out() . '" class="btn btn-outline-warning mt-2">' .
                 get_string('unmatchedusers', 'webexmeetings', $unmatched_count) . '</a>';
        }
    }
    
    echo '</div>';
    echo '</div>';
}

echo $OUTPUT->footer();
