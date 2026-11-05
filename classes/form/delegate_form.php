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

namespace mod_webexmeetings\form;

use mod_webexmeetings\local\oauth;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Choose the Moodle user allowed to authorise the site-wide Webex connection.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delegate_form extends \moodleform {
    /** @return void */
    public function definition(): void {
        global $DB;
        $mform = $this->_form;
        $delegateid = (int)($this->_customdata['delegateid'] ?? 0);
        $options = [];
        if ($delegateid > 0 && ($user = $DB->get_record('user', ['id' => $delegateid]))) {
            $options[$delegateid] = fullname($user) . ' (' . $user->email . ')';
        }
        $mform->addElement('autocomplete', 'delegate_userid', get_string('oauthdelegateuser', 'mod_webexmeetings'),
            $options, ['ajax' => 'core_user/form_user_selector']);
        $mform->setType('delegate_userid', PARAM_INT);
        $mform->setDefault('delegate_userid', $delegateid ?: '');
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /** @param array $data Submitted data. @param array $files Uploaded files. @return array Validation errors. */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $userid = (int)($data['delegate_userid'] ?? 0);
        if ($userid !== 0 && !oauth::eligible_delegate($userid)) {
            $errors['delegate_userid'] = get_string('oauthinvaliddelegate', 'mod_webexmeetings');
        }
        return $errors;
    }
}
