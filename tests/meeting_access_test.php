<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use mod_webexmeetings\local\meeting_access;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Scheduled entry policy, without contacting Webex.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(meeting_access::class)]
final class meeting_access_test extends \advanced_testcase {
    public function test_single_meeting_closes_exactly_at_scheduled_end(): void {
        $meeting = (object)['start_time' => 1000, 'end_time' => 2000, 'recurring' => 0];
        $this->assertFalse(meeting_access::has_ended($meeting, 999));
        $this->assertSame('meetingnotstarted', meeting_access::status($meeting, 999));
        $this->assertFalse(meeting_access::has_ended($meeting, 1999));
        $this->assertSame('meetinginprogress', meeting_access::status($meeting, 1999));
        $this->assertTrue(meeting_access::has_ended($meeting, 2000));
        $this->assertSame('meetingscheduleended', meeting_access::status($meeting, 2000));
        $this->assertTrue(meeting_access::has_ended($meeting, 2001));
    }

    public function test_recurring_series_is_not_closed_by_first_occurrence(): void {
        $meeting = (object)['start_time' => 1000, 'end_time' => 2000, 'recurring' => 1];
        $this->assertSame('meetingnotstarted', meeting_access::status($meeting, 999));
        $this->assertFalse(meeting_access::has_ended($meeting, 2001));
        $this->assertSame('recurringmeeting', meeting_access::status($meeting, 2001));
    }

    public function test_invalid_end_is_not_an_unlimited_entry_window(): void {
        $this->assertTrue(meeting_access::has_ended((object)['end_time' => 0, 'recurring' => 0], 1000));
        $this->assertTrue(meeting_access::has_ended((object)['recurring' => 1], 1000));
    }

    public function test_controller_guard_throws_a_localised_error(): void {
        $meeting = (object)['start_time' => time() - 100, 'end_time' => time() - 1, 'recurring' => 0];
        try {
            meeting_access::require_open($meeting, 42);
            $this->fail('Expired activity accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('meetingaccessended', $e->errorcode);
            $this->assertSame('mod_webexmeetings', $e->module);
        }
        $meeting->end_time = time() + 100;
        meeting_access::require_open($meeting, 42);
        $this->assertFalse(meeting_access::has_ended($meeting));
    }
}
