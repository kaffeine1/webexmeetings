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
 * Attendance report for a Webex meeting
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/webexmeetings/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$id = required_param('id', PARAM_INT); // Course module ID
$export = optional_param('export', '', PARAM_ALPHA); // Export format: csv, excel, pdf

$cm = get_coursemodule_from_id('webexmeetings', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$webexmeeting = $DB->get_record('webexmeetings', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexmeetings:viewattendance', $context);

// Trigger event
$event = \mod_webexmeetings\event\attendance_viewed::create(array(
    'objectid' => $webexmeeting->id,
    'context' => $context,
));
$event->trigger();

// Get attendance data
$attendance = webexmeetings_get_attendance_report($webexmeeting->id);

// Get enrolled users for comparison
$enrolled = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');

// Handle export
if (!empty($export) && has_capability('mod/webexmeetings:exportattendance', $context)) {
    $filename = clean_filename($webexmeeting->name . '_attendance_' . date('Ymd'));
    
    if ($export === 'csv') {
        // CSV Export
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for UTF-8
        
        fputcsv($output, array(
            get_string('firstname'),
            get_string('lastname'),
            get_string('email'),
            get_string('jointime', 'webexmeetings'),
            get_string('leavetime', 'webexmeetings'),
            get_string('duration', 'webexmeetings'),
            get_string('sessions', 'webexmeetings'),
            get_string('status')
        ), ';');
        
        foreach ($enrolled as $user) {
            $att = isset($attendance[$user->id]) ? $attendance[$user->id] : null;
            
            fputcsv($output, array(
                $user->firstname,
                $user->lastname,
                $user->email,
                $att ? userdate($att->join_time, '%H:%M:%S %d/%m/%Y') : '-',
                $att ? ($att->leave_time ? userdate($att->leave_time, '%H:%M:%S %d/%m/%Y') : '-') : '-',
                $att ? format_time($att->duration) : '-',
                $att ? $att->session_count : '0',
                $att ? get_string('present', 'webexmeetings') : get_string('absent', 'webexmeetings')
            ), ';');
        }
        
        fclose($output);
        exit;
    }
}

// Page setup
$PAGE->set_url('/mod/webexmeetings/attendance.php', array('id' => $cm->id));
$PAGE->set_title(format_string($webexmeeting->name) . ' - ' . get_string('attendancereport', 'webexmeetings'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('attendancereport', 'webexmeetings') . ': ' . format_string($webexmeeting->name));

// Meeting schedule info
echo '<div class="card mb-3">';
echo '<div class="card-body">';
echo '<p><strong>' . get_string('starttime', 'webexmeetings') . ':</strong> ' .
     userdate($webexmeeting->start_time, get_string('strftimedaydatetime', 'langconfig')) . '</p>';
echo '<p><strong>' . get_string('endtime', 'webexmeetings') . ':</strong> ' .
     userdate($webexmeeting->end_time, get_string('strftimedaydatetime', 'langconfig')) . '</p>';
if ($webexmeeting->last_sync) {
    echo '<p><strong>' . get_string('lastsync', 'webexmeetings') . ':</strong> ' .
         userdate($webexmeeting->last_sync, get_string('strftimedaydatetime', 'langconfig')) . 
         ' <span class="badge badge-' . ($webexmeeting->sync_status === 'success' ? 'success' : 'warning') . '">' .
         $webexmeeting->sync_status . '</span></p>';
}
echo '</div>';
echo '</div>';

// Summary stats
$total_enrolled = count($enrolled);
$total_present = count($attendance);
$total_absent = $total_enrolled - $total_present;
$attendance_percent = $total_enrolled > 0 ? round(($total_present / $total_enrolled) * 100, 1) : 0;

echo '<div class="row mb-3">';
echo '<div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center">';
echo '<h3>' . $total_enrolled . '</h3><p>' . get_string('enrolled', 'webexmeetings') . '</p>';
echo '</div></div></div>';
echo '<div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center">';
echo '<h3>' . $total_present . '</h3><p>' . get_string('present', 'webexmeetings') . '</p>';
echo '</div></div></div>';
echo '<div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center">';
echo '<h3>' . $total_absent . '</h3><p>' . get_string('absent', 'webexmeetings') . '</p>';
echo '</div></div></div>';
echo '<div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center">';
echo '<h3>' . $attendance_percent . '%</h3><p>' . get_string('attendancerate', 'webexmeetings') . '</p>';
echo '</div></div></div>';
echo '</div>';

// Attendance table
echo '<table class="table table-striped table-hover">';
echo '<thead class="thead-dark"><tr>';
echo '<th>' . get_string('name') . '</th>';
echo '<th>' . get_string('email') . '</th>';
echo '<th>' . get_string('status') . '</th>';
echo '<th>' . get_string('jointime', 'webexmeetings') . '</th>';
echo '<th>' . get_string('leavetime', 'webexmeetings') . '</th>';
echo '<th>' . get_string('duration', 'webexmeetings') . '</th>';
echo '<th>' . get_string('sessions', 'webexmeetings') . '</th>';
echo '<th>' . get_string('details') . '</th>';
echo '</tr></thead>';
echo '<tbody>';

foreach ($enrolled as $user) {
    $att = isset($attendance[$user->id]) ? $attendance[$user->id] : null;
    
    echo '<tr>';
    echo '<td>' . fullname($user) . '</td>';
    echo '<td>' . $user->email . '</td>';
    
    if ($att) {
        echo '<td><span class="badge badge-success">' . get_string('present', 'webexmeetings') . '</span></td>';
        echo '<td>' . ($att->join_time ? userdate($att->join_time, '%H:%M:%S %d/%m/%Y') : '-') . '</td>';
        echo '<td>' . ($att->leave_time ? userdate($att->leave_time, '%H:%M:%S %d/%m/%Y') : '-') . '</td>';
        echo '<td>' . ($att->duration ? format_time($att->duration) : '-') . '</td>';
        echo '<td>' . ($att->session_count ?? 0) . '</td>';
        
        // Session details
        $sessions = webexmeetings_get_user_sessions($webexmeeting->id, $user->id);
        if (!empty($sessions)) {
            $session_details = '';
            foreach ($sessions as $session) {
                $session_details .= userdate($session->join_time, '%H:%M:%S') . ' - ' . 
                    ($session->leave_time ? userdate($session->leave_time, '%H:%M:%S') : '...') . 
                    ($session->duration ? ' (' . format_time($session->duration) . ')' : '') . "\n";
            }
            echo '<td><button class="btn btn-sm btn-outline-info" data-toggle="tooltip" title="' . 
                 htmlspecialchars($session_details) . '">' . get_string('viewsessions', 'webexmeetings') . '</button></td>';
        } else {
            echo '<td>-</td>';
        }
    } else {
        echo '<td><span class="badge badge-danger">' . get_string('absent', 'webexmeetings') . '</span></td>';
        echo '<td>-</td><td>-</td><td>-</td><td>0</td><td>-</td>';
    }
    
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// Export buttons
if (has_capability('mod/webexmeetings:exportattendance', $context)) {
    echo '<div class="mt-3">';
    $csvurl = new moodle_url('/mod/webexmeetings/attendance.php', array('id' => $cm->id, 'export' => 'csv'));
    echo '<a href="' . $csvurl->out() . '" class="btn btn-outline-secondary mr-2">' .
         '<i class="fa fa-file-text"></i> ' . get_string('exportcsv', 'webexmeetings') . '</a>';
    echo '</div>';
}

// Back button
echo '<div class="mt-3">';
$backurl = new moodle_url('/mod/webexmeetings/view.php', array('id' => $cm->id));
echo '<a href="' . $backurl->out() . '" class="btn btn-secondary">' . get_string('back') . '</a>';
echo '</div>';

echo $OUTPUT->footer();
