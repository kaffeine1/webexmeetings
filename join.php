<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('webexmeetings', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$meeting = $DB->get_record('webexmeetings', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexmeetings:view', $context);
\mod_webexmeetings\local\meeting_access::require_open($meeting, (int)$cm->id);
$action = new moodle_url('/mod/webexmeetings/join.php', ['id' => $cm->id]);
$PAGE->set_url($action);
$PAGE->set_context($context);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
\mod_webexmeetings\local\recording_disclaimer::confirm($meeting, $context, $action);
if (get_config('mod_webexmeetings', 'personalized_join')) {
    $identity = \mod_webexmeetings\local\participant_identity::details($USER, (int)$meeting->id);
    try {
        $api = new \mod_webexmeetings\external\webex_api();
        $link = $api->get_join_link((string)$meeting->meeting_id, $identity['email'], $identity['displayName']);
    } catch (\Exception $e) {
        // Do not silently replace a personal entry with an anonymous or organiser link.
        throw new moodle_exception('personaljoinunavailable', 'mod_webexmeetings');
    }
} else {
    $link = (string)$meeting->join_url;
}
if (str_contains($link, '\\')) {
    throw new moodle_exception('nojoinurl', 'mod_webexmeetings');
}
$url = new moodle_url($link);
if ($url->get_scheme() !== 'https' || !preg_match('/(^|\.)webex\.com$/i', (string)$url->get_host())) {
    throw new moodle_exception('nojoinurl', 'mod_webexmeetings');
}
\core\session\manager::write_close();
header('Location: ' . $url->out(false), true, 303);
exit;
