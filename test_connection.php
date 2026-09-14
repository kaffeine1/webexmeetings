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
 * Test connection to Webex API
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$PAGE->set_context($context);
$PAGE->set_url('/mod/webexmeetings/test_connection.php');
$PAGE->set_title(get_string('testconnection', 'webexmeetings'));
$PAGE->set_heading(get_string('pluginname', 'webexmeetings'));
$PAGE->set_pagelayout('admin');

$runtest = optional_param('runtest', false, PARAM_BOOL);
if ($runtest) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('oauthpostrequired', 'mod_webexmeetings');
    }
    require_sesskey();
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testconnection', 'webexmeetings'));

if ($runtest) {
    try {
        $webexapi = new \mod_webexmeetings\external\webex_api();
        $webexapi->test_connection();
        echo $OUTPUT->notification(get_string('connectionsuccessful', 'webexmeetings'),
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        echo $OUTPUT->notification(
            get_string('oauthtestfailed', 'webexmeetings'),
            \core\output\notification::NOTIFY_ERROR
        );
    }
}
echo $OUTPUT->single_button(new moodle_url('/mod/webexmeetings/test_connection.php', ['runtest' => 1]),
    get_string('testconnection', 'webexmeetings'), 'post');

$settingsurl = new moodle_url('/admin/settings.php', array('section' => 'modsettingwebexmeetings'));
echo $OUTPUT->continue_button($settingsurl);

echo $OUTPUT->footer();
