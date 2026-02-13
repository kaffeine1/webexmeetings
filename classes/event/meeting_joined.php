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
 * Meeting joined event
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexmeetings\event;

defined('MOODLE_INTERNAL') || die();

class meeting_joined extends \core\event\base {
    protected function init() {
        $this->data['objecttable'] = 'webexmeetings';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_name() {
        return get_string('eventmeetingjoined', 'mod_webexmeetings');
    }

    public function get_description() {
        return "The user with id '$this->userid' joined a Webex meeting with id '$this->objectid' " .
               "in the course with id '$this->courseid'.";
    }

    public function get_url() {
        return new \moodle_url('/mod/webexmeetings/view.php', array('id' => $this->contextinstanceid));
    }
}
