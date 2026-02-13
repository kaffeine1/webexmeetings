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
 * Attendance synced event
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexmeetings\event;

defined('MOODLE_INTERNAL') || die();

class attendance_synced extends \core\event\base {
    protected function init() {
        $this->data['objecttable'] = 'webexmeetings';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return get_string('eventattendancesynced', 'mod_webexmeetings');
    }

    public function get_description() {
        $count = $this->other['count'] ?? 0;
        return "Attendance data was synced for Webex meeting with id '$this->objectid' " .
               "in the course with id '$this->courseid'. $count participants synced.";
    }

    public function get_url() {
        return new \moodle_url('/mod/webexmeetings/view.php', array('id' => $this->contextinstanceid));
    }
}
