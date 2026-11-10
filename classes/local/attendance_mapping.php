<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Explicit participant associations scoped to a Moodle activity.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attendance_mapping {
    /** Normalize participant/device intervals without inventing entry times. */
    public static function sessions(\stdClass $record): array {
        $sources = !empty($record->devices) && is_array($record->devices) ? $record->devices : [$record];
        $sessions = [];
        foreach ($sources as $source) {
            $join = !empty($source->joinedTime) ? strtotime($source->joinedTime) : false;
            if (!$join || $join < 1) {
                continue;
            }
            $leave = !empty($source->leftTime) ? strtotime($source->leftTime) : false;
            $leave = $leave && $leave >= $join ? $leave : 0;
            $leave = max($leave, (int)($sessions[$join]->leave_time ?? 0));
            $sessions[$join] = (object)['join_time' => $join, 'leave_time' => $leave,
                'duration' => $leave ? $leave - $join : 0];
        }
        return array_values($sessions);
    }

    /** Return only records for the same participant in this activity, never by display name. */
    private static function identity_rows(int $meetingid, string $id, string $email): array {
        global $DB;
        if ($id !== '') {
            return $DB->get_records('webexmeetings_unmatched', ['meetingid' => $meetingid, 'webex_id' => $id]);
        }
        if ($email !== '') {
            return $DB->get_records('webexmeetings_unmatched',
                ['meetingid' => $meetingid, 'webex_id' => '', 'webex_email' => $email]);
        }
        return [];
    }

    /** A manually selected target must still be an active participant of the course. */
    private static function eligible(int $meetingid, int $userid): bool {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user) {
            return false;
        }
        $cm = get_coursemodule_from_instance('webexmeetings', $meetingid, 0, false, MUST_EXIST);
        return is_enrolled(\context_module::instance($cm->id), $user, '', true);
    }

    /** Resolve a saved mapping first; otherwise require an unambiguous real email. */
    public static function match(\stdClass $record, int $meetingid): ?\stdClass {
        global $DB;
        $email = trim((string)($record->email ?? ''));
        $rows = self::identity_rows($meetingid, (string)($record->id ?? ''), $email);
        $targets = [];
        foreach ($rows as $row) {
            if ($row->mapped_userid) {
                $targets[(int)$row->mapped_userid] = true;
            }
        }
        if ($targets) {
            $userid = (int)array_key_first($targets);
            return count($targets) === 1 && self::eligible($meetingid, $userid) ?
                $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST) : null;
        }
        return self::match_email($email, $meetingid, empty($record->host));
    }

    /** Resolve shared emails only for a unique course member whose enrolment is active. */
    public static function match_email(string $email, int $meetingid = 0, bool $allowshared = true): ?\stdClass {
        global $DB;
        $email = trim($email);
        if ($email === '' || preg_match('/@guest\.webex\.localhost$/i', $email)) {
            return null;
        }
        $users = $DB->get_records('user', ['email' => $email, 'deleted' => 0, 'suspended' => 0], '', '*', 0, 2);
        if (count($users) < 2) {
            return $users ? reset($users) : null;
        }
        if ($meetingid < 1 || !$allowshared) {
            return null;
        }
        $cm = get_coursemodule_from_instance('webexmeetings', $meetingid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        // Count inactive enrolments too: expiry must not transfer a participant's identity.
        $context = \context_module::instance($cm->id);
        [$enrolledsql, $params] = get_enrolled_sql($context);
        $params['webexparticipantemail'] = $email;
        $users = $DB->get_records_sql("SELECT u.* FROM {user} u
                WHERE u.email = :webexparticipantemail AND u.deleted = 0 AND u.suspended = 0
                  AND u.id IN ($enrolledsql)", $params, 0, 2);
        $user = count($users) === 1 ? reset($users) : null;
        return $user && is_enrolled($context, $user, '', true) ? $user : null;
    }

    /** Preserve every entry interval, including multiple guests with no email. */
    public static function remember(\stdClass $record, int $meetingid, array $sessions): void {
        self::locked($meetingid, static function() use ($record, $meetingid, $sessions): void {
            self::remember_rows($record, $meetingid, $sessions);
        });
    }

    private static function remember_rows(\stdClass $record, int $meetingid, array $sessions): void {
        global $DB;
        $email = trim((string)($record->email ?? ''));
        $id = (string)($record->id ?? '');
        // With neither an ID nor email, no stable identity can safely be persisted or mapped.
        if ($id === '' && $email === '') {
            throw new \coding_exception('Webex participant has neither an ID nor an email');
        }
        foreach ($sessions ?: [(object)['join_time' => 0, 'leave_time' => 0, 'duration' => 0]] as $session) {
            $conditions = ['meetingid' => $meetingid, 'join_time' => $session->join_time];
            $conditions[$id !== '' ? 'webex_id' : 'webex_email'] = $id !== '' ? $id : $email;
            if ($id === '') {
                $conditions['webex_id'] = '';
            }
            $existing = $DB->get_records('webexmeetings_unmatched', $conditions, 'id ASC', '*', 0, 1);
            $existing = $existing ? reset($existing) : null;
            $row = (object)['meetingid' => $meetingid, 'webex_email' => $email,
                'webex_displayname' => (string)($record->displayName ?? ''), 'webex_id' => $id,
                'join_time' => $session->join_time, 'leave_time' => $session->leave_time,
                'duration' => $session->duration];
            if ($existing) {
                $row->id = $existing->id;
                if ($row->leave_time < $existing->leave_time) {
                    $row->leave_time = $existing->leave_time;
                    $row->duration = $existing->duration;
                }
                $DB->update_record('webexmeetings_unmatched', $row);
            } else {
                $row->timecreated = time();
                $row->mapped_userid = 0;
                $DB->insert_record('webexmeetings_unmatched', $row);
            }
        }
    }

    /** Save a validated association and import all its stored intervals atomically. */
    public static function map(int $meetingid, int $rowid, int $userid): void {
        self::locked($meetingid, static function() use ($meetingid, $rowid, $userid): void {
            self::map_rows($meetingid, $rowid, $userid);
        });
    }

    /** Serialize manual mapping and imports of the same activity. */
    private static function locked(int $meetingid, callable $callback): void {
        $factory = \core\lock\lock_config::get_lock_factory('mod_webexmeetings_attendance');
        $lock = $factory->get_lock('meeting_' . $meetingid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $callback();
        } finally {
            $lock->release();
        }
    }

    /** Shared import for cron, manual sync and explicit mapping. Never shrink a closed interval. */
    public static function store_sessions(int $meetingid, int $userid, array $sessions): void {
        self::locked($meetingid, static function() use ($meetingid, $userid, $sessions): void {
            self::store_rows($meetingid, $userid, $sessions);
        });
    }

    private static function store_rows(int $meetingid, int $userid, array $sessions): void {
        global $DB, $CFG;
        $tx = $DB->start_delegated_transaction();
        foreach ($sessions as $row) {
            if ($row->join_time < 1) {
                continue;
            }
            $conditions = ['meetingid' => $meetingid, 'userid' => $userid, 'join_time' => $row->join_time];
            $existing = $DB->get_record('webexmeetings_sessions', $conditions);
            $leave = max((int)$row->leave_time, (int)($existing->leave_time ?? 0));
            $session = (object)($conditions + ['sessionid' => $meetingid . '_' . $userid . '_' . $row->join_time,
                'leave_time' => $leave, 'duration' => $leave ? max(0, $leave - $row->join_time) : 0]);
            if ($existing) {
                $session->id = $existing->id;
                $DB->update_record('webexmeetings_sessions', $session);
            } else {
                $DB->insert_record('webexmeetings_sessions', $session);
            }
        }
        require_once($CFG->dirroot . '/mod/webexmeetings/lib.php');
        webexmeetings_update_attendance_summary($meetingid, $userid);
        $tx->allow_commit();
    }

    private static function map_rows(int $meetingid, int $rowid, int $userid): void {
        global $DB;
        $selected = $DB->get_record('webexmeetings_unmatched', ['id' => $rowid, 'meetingid' => $meetingid], '*', MUST_EXIST);
        if (!self::eligible($meetingid, $userid)) {
            throw new \moodle_exception('invaliduser', 'error');
        }
        $rows = self::identity_rows($meetingid, (string)$selected->webex_id, (string)$selected->webex_email);
        if (!$rows) {
            throw new \coding_exception('No stable participant identity');
        }
        $tx = $DB->start_delegated_transaction();
        foreach ($rows as $row) {
            if ($row->mapped_userid && (int)$row->mapped_userid !== $userid) {
                throw new \coding_exception('Participant is already mapped to another user');
            }
            $DB->set_field('webexmeetings_unmatched', 'mapped_userid', $userid, ['id' => $row->id]);
        }
        self::store_rows($meetingid, $userid, $rows);
        $tx->allow_commit();
    }
}
