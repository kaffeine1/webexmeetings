<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('webexmeetings', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$meeting = $DB->get_record('webexmeetings', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexmeetings:view', $context);
require_capability('mod/webexmeetings:managemeeting', $context);
if (!\mod_webexmeetings\local\meeting_host::can_start($context)) {
    throw new moodle_exception('startmeetingnotallowed', 'mod_webexmeetings');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('oauthpostrequired', 'mod_webexmeetings');
}
require_sesskey();
\mod_webexmeetings\local\meeting_access::require_open($meeting, (int)$cm->id);

$PAGE->set_url('/mod/webexmeetings/start.php', ['id' => $cm->id]);
$PAGE->set_context($context);
\mod_webexmeetings\local\recording_disclaimer::confirm($meeting, $context,
    new moodle_url('/mod/webexmeetings/start.php', ['id' => $cm->id]));
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
try {
    $api = new \mod_webexmeetings\external\webex_api();
    $link = $api->get_start_link((string)$meeting->meeting_id);
} catch (Throwable $error) {
    // Do not disclose returned URLs, credentials or upstream exception details.
    throw new moodle_exception('startmeetingunavailable', 'mod_webexmeetings');
}
\core\session\manager::write_close();
header('Location: ' . $link, true, 303);
exit;
