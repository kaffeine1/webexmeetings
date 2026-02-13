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
 * Form for creating/editing Webex meeting instances
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 */
class mod_webexmeetings_mod_form extends moodleform_mod {
    
    /**
     * Define the form
     */
    public function definition() {
        global $CFG;
        
        $mform = $this->_form;
        
        // General section
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        // Name
        $mform->addElement('text', 'name', get_string('meetingname', 'webexmeetings'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        
        // Description
        $this->standard_intro_elements();
        
        // Meeting schedule section
        $mform->addElement('header', 'scheduling', get_string('scheduling', 'webexmeetings'));
        
        // Start time
        $mform->addElement('date_time_selector', 'start_time', get_string('starttime', 'webexmeetings'));
        $mform->setDefault('start_time', time() + 3600); // Default to 1 hour from now
        
        // End time
        $mform->addElement('date_time_selector', 'end_time', get_string('endtime', 'webexmeetings'));
        $mform->setDefault('end_time', time() + 7200); // Default to 2 hours from now
        
        // Meeting password (optional)
        $mform->addElement('text', 'meeting_password', get_string('meetingpassword', 'webexmeetings'), array('size' => '20'));
        $mform->setType('meeting_password', PARAM_RAW);
        $mform->addHelpButton('meeting_password', 'meetingpassword', 'webexmeetings');
        
        // Recurring meeting
        $mform->addElement('advcheckbox', 'recurring', get_string('recurringmeeting', 'webexmeetings'));
        $mform->setDefault('recurring', 0);
        
        // Attendance tracking section
        $mform->addElement('header', 'attendancesection', get_string('attendancesection', 'webexmeetings'));
        
        // Track attendance
        $mform->addElement('advcheckbox', 'track_attendance', get_string('trackattendance', 'webexmeetings'));
        $mform->setDefault('track_attendance', get_config('mod_webexmeetings', 'default_track_attendance'));
        $mform->addHelpButton('track_attendance', 'trackattendance', 'webexmeetings');
        
        // Minimum duration for attendance
        $options = array(
            0 => get_string('nominimum', 'webexmeetings'),
            5 => '5 ' . get_string('minutes'),
            10 => '10 ' . get_string('minutes'),
            15 => '15 ' . get_string('minutes'),
            30 => '30 ' . get_string('minutes'),
            60 => '60 ' . get_string('minutes'),
        );
        $mform->addElement('select', 'min_duration', get_string('minduration', 'webexmeetings'), $options);
        $mform->setDefault('min_duration', get_config('mod_webexmeetings', 'default_min_duration'));
        $mform->addHelpButton('min_duration', 'minduration', 'webexmeetings');
        $mform->disabledIf('min_duration', 'track_attendance');
        
        // Standard course module elements
        $this->standard_coursemodule_elements();
        
        // Action buttons
        $this->add_action_buttons();
    }
    
    /**
     * Validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        // Validate times
        if ($data['end_time'] <= $data['start_time']) {
            $errors['end_time'] = get_string('endtimebeforestart', 'webexmeetings');
        }
        
        return $errors;
    }
}
