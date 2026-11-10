<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use mod_webexmeetings\local\attendance_mapping;
use PHPUnit\Framework\Attributes\CoversClass;

/** Invoke the real import processor without authenticating or contacting Webex. */
final class attendance_import_stub extends \mod_webexmeetings\external\webex_api {
    public function __construct() {
    }

    public function import(array $records, int $meetingid): array {
        $method = new \ReflectionMethod(parent::class, 'process_attendance_records');
        return $method->invoke($this, $records, $meetingid);
    }
}

/**
 * Guest identity and interval regression tests using only synthetic participants.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(attendance_mapping::class)]
final class attendance_mapping_test extends \advanced_testcase {
    private function meeting(): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $course = $this->getDataGenerator()->create_course();
        $id = $DB->insert_record('webexmeetings', (object)['course' => $course->id, 'name' => 'Synthetic meeting']);
        add_course_module((object)['course' => $course->id,
            'module' => $DB->get_field('modules', 'id', ['name' => 'webexmeetings'], MUST_EXIST),
            'instance' => $id, 'section' => 0]);
        return $id;
    }

    private function enrol(int $meetingid): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $DB->get_field('webexmeetings', 'course', ['id' => $meetingid]);
        $this->getDataGenerator()->enrol_user($user->id, $course);
        return $user;
    }

    private function guest(string $id, string $email = ''): \stdClass {
        return (object)['id' => $id, 'email' => $email, 'displayName' => 'Same name',
            'joinedTime' => '2026-11-04T09:00:00Z', 'leftTime' => '2026-11-04T09:05:00Z'];
    }

    public function test_shared_email_uses_the_only_active_course_member_even_after_two_outsiders(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $email = 'shared@example.invalid';
        $this->getDataGenerator()->create_user(['email' => $email]);
        $this->getDataGenerator()->create_user(['email' => $email]);
        $member = $this->enrol($meeting);
        $DB->set_field('user', 'email', $email, ['id' => $member->id]);
        $record = $this->guest('shared-person', $email);
        $this->assertSame((int)$member->id, (int)attendance_mapping::match($record, $meeting)->id);
        $imported = (new attendance_import_stub())->import([$record], $meeting);
        $this->assertCount(1, $imported);
        $this->assertSame((int)$member->id, (int)$imported[0]->userid);
        $this->assertSame(0, $DB->count_records('webexmeetings_unmatched', ['meetingid' => $meeting]));
    }

    public function test_shared_email_with_two_active_course_members_remains_unmatched(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        foreach ([1, 2] as $i) {
            $member = $this->enrol($meeting);
            $DB->set_field('user', 'email', 'shared@example.invalid', ['id' => $member->id]);
        }
        $record = $this->guest('shared-person', 'shared@example.invalid');
        $this->assertNull(attendance_mapping::match($record, $meeting));
        $this->assertSame([], (new attendance_import_stub())->import([$record], $meeting));
        $this->assertSame(1, $DB->count_records('webexmeetings_unmatched', ['meetingid' => $meeting]));
        $this->assertSame(0, $DB->count_records('webexmeetings_sessions', ['meetingid' => $meeting]));
    }

    public function test_shared_email_does_not_change_identity_with_enrolment_status(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $member = $this->enrol($meeting);
        $other = $this->enrol($meeting);
        foreach ([$member, $other] as $user) {
            $DB->set_field('user', 'email', 'shared@example.invalid', ['id' => $user->id]);
        }
        foreach ([['timeend' => time() - 3600], ['timestart' => time() + 3600], ['status' => ENROL_USER_SUSPENDED]] as $change) {
            $enrolment = $DB->get_record('user_enrolments', ['userid' => $other->id], '*', MUST_EXIST);
            $enrolment->status = $change['status'] ?? ENROL_USER_ACTIVE;
            $enrolment->timestart = $change['timestart'] ?? 0;
            $enrolment->timeend = $change['timeend'] ?? 0;
            $DB->update_record('user_enrolments', $enrolment);
            $this->assertNull(attendance_mapping::match_email('shared@example.invalid', $meeting));
            $this->assertSame([], (new attendance_import_stub())->import(
                [$this->guest('shared-person', 'shared@example.invalid')], $meeting));
        }
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $enrolment->enrolid]);
        $this->assertNull(attendance_mapping::match_email('shared@example.invalid', $meeting));
        $this->assertSame(0, $DB->count_records('webexmeetings_sessions', ['meetingid' => $meeting]));
    }

    public function test_shared_email_requires_an_active_unique_course_member(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $member = $this->enrol($meeting);
        $this->getDataGenerator()->create_user(['email' => $member->email]);
        $DB->set_field('user_enrolments', 'timeend', time() - 3600, ['userid' => $member->id]);
        $this->assertNull(attendance_mapping::match_email($member->email, $meeting));
        $DB->set_field('user_enrolments', 'timeend', 0, ['userid' => $member->id]);
        $this->assertSame((int)$member->id, (int)attendance_mapping::match_email($member->email, $meeting)->id);
    }

    public function test_disabled_duplicate_enrolment_blocks_but_multiple_enrolments_of_one_user_do_not(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $member = $this->enrol($meeting);
        $other = $this->getDataGenerator()->create_user(['email' => $member->email]);
        $courseid = $DB->get_field('webexmeetings', 'course', ['id' => $meeting]);
        $plugin = enrol_get_plugin('self');
        $instanceid = $plugin->add_instance($DB->get_record('course', ['id' => $courseid]),
            ['status' => ENROL_INSTANCE_DISABLED]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $plugin->enrol_user($instance, $other->id);
        $this->assertNull(attendance_mapping::match_email($member->email, $meeting));
        $plugin->unenrol_user($instance, $other->id);
        $plugin->enrol_user($instance, $member->id);
        $this->assertSame((int)$member->id, (int)attendance_mapping::match_email($member->email, $meeting)->id);
    }

    public function test_shared_email_host_requires_explicit_mapping_but_unique_host_is_unchanged(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $host = $this->getDataGenerator()->create_user();
        $record = $this->guest('host', $host->email);
        $record->host = true;
        $this->assertSame((int)$host->id, (int)attendance_mapping::match($record, $meeting)->id);
        $member = $this->enrol($meeting);
        $DB->set_field('user', 'email', $host->email, ['id' => $member->id]);
        $this->assertNull(attendance_mapping::match($record, $meeting));
        attendance_mapping::remember($record, $meeting, attendance_mapping::sessions($record));
        $row = $DB->get_record('webexmeetings_unmatched', ['meetingid' => $meeting], '*', MUST_EXIST);
        attendance_mapping::map($meeting, $row->id, $member->id);
        $this->assertSame((int)$member->id, (int)attendance_mapping::match($record, $meeting)->id);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['userid' => $member->id]);
        $this->assertNull(attendance_mapping::match($record, $meeting));
    }

    public function test_shared_email_needs_a_valid_course_context_and_does_not_cross_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $othermeeting = $this->meeting();
        $member = $this->enrol($meeting);
        $other = $this->enrol($othermeeting);
        foreach ([$member, $other] as $user) {
            $DB->set_field('user', 'email', 'shared@example.invalid', ['id' => $user->id]);
        }
        $this->assertNull(attendance_mapping::match_email('shared@example.invalid'));
        $this->assertNull(attendance_mapping::match_email('shared@example.invalid', 999999));
        $this->assertSame((int)$member->id,
            (int)attendance_mapping::match_email('shared@example.invalid', $meeting)->id);
        $this->assertSame((int)$other->id,
            (int)attendance_mapping::match_email('shared@example.invalid', $othermeeting)->id);
    }

    public function test_hundreds_of_unique_emails_match_without_manual_mapping(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $course = $DB->get_field('webexmeetings', 'course', ['id' => $meeting]);
        $records = [];
        $expected = [];
        for ($i = 0; $i < 200; $i++) {
            $user = $this->getDataGenerator()->create_user(['email' => "participant{$i}@example.invalid"]);
            $this->getDataGenerator()->enrol_user($user->id, $course);
            $records[] = $this->guest('participant-' . $i, $user->email);
            $expected[] = $user->id;
        }
        $api = new attendance_import_stub();
        foreach ([1, 2] as $pass) {
            $imported = $api->import($records, $meeting);
            $this->assertSame($expected, array_column($imported, 'userid'));
            foreach ($imported as $participant) {
                attendance_mapping::store_sessions($meeting, $participant->userid, $participant->sessions);
            }
            $this->assertSame(200, $DB->count_records('webexmeetings_attendance', ['meetingid' => $meeting]));
            $this->assertSame(200, $DB->count_records('webexmeetings_sessions', ['meetingid' => $meeting]));
            $this->assertSame(0, $DB->count_records('webexmeetings_unmatched', ['meetingid' => $meeting]));
        }
    }

    public function test_mapping_survives_sync_and_cannot_cross_activities(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $other = $this->meeting();
        $user = $this->enrol($meeting);
        $guest = $this->guest('guest-1', 'guest-1@guest.webex.localhost');
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        $row = $DB->get_record('webexmeetings_unmatched', ['meetingid' => $meeting], '*', MUST_EXIST);
        $this->assertNull(attendance_mapping::match($guest, $meeting));
        attendance_mapping::map($meeting, $row->id, $user->id);
        attendance_mapping::map($meeting, $row->id, $user->id);
        $this->assertSame($user->id, attendance_mapping::match($guest, $meeting)->id);
        $this->assertNull(attendance_mapping::match($guest, $other));
        $this->assertSame(1, $DB->count_records('webexmeetings_sessions', ['meetingid' => $meeting]));
        $this->assertEquals(300, $DB->get_field('webexmeetings_attendance', 'duration', ['meetingid' => $meeting]));
        $guest->leftTime = '2026-11-04T09:06:00Z';
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        $this->assertSame($user->id, attendance_mapping::match($guest, $meeting)->id);
        $this->assertEquals($user->id, $DB->get_field('webexmeetings_unmatched', 'mapped_userid', ['id' => $row->id]));
    }

    public function test_reentries_devices_and_empty_email_guests_are_preserved(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $user = $this->enrol($meeting);
        $guest = $this->guest('guest-1');
        $guest->devices = [(object)['joinedTime' => '2026-11-04T09:00:00Z', 'leftTime' => '2026-11-04T09:05:00Z'],
            (object)['joinedTime' => '2026-11-04T09:10:00Z', 'leftTime' => '2026-11-04T09:15:00Z']];
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        $other = $this->guest('guest-2');
        attendance_mapping::remember($other, $meeting, attendance_mapping::sessions($other));
        $this->assertSame(3, $DB->count_records('webexmeetings_unmatched', ['meetingid' => $meeting]));
        $rows = $DB->get_records('webexmeetings_unmatched', ['meetingid' => $meeting, 'webex_id' => 'guest-1']);
        attendance_mapping::map($meeting, reset($rows)->id, $user->id);
        $this->assertSame(2, $DB->count_records('webexmeetings_sessions', ['meetingid' => $meeting]));
        $this->assertEquals(600, $DB->get_field('webexmeetings_attendance', 'duration', ['meetingid' => $meeting]));
        $this->assertNull(attendance_mapping::match($other, $meeting));
        attendance_mapping::map($meeting, reset($rows)->id, $user->id);
        $this->assertEquals(600, $DB->get_field('webexmeetings_attendance', 'duration', ['meetingid' => $meeting]));
    }

    public function test_mapping_rejects_foreign_row_and_unenrolled_target(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $other = $this->meeting();
        $user = $this->enrol($meeting);
        $guest = $this->guest('guest-1');
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        $row = $DB->get_record('webexmeetings_unmatched', ['meetingid' => $meeting]);
        try {
            attendance_mapping::map($other, $row->id, $user->id);
            $this->fail('Cross-activity mapping accepted');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame(0, $DB->count_records('webexmeetings_sessions'));
        }
        $outsider = $this->getDataGenerator()->create_user();
        try {
            attendance_mapping::map($meeting, $row->id, $outsider->id);
            $this->fail('Non-enrolled target accepted');
        } catch (\moodle_exception $e) {
            $this->assertEquals(0, $DB->get_field('webexmeetings_unmatched', 'mapped_userid', ['id' => $row->id]));
        }
    }

    public function test_names_and_email_prefixes_are_not_identity_proofs(): void {
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $this->getDataGenerator()->create_user(['username' => 'shared', 'firstname' => 'Same', 'lastname' => 'name']);
        $guest = $this->guest('guest-1', 'shared@example.invalid');
        $this->assertNull(attendance_mapping::match($guest, $meeting));
        $guest->email = '';
        $this->assertNull(attendance_mapping::match($guest, $meeting));
        $guest->devices = [];
        $this->assertCount(1, attendance_mapping::sessions($guest));
        $guest->joinedTime = 'not-a-date';
        $this->assertSame([], attendance_mapping::sessions($guest));
    }

    public function test_overlapping_connections_count_only_once(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $user = $this->enrol($meeting);
        $guest = $this->guest('guest-1');
        $guest->devices = [(object)['joinedTime' => '2026-11-04T09:00:00Z', 'leftTime' => '2026-11-04T09:10:00Z'],
            (object)['joinedTime' => '2026-11-04T09:05:00Z', 'leftTime' => '2026-11-04T09:15:00Z']];
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        $row = $DB->get_record('webexmeetings_unmatched', ['meetingid' => $meeting], '*', IGNORE_MULTIPLE);
        attendance_mapping::map($meeting, $row->id, $user->id);
        $this->assertEquals(900, $DB->get_field('webexmeetings_attendance', 'duration', ['meetingid' => $meeting]));
    }

    public function test_suspended_target_is_not_reused(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $user = $this->enrol($meeting);
        $guest = $this->guest('guest-1');
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        $row = $DB->get_record('webexmeetings_unmatched', ['meetingid' => $meeting], '*', MUST_EXIST);
        attendance_mapping::map($meeting, $row->id, $user->id);
        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);
        $this->assertNull(attendance_mapping::match($guest, $meeting));
    }

    public function test_actual_import_reuses_manual_mapping_on_next_sync(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $user = $this->enrol($meeting);
        $guest = $this->guest('guest-1', 'guest-1@guest.webex.localhost');
        $api = new attendance_import_stub();
        $this->assertSame([], $api->import([$guest], $meeting));
        $row = $DB->get_record('webexmeetings_unmatched', ['meetingid' => $meeting], '*', MUST_EXIST);
        attendance_mapping::map($meeting, $row->id, $user->id);
        $first = $api->import([$guest], $meeting);
        $second = $api->import([$guest], $meeting);
        $this->assertSame($user->id, $first[0]->userid);
        $this->assertEquals($first, $second);
        $this->assertSame(1, $DB->count_records('webexmeetings_unmatched', ['meetingid' => $meeting]));
        $this->assertSame(1, $DB->count_records('webexmeetings_sessions', ['meetingid' => $meeting]));
    }

    public function test_different_ids_with_same_guest_email_need_separate_confirmation(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $user = $this->enrol($meeting);
        $guest = $this->guest('guest-1', 'shared@guest.webex.localhost');
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        $row = $DB->get_record('webexmeetings_unmatched', ['meetingid' => $meeting], '*', MUST_EXIST);
        attendance_mapping::map($meeting, $row->id, $user->id);
        $guest->id = 'another-person';
        $this->assertNull(attendance_mapping::match($guest, $meeting));
        unset($guest->id);
        $this->assertNull(attendance_mapping::match($guest, $meeting));
        attendance_mapping::remember($guest, $meeting, attendance_mapping::sessions($guest));
        $this->assertEquals('guest-1', $DB->get_field('webexmeetings_unmatched', 'webex_id', ['id' => $row->id]));
        $this->assertNull(attendance_mapping::match($guest, $meeting));
        $this->assertSame(2, $DB->count_records('webexmeetings_unmatched', ['meetingid' => $meeting]));
    }

    public function test_resync_never_shrinks_or_duplicates_closed_sessions(): void {
        global $DB;
        $this->resetAfterTest();
        $meeting = $this->meeting();
        $user = $this->enrol($meeting);
        foreach ([600, 300, 0, 600] as $duration) {
            attendance_mapping::store_sessions($meeting, $user->id,
                [(object)['join_time' => 1000, 'leave_time' => $duration ? 1000 + $duration : 0]]);
        }
        $this->assertSame(1, $DB->count_records('webexmeetings_sessions', ['meetingid' => $meeting]));
        $this->assertEquals(600, $DB->get_field('webexmeetings_attendance', 'duration', ['meetingid' => $meeting]));
    }
}
