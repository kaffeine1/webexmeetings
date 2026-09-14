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

/**
 * Receive Webex's authorisation code. No credentials or codes are rendered.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
require_once(__DIR__ . '/../../config.php');

use mod_webexmeetings\local\oauth;

$PAGE->set_url(oauth::redirect_uri());
// Do not preserve a callback containing an authorisation code as the login destination.
require_login(null, false, null, false);
require_capability('moodle/site:config', context_system::instance());

try {
    (new oauth())->complete(optional_param('code', '', PARAM_RAW), optional_param('state', '', PARAM_RAW),
        optional_param('error', '', PARAM_RAW));
} catch (Throwable $e) {
    $message = $e instanceof moodle_exception && $e->module === 'mod_webexmeetings' ? $e->getMessage() :
        get_string('oauthrequestfailed', 'mod_webexmeetings');
    redirect(oauth::management_url(), $message, null, \core\output\notification::NOTIFY_ERROR);
}
redirect(oauth::management_url(), get_string('oauthconnected', 'mod_webexmeetings'),
    null, \core\output\notification::NOTIFY_SUCCESS);
