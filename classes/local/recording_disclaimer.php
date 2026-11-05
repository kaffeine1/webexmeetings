<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Recording notice policy, independent from Webex recording controls.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recording_disclaimer {
    /** Sailfor assignment roles, plus Moodle's standard teaching/management roles. */
    private const ASSIGNEES = ['teacher', 'editingteacher', 'tutor', 'presenter', 'trainer',
        'scientific', 'moderator', 'staff', 'expert', 'manager', 'faddymanager'];

    public static function required(\stdClass $meeting, \context_module $context, int $userid = 0): bool {
        global $USER, $DB;
        $userid = $userid ?: (int)$USER->id;
        if (!$userid || isguestuser($userid)) {
            return false;
        }
        $participant = false;
        $assignee = is_siteadmin($userid);
        $assignments = get_user_roles($context, $userid, true);
        $roleids = array_unique(array_column($assignments, 'roleid'));
        $roles = $roleids ? $DB->get_records_list('role', 'id', $roleids, '', 'id,shortname,archetype') : [];
        foreach ($roles as $role) {
            $participant = $participant || $role->shortname === 'student' || $role->archetype === 'student';
            $assignee = $assignee || in_array($role->shortname, self::ASSIGNEES, true) ||
                in_array($role->archetype, ['teacher', 'editingteacher', 'manager'], true);
        }
        return (!empty($meeting->disclaimer_participants) && $participant) ||
            (!empty($meeting->disclaimer_assignees) && $assignee);
    }

    /** Fill defaults only on creation; editing must not reset an existing choice. */
    public static function apply_defaults(\stdClass $data): void {
        foreach (['disclaimer_participants', 'disclaimer_assignees'] as $field) {
            if (!isset($data->{$field})) {
                $data->{$field} = (int)(bool)get_config('mod_webexmeetings', 'default_' . $field);
            }
        }
    }

    /** Render a core Moodle confirmation dialog, with an accessible no-JS form fallback. */
    public static function confirm(\stdClass $meeting, \context_module $context, \moodle_url $action): void {
        global $PAGE, $OUTPUT;
        if (!self::required($meeting, $context)) {
            return;
        }
        $accepted = optional_param('recordingaccepted', 0, PARAM_BOOL);
        if ($accepted) {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new \moodle_exception('oauthpostrequired', 'mod_webexmeetings');
            }
            require_sesskey();
            return;
        }
        $title = get_string('recordingdisclaimertitle', 'mod_webexmeetings');
        $text = trim((string)get_config('mod_webexmeetings', 'recording_disclaimer_text'));
        $text = $text !== '' ? $text : get_string('recordingdisclaimerdefault', 'mod_webexmeetings');
        $yes = get_string('recordingdisclaimeraccept', 'mod_webexmeetings');
        $no = get_string('cancel');
        $back = new \moodle_url('/mod/webexmeetings/view.php', ['id' => $context->instanceid]);
        $PAGE->set_title($title);
        echo $OUTPUT->header();
        echo $OUTPUT->heading($title);
        echo \html_writer::div(s($text), 'alert alert-info');
        echo \html_writer::start_tag('form', ['method' => 'post', 'action' => $action->out(false),
            'id' => 'webex-recording-confirmation']);
        echo \html_writer::input_hidden_params($action);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'recordingaccepted', 'value' => 1]);
        echo \html_writer::tag('button', s($yes), ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo ' ' . \html_writer::link($back, s($no), ['class' => 'btn btn-secondary']);
        echo \html_writer::end_tag('form');
        $args = json_encode([$title, s($text), $yes, $no, $back->out(false)], JSON_HEX_TAG | JSON_HEX_AMP |
            JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $PAGE->requires->js_init_code("require(['core/notification'], function(Notification) {
            var args = $args;
            Notification.confirm(args[0], args[1], args[2], args[3], function() {
                document.getElementById('webex-recording-confirmation').submit();
            }, function() { window.location.assign(args[4]); });
        });");
        echo $OUTPUT->footer();
        exit;
    }
}
