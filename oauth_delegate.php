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
 * Administrator-only selection of the Webex OAuth delegate.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_webexmeetings\form\delegate_form;
use mod_webexmeetings\local\oauth;

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(oauth::delegate_url());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('oauthdelegateuser', 'mod_webexmeetings'));
$PAGE->set_heading(get_string('pluginname', 'mod_webexmeetings'));

$delegateid = (int)(oauth::configuration()->oauth_delegate_userid ?? 0);
$form = new delegate_form(null, ['delegateid' => $delegateid]);
if ($form->is_cancelled()) {
    redirect(oauth::management_url());
}
if ($data = $form->get_data()) {
    try {
        (new oauth())->set_delegate_userid((int)($data->delegate_userid ?? 0));
        redirect(oauth::management_url(), get_string('oauthdelegatesaved', 'mod_webexmeetings'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $message = $e->module === 'mod_webexmeetings' ? $e->getMessage() :
            get_string('oauthsavefailed', 'mod_webexmeetings');
        redirect(oauth::delegate_url(), $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('oauthdelegateuser', 'mod_webexmeetings'));
echo $OUTPUT->notification(get_string('oauthdelegatewarning', 'mod_webexmeetings'),
    \core\output\notification::NOTIFY_INFO);
$form->display();
echo $OUTPUT->footer();
