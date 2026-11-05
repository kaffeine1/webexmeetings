<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use mod_webexmeetings\local\meeting_host;

defined('MOODLE_INTERNAL') || die();

/** Shared Webex host access requires both delegation and course permissions. */
final class meeting_host_test extends \advanced_testcase {
    public function test_host_access_requires_delegation_and_activity_management(): void {
        $this->resetAfterTest();
        set_config('auth_method', 'oauth', 'mod_webexmeetings');
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($activity->cmid);
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($teacher);
        $this->assertFalse(meeting_host::can_start($context));
        set_config('oauth_delegate_userid', $teacher->id, 'mod_webexmeetings');
        $this->assertTrue(meeting_host::can_start($context));
        $this->setUser($student);
        set_config('oauth_delegate_userid', $student->id, 'mod_webexmeetings');
        $this->assertFalse(meeting_host::can_start($context));
        $this->setAdminUser();
        $this->assertTrue(meeting_host::can_start($context));
        set_config('auth_method', 'bot', 'mod_webexmeetings');
        $this->assertFalse(meeting_host::can_start($context));
    }
}
