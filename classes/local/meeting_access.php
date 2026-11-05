<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Keep local entry controls consistent with the scheduled activity window.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class meeting_access {
    /** The first occurrence end is not the end of a recurring Webex series. */
    public static function has_ended(\stdClass $meeting, ?int $now = null): bool {
        $end = (int)($meeting->end_time ?? 0);
        return $end <= 0 || (empty($meeting->recurring) && ($now ?? time()) >= $end);
    }

    public static function status(\stdClass $meeting, ?int $now = null): string {
        $now = $now ?? time();
        if (self::has_ended($meeting, $now)) {
            return 'meetingscheduleended';
        }
        if ($now < (int)$meeting->start_time) {
            return 'meetingnotstarted';
        }
        if (!empty($meeting->recurring)) {
            return 'recurringmeeting';
        }
        return 'meetinginprogress';
    }

    /** Enforce the same rule on direct join and organiser requests. */
    public static function require_open(\stdClass $meeting, int $cmid): void {
        if (self::has_ended($meeting)) {
            throw new \moodle_exception('meetingaccessended', 'mod_webexmeetings',
                new \moodle_url('/mod/webexmeetings/view.php', ['id' => $cmid]));
        }
    }
}
