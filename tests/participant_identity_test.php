<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use mod_webexmeetings\local\participant_identity;
use PHPUnit\Framework\Attributes\CoversClass;

/** Session identity checks for opt-in personal entry. */
#[CoversClass(participant_identity::class)]
final class participant_identity_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_uses_only_the_active_accounts_unique_email_and_name(): void {
        $user = $this->getDataGenerator()->create_user([
            'email' => 'person@example.invalid', 'firstname' => 'Test', 'lastname' => 'Participant',
        ]);
        $this->assertSame(['email' => 'person@example.invalid', 'displayName' => fullname($user)],
            participant_identity::details($user));
    }

    public function test_duplicate_active_email_is_not_automatically_assigned(): void {
        $user = $this->getDataGenerator()->create_user(['email' => 'shared@example.invalid']);
        $this->getDataGenerator()->create_user(['email' => $user->email]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('participantidentityunavailable', 'mod_webexmeetings'));
        participant_identity::details($user);
    }

    private function enrol_for_meeting(\stdClass $user): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $course = $this->getDataGenerator()->create_course();
        $id = $DB->insert_record('webexmeetings', (object)['course' => $course->id, 'name' => 'Synthetic scoped identity']);
        $cmid = add_course_module((object)['course' => $course->id,
            'module' => $DB->get_field('modules', 'id', ['name' => 'webexmeetings']),
            'instance' => $id, 'section' => 0]);
        course_add_cm_to_section($course->id, $cmid, 0);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        return $id;
    }

    public function test_duplicate_outside_course_does_not_block_the_enrolled_session_user(): void {
        $user = $this->getDataGenerator()->create_user(['email' => 'shared@example.invalid']);
        $this->getDataGenerator()->create_user(['email' => $user->email]);
        $meeting = $this->enrol_for_meeting($user);
        $this->assertSame(['email' => $user->email, 'displayName' => fullname($user)],
            participant_identity::details($user, $meeting));
    }

    public function test_two_enrolled_users_with_the_same_email_are_still_rejected(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'shared@example.invalid']);
        $meeting = $this->enrol_for_meeting($user);
        $other = $this->getDataGenerator()->create_user(['email' => $user->email]);
        $courseid = $DB->get_field('webexmeetings', 'course', ['id' => $meeting]);
        $this->getDataGenerator()->enrol_user($other->id, $courseid);
        $this->expectException(\moodle_exception::class);
        participant_identity::details($user, $meeting);
    }

    public function test_unenrolled_duplicate_cannot_borrow_the_enrolled_users_identity(): void {
        $member = $this->getDataGenerator()->create_user(['email' => 'shared@example.invalid']);
        $meeting = $this->enrol_for_meeting($member);
        $outsider = $this->getDataGenerator()->create_user(['email' => $member->email]);
        $this->expectException(\moodle_exception::class);
        participant_identity::details($outsider, $meeting);
    }

    public function test_another_accounts_email_cannot_be_supplied(): void {
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $user->email = $other->email;
        $this->expectException(\moodle_exception::class);
        participant_identity::details($user);
    }

    public function test_invalid_and_anonymous_identities_are_rejected(): void {
        $user = $this->getDataGenerator()->create_user();
        foreach (['', 'invalid', 'technical@guest.webex.localhost'] as $email) {
            $copy = clone $user;
            $copy->email = $email;
            try {
                participant_identity::details($copy);
                $this->fail('Invalid identity accepted');
            } catch (\moodle_exception $e) {
                $this->assertSame('participantidentityunavailable', $e->errorcode);
            }
        }
        foreach (['deleted', 'suspended'] as $flag) {
            $copy = clone $user;
            $copy->$flag = 1;
            try {
                participant_identity::details($copy);
                $this->fail('Inactive identity accepted');
            } catch (\moodle_exception $e) {
                $this->assertSame('participantidentityunavailable', $e->errorcode);
            }
        }
    }

    public function test_guest_is_rejected_and_long_names_are_bounded(): void {
        try {
            participant_identity::details(guest_user());
            $this->fail('Guest identity accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('participantidentityunavailable', $e->errorcode);
        }
        $user = $this->getDataGenerator()->create_user();
        $user->firstname = str_repeat('A', 110);
        $user->lastname = str_repeat('B', 110);
        $this->assertSame(128, \core_text::strlen(participant_identity::details($user)['displayName']));
    }
}
