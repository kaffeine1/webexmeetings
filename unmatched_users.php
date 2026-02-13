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
 * Manage unmatched Webex users
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/webexmeetings/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID
$mapid = optional_param('mapid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('webexmeetings', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$webexmeeting = $DB->get_record('webexmeetings', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexmeetings:syncattendance', $context);

$returnurl = new moodle_url('/mod/webexmeetings/unmatched_users.php', array('id' => $cm->id));

// Handle mapping
if ($mapid && $userid && confirm_sesskey()) {
    $unmatched = $DB->get_record('webexmeetings_unmatched', array('id' => $mapid), '*', MUST_EXIST);
    $unmatched->mapped_userid = $userid;
    $DB->update_record('webexmeetings_unmatched', $unmatched);
    
    // Create attendance record for the mapped user
    if ($unmatched->join_time) {
        $sessionid = $webexmeeting->meeting_id . '_' . $userid . '_' . $unmatched->join_time;
        
        $existing = $DB->get_record('webexmeetings_sessions', array(
            'meetingid' => $webexmeeting->id,
            'userid' => $userid,
            'join_time' => $unmatched->join_time
        ));
        
        if (!$existing) {
            $session = new stdClass();
            $session->meetingid = $webexmeeting->id;
            $session->userid = $userid;
            $session->sessionid = $sessionid;
            $session->join_time = $unmatched->join_time;
            $session->leave_time = $unmatched->leave_time;
            $session->duration = $unmatched->duration;
            
            $DB->insert_record('webexmeetings_sessions', $session);
        }
        
        webexmeetings_update_attendance_summary($webexmeeting->id, $userid);
    }
    
    redirect($returnurl, get_string('usermapped', 'webexmeetings'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url('/mod/webexmeetings/unmatched_users.php', array('id' => $cm->id));
$PAGE->set_title(get_string('unmatcheduserspage', 'webexmeetings'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unmatcheduserspage', 'webexmeetings'));

// Get unmatched users
$unmatched = $DB->get_records('webexmeetings_unmatched', array(
    'meetingid' => $webexmeeting->id,
    'mapped_userid' => 0
));

if (empty($unmatched)) {
    echo $OUTPUT->notification(get_string('nounmatchedusers', 'webexmeetings'), \core\output\notification::NOTIFY_INFO);
} else {
    echo '<p>' . get_string('unmatchedusersdesc', 'webexmeetings') . '</p>';
    
    // Get all Moodle users for select
    $enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');
    $options = array();
    foreach ($enrolled_users as $u) {
        $options[$u->id] = fullname($u) . ' (' . $u->email . ')';
    }
    
    $table = new html_table();
    $table->head = array(
        get_string('email'),
        get_string('name'),
        get_string('jointime', 'webexmeetings'),
        get_string('duration', 'webexmeetings'),
        get_string('maptouser', 'webexmeetings')
    );
    $table->attributes['class'] = 'table table-striped';
    
    foreach ($unmatched as $u) {
        $select = html_writer::select($options, 'userid', '', array('' => get_string('selectuser', 'webexmeetings')));
        $form = html_writer::start_tag('form', array('method' => 'post', 'action' => $returnurl));
        $form .= html_writer::input_hidden_params($returnurl);
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'mapid', 'value' => $u->id));
        $form .= $select;
        $form .= ' ';
        $form .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('map', 'webexmeetings'),
            'class' => 'btn btn-sm btn-primary'
        ));
        $form .= html_writer::end_tag('form');
        
        $table->data[] = array(
            $u->webex_email,
            $u->webex_displayname,
            $u->join_time ? userdate($u->join_time, '%H:%M:%S %d/%m/%Y') : '-',
            $u->duration ? format_time($u->duration) : '-',
            $form
        );
    }
    
    echo html_writer::table($table);
}

// Back button
$backurl = new moodle_url('/mod/webexmeetings/view.php', array('id' => $cm->id));
echo '<div class="mt-3">';
echo '<a href="' . $backurl->out() . '" class="btn btn-secondary">' . get_string('back') . '</a>';
echo '</div>';

echo $OUTPUT->footer();
