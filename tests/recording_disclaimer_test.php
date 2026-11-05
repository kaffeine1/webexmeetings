<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use mod_webexmeetings\local\recording_disclaimer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Recording notice policy tests without Webex API calls.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(recording_disclaimer::class)]
final class recording_disclaimer_test extends \advanced_testcase {
    private function fixture(): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $course = $this->getDataGenerator()->create_course();
        $id = $DB->insert_record('webexmeetings', (object)['course' => $course->id, 'name' => 'Synthetic notice']);
        $cmid = add_course_module((object)['course' => $course->id,
            'module' => $DB->get_field('modules', 'id', ['name' => 'webexmeetings'], MUST_EXIST),
            'instance' => $id, 'section' => 0]);
        return [$course, \context_module::instance($cmid)];
    }

    public function test_audience_switch_matrix_and_dual_roles(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $context] = $this->fixture();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $dual = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($dual->id, $course->id, 'student');
        role_assign($DB->get_field('role', 'id', ['shortname' => 'teacher']), $dual->id, $context->id);
        foreach ([0, 1] as $participants) {
            foreach ([0, 1] as $assignees) {
                $meeting = (object)['disclaimer_participants' => $participants, 'disclaimer_assignees' => $assignees];
                $this->assertSame((bool)$participants, recording_disclaimer::required($meeting, $context, $student->id));
                $this->assertSame((bool)$assignees, recording_disclaimer::required($meeting, $context, $teacher->id));
                $this->assertSame((bool)($participants || $assignees), recording_disclaimer::required($meeting, $context, $dual->id));
            }
        }
    }

    public function test_sailfor_assignment_roles_without_archetype(): void {
        $this->resetAfterTest();
        [$course, $context] = $this->fixture();
        foreach (['tutor', 'presenter', 'trainer', 'scientific', 'moderator', 'staff', 'expert', 'faddymanager'] as $name) {
            $user = $this->getDataGenerator()->create_user();
            $role = $this->getDataGenerator()->create_role(['shortname' => $name, 'archetype' => '']);
            $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
            $this->assertTrue(recording_disclaimer::required((object)['disclaimer_assignees' => 1], $context, $user->id));
            $this->assertFalse(recording_disclaimer::required((object)['disclaimer_participants' => 1], $context, $user->id));
        }
    }

    public function test_defaults_are_not_a_live_override_or_recording_control(): void {
        $this->resetAfterTest();
        set_config('default_disclaimer_participants', 1, 'mod_webexmeetings');
        set_config('default_disclaimer_assignees', 0, 'mod_webexmeetings');
        $new = new \stdClass();
        recording_disclaimer::apply_defaults($new);
        $this->assertSame(1, $new->disclaimer_participants);
        $this->assertSame(0, $new->disclaimer_assignees);
        $existing = (object)['disclaimer_participants' => 0, 'disclaimer_assignees' => 1];
        recording_disclaimer::apply_defaults($existing);
        $this->assertSame(0, $existing->disclaimer_participants);
        $this->assertSame(1, $existing->disclaimer_assignees);
        $this->assertFalse(property_exists($new, 'enabledAutoRecordMeeting'));
    }

    public function test_admin_uses_assignee_switch_and_guest_gets_no_consent_privilege(): void {
        $this->resetAfterTest();
        [, $context] = $this->fixture();
        $admin = get_admin();
        $this->assertTrue(recording_disclaimer::required((object)['disclaimer_assignees' => 1], $context, $admin->id));
        $this->assertFalse(recording_disclaimer::required((object)['disclaimer_participants' => 1], $context, $admin->id));
        $this->assertFalse(recording_disclaimer::required((object)['disclaimer_participants' => 1,
            'disclaimer_assignees' => 1], $context, guest_user()->id));
    }

    public function test_schema_upgrade_preserves_existing_activity_choices(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        $course = $this->getDataGenerator()->create_course();
        $id = $DB->insert_record('webexmeetings', (object)['course' => $course->id, 'name' => 'Upgrade fixture',
            'disclaimer_participants' => 1, 'disclaimer_assignees' => 0]);
        set_config('version', 2026101300, 'mod_webexmeetings');
        try {
            $this->assertTrue(xmldb_webexmeetings_upgrade(2026101300));
            $record = $DB->get_record('webexmeetings', ['id' => $id], '*', MUST_EXIST);
            $this->assertEquals(1, $record->disclaimer_participants);
            $this->assertEquals(0, $record->disclaimer_assignees);
        } finally {
            unset_config('upgraderunning');
        }
    }
}
