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
 * Display information about all Webex meetings in a course
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // Course ID

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);

$PAGE->set_url('/mod/webexmeetings/index.php', array('id' => $id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

// Trigger event
$event = \mod_webexmeetings\event\course_module_instance_list_viewed::create(array(
    'context' => context_course::instance($course->id)
));
$event->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'webexmeetings'));

if (!$webexmeetings = get_all_instances_in_course('webexmeetings', $course)) {
    notice(get_string('nomeetings', 'webexmeetings'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$table = new html_table();
$table->attributes['class'] = 'table table-striped generaltable';
$table->head = array(
    get_string('name'),
    get_string('starttime', 'webexmeetings'),
    get_string('endtime', 'webexmeetings'),
    get_string('status')
);

$now = time();

foreach ($webexmeetings as $meeting) {
    $link = html_writer::link(
        new moodle_url('/mod/webexmeetings/view.php', array('id' => $meeting->coursemodule)),
        format_string($meeting->name)
    );
    
    if ($now < $meeting->start_time) {
        $status = '<span class="badge badge-info">' . get_string('meetingnotstarted', 'webexmeetings') . '</span>';
    } else if ($now >= $meeting->start_time && $now <= $meeting->end_time) {
        $status = '<span class="badge badge-success">' . get_string('meetinginprogress', 'webexmeetings') . '</span>';
    } else {
        $status = '<span class="badge badge-secondary">' . get_string('meetingended', 'webexmeetings') . '</span>';
    }
    
    $table->data[] = array(
        $link,
        userdate($meeting->start_time, get_string('strftimedaydatetime', 'langconfig')),
        userdate($meeting->end_time, get_string('strftimedaydatetime', 'langconfig')),
        $status
    );
}

echo html_writer::table($table);

echo $OUTPUT->footer();
