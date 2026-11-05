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
 * Administration of the site-wide Webex OAuth connection.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_webexmeetings\local\oauth;

require_login(null, false);
if (!oauth::can_connect()) {
    require_capability('moodle/site:config', context_system::instance());
}
$isadmin = has_capability('moodle/site:config', context_system::instance());
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(oauth::management_url());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('oauthmanage', 'mod_webexmeetings'));
$PAGE->set_heading(get_string('pluginname', 'mod_webexmeetings'));

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('oauthpostrequired', 'mod_webexmeetings');
    }
    require_sesskey();
    $oauth = new oauth();
    try {
        if ($action === 'connect') {
            $url = $oauth->begin();
        } else if ($action === 'disconnect') {
            require_capability('moodle/site:config', context_system::instance());
            $oauth->disconnect();
            redirect(oauth::management_url(), get_string('oauthdisconnected', 'mod_webexmeetings'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            throw new moodle_exception('oauthpostrequired', 'mod_webexmeetings');
        }
    } catch (moodle_exception $e) {
        $message = $e->module === 'mod_webexmeetings' ? $e->getMessage() :
            get_string('oauthrequestfailed', 'mod_webexmeetings');
        redirect(oauth::management_url(), $message, null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($url);
}

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingwebexmeetings']);
$config = oauth::configuration();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('oauthmanage', 'mod_webexmeetings'));
if ($isadmin) {
    echo html_writer::div(html_writer::link($settingsurl, get_string('generalsettings', 'admin'),
        ['class' => 'btn btn-secondary']), 'mb-3');
}
echo $OUTPUT->notification(get_string(oauth::status(), 'mod_webexmeetings'),
    \core\output\notification::NOTIFY_INFO);

$details = [
    [get_string('oauthredirecturi', 'mod_webexmeetings'), html_writer::tag('code', s(oauth::redirect_uri()->out(false)))],
    [get_string('oauthscopes', 'mod_webexmeetings'), s(oauth::scopes($config->oauth_scope_profile ?? 'host'))],
];
if (!empty($config->oauth_connected_at)) {
    $details[] = [get_string('oauthconnectedat', 'mod_webexmeetings'), userdate($config->oauth_connected_at)];
}
if ($isadmin && !empty($config->oauth_delegate_userid)) {
    $delegate = $DB->get_record('user', ['id' => (int)$config->oauth_delegate_userid]);
    if ($delegate) {
        $details[] = [get_string('oauthdelegateuser', 'mod_webexmeetings'),
            s(fullname($delegate) . ' (' . $delegate->email . ')')];
    }
}
echo html_writer::start_tag('dl', ['class' => 'row mx-0']);
foreach ($details as [$label, $value]) {
    echo html_writer::tag('dt', $label, ['class' => 'col-sm-4 px-0']);
    echo html_writer::tag('dd', $value, ['class' => 'col-sm-8 px-0 text-break']);
}
echo html_writer::end_tag('dl');

if ($isadmin && optional_param('confirmdisconnect', false, PARAM_BOOL)) {
    echo $OUTPUT->confirm(get_string('oauthdisconnectconfirm', 'mod_webexmeetings'),
        new single_button(new moodle_url(oauth::management_url(), ['action' => 'disconnect']),
            get_string('oauthdisconnect', 'mod_webexmeetings'), 'post'), oauth::management_url());
} else {
    echo $OUTPUT->single_button(new moodle_url(oauth::management_url(), ['action' => 'connect']),
        get_string('oauthconnect', 'mod_webexmeetings'), 'post');
    if ($isadmin) {
        echo $OUTPUT->single_button(oauth::delegate_url(),
            get_string('oauthdelegateuser', 'mod_webexmeetings'), 'get');
    }
    echo $OUTPUT->single_button(new moodle_url('/mod/webexmeetings/test_connection.php'),
        get_string('testconnection', 'mod_webexmeetings'), 'get');
    if ($isadmin && !empty($config->refresh_token)) {
        echo $OUTPUT->single_button(new moodle_url(oauth::management_url(), ['confirmdisconnect' => 1]),
            get_string('oauthdisconnect', 'mod_webexmeetings'), 'get');
    }
}
echo $OUTPUT->footer();
