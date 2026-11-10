<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings\local;

defined('MOODLE_INTERNAL') || die();

/** Identity supplied by the authenticated Moodle session, never by URL parameters. */
final class participant_identity {
    /** Return an unambiguous account identity suitable for a personal Webex link. */
    public static function details(\stdClass $user, int $meetingid = 0): array {
        $email = trim((string)($user->email ?? ''));
        if (empty($user->id) || isguestuser($user) || !empty($user->deleted) || !empty($user->suspended) ||
                !validate_email($email) || preg_match('/@guest\.webex\.localhost$/i', $email)) {
            throw new \moodle_exception('participantidentityunavailable', 'mod_webexmeetings');
        }
        $matched = attendance_mapping::match_email($email, $meetingid);
        if (!$matched || (int)$matched->id !== (int)$user->id) {
            throw new \moodle_exception('participantidentityunavailable', 'mod_webexmeetings');
        }
        $name = \core_text::substr(trim(strip_tags(fullname($user))), 0, 128);
        if ($name === '') {
            throw new \moodle_exception('participantidentityunavailable', 'mod_webexmeetings');
        }
        return ['email' => $email, 'displayName' => $name];
    }
}
