<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use mod_webexmeetings\external\webex_api;

defined('MOODLE_INTERNAL') || die();

/** HTTP responses queued without contacting Webex. */
final class webex_api_stub extends webex_api {
    public array $responses = [];
    public array $requests = [];

    protected function request(string $method, string $url, ?array $data = null): array {
        $this->requests[] = compact('method', 'url', 'data');
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) {
            throw $response;
        }
        if (!is_array($response)) {
            throw new \coding_exception('Missing fake Webex response');
        }
        return $response;
    }
}

/**
 * Preflight checks for the meeting lifecycle and attendance API.
 *
 * @package mod_webexmeetings
 */
final class webex_api_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('auth_method', 'bot', 'mod_webexmeetings');
        set_config('personal_token', 'fake-token', 'mod_webexmeetings');
    }

    private static function response($body, array $headers = []): array {
        return ['body' => $body, 'headers' => $headers];
    }

    public function test_create_requires_id_and_join_link_and_uses_utc(): void {
        $api = new webex_api_stub();
        $api->responses[] = self::response((object)[
            'id' => 'series-1', 'webLink' => 'https://example.webex.com/join/1',
        ]);
        $start = strtotime('2026-10-01T14:00:00Z');
        $data = (object)[
            'name' => 'Preflight', 'start_time' => $start, 'end_time' => $start + 3600,
            'meeting_password' => 'valid-password', 'recurring' => 0,
        ];
        $oldtimezone = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Rome');
            $created = $api->create_meeting($data);
        } finally {
            date_default_timezone_set($oldtimezone);
        }
        $this->assertSame('series-1', $created->id);
        $this->assertSame('POST', $api->requests[0]['method']);
        $this->assertSame('2026-10-01T14:00:00Z', $api->requests[0]['data']['start']);
        $this->assertSame('2026-10-01T15:00:00Z', $api->requests[0]['data']['end']);
        $this->assertSame('valid-password', $api->requests[0]['data']['password']);

        $api->responses[] = self::response((object)['id' => 'series-2']);
        $this->expectException(\Exception::class);
        $api->create_meeting($data);
    }

    public function test_update_uses_submitted_password_and_propagates_failure(): void {
        $api = new webex_api_stub();
        $api->responses[] = self::response(null);
        $data = (object)[
            'meeting_id' => 'series-1', 'name' => 'Updated',
            'start_time' => strtotime('2026-10-01T14:00:00Z'),
            'end_time' => strtotime('2026-10-01T15:00:00Z'),
            'meeting_password' => 'new-password', 'password' => 'old-password',
        ];
        $this->assertTrue($api->update_meeting($data));
        $this->assertSame('new-password', $api->requests[0]['data']['password']);
        $api->responses[] = new \Exception('Webex API request failed (HTTP 403)');
        $this->expectException(\Exception::class);
        $api->update_meeting($data);
    }

    public function test_connection_checks_identity_and_meeting_read_access(): void {
        $api = new webex_api_stub();
        $api->responses[] = self::response((object)['id' => 'person-1']);
        $api->responses[] = self::response((object)['items' => []]);
        $this->assertTrue($api->test_connection());
        $this->assertCount(2, $api->requests);
        $this->assertStringContainsString('/people/me', $api->requests[0]['url']);
        $this->assertStringContainsString('/meetings?', $api->requests[1]['url']);
    }

    public function test_personal_join_uses_explicit_identity_and_never_returns_host_link(): void {
        $api = new webex_api_stub();
        $link = 'https://customer.webex.com/wbxmjs/join?integrationJoinToken=synthetic';
        $api->responses[] = self::response((object)['joinLink' => $link,
            'startLink' => 'https://customer.webex.com/host?integrationJoinToken=host-only']);
        $this->assertSame($link, $api->get_join_link('series-1', 'student@example.invalid', 'Synthetic Student'));
        $this->assertSame('POST', $api->requests[0]['method']);
        $this->assertSame('https://webexapis.com/v1/meetings/join', $api->requests[0]['url']);
        $this->assertSame(['meetingId' => 'series-1', 'joinDirectly' => false, 'expirationMinutes' => 5,
            'email' => 'student@example.invalid', 'displayName' => 'Synthetic Student'], $api->requests[0]['data']);
    }

    public function test_personal_join_rejects_missing_untrusted_or_host_only_links(): void {
        foreach ([null, '', 'http://customer.webex.com/join', 'https://webex.com.evil.test/join',
                'https://evil.invalid\\.webex.com/join',
                'https://user@customer.webex.com/join', 'https://customer.webex.com:444/join',
                "https://customer.webex.com/join\r\nInjected: yes", 'https://customer.webex.com/join#fragment',
                ['not-a-string'], 'https://customer.webex.com/host-only'] as $link) {
            $api = new webex_api_stub();
            $api->responses[] = self::response((object)['joinLink' => $link,
                'startLink' => 'https://customer.webex.com/host-only']);
            try {
                $api->get_join_link('series-1', 'student@example.invalid', 'Synthetic Student');
                $this->fail('Unsafe attendee destination accepted');
            } catch (\moodle_exception $e) {
                $this->assertSame('personaljoinunavailable', $e->errorcode);
            }
        }
    }

    public function test_personal_join_rejects_invalid_identity_before_any_api_request(): void {
        foreach ([['', 'Student'], ['guest@guest.webex.localhost', 'Student'],
                ['valid@example.invalid', ''], ['valid@example.invalid', str_repeat('X', 129)],
                ['valid@example.invalid', "Name\nInjected"]] as [$email, $name]) {
            $api = new webex_api_stub();
            try {
                $api->get_join_link('series-1', $email, $name);
                $this->fail('Invalid identity accepted');
            } catch (\moodle_exception $e) {
                $this->assertSame('participantidentityunavailable', $e->errorcode);
                $this->assertCount(0, $api->requests);
            }
        }
    }

    public function test_personal_join_uses_host_email_only_for_organization_profile(): void {
        set_config('api_host_email', 'host@example.invalid', 'mod_webexmeetings');
        set_config('oauth_scope_profile', 'organization', 'mod_webexmeetings');
        $api = new webex_api_stub();
        $api->responses[] = self::response((object)['joinLink' => 'https://customer.webex.com/join']);
        $api->get_join_link('series-1', 'student@example.invalid', 'Synthetic Student');
        $this->assertSame('host@example.invalid', $api->requests[0]['data']['hostEmail']);
    }

    public function test_personal_join_propagates_failure_instead_of_using_anonymous_entry(): void {
        $api = new webex_api_stub();
        $api->responses[] = new \Exception('Webex API request failed (HTTP 403)');
        $this->expectExceptionMessage('Webex API request failed (HTTP 403)');
        $api->get_join_link('series-1', 'student@example.invalid', 'Synthetic Student');
    }

    public function test_start_link_uses_local_id_and_never_returns_attendee_link(): void {
        $api = new webex_api_stub();
        $link = 'https://customer.webex.com/wbxmjs/start?integrationJoinToken=fake';
        $api->responses[] = self::response((object)['startLink' => $link, 'joinLink' => 'https://customer.webex.com/join']);
        $this->assertSame($link, $api->get_start_link('series-1'));
        $this->assertSame('POST', $api->requests[0]['method']);
        $this->assertSame('https://webexapis.com/v1/meetings/join', $api->requests[0]['url']);
        $this->assertSame(['meetingId' => 'series-1', 'joinDirectly' => false, 'expirationMinutes' => 5],
            $api->requests[0]['data']);
    }

    public function test_start_link_rejects_untrusted_or_missing_urls(): void {
        foreach ([null, '', 'http://customer.webex.com/start', 'https://webex.com.evil.test/start',
                'https://evil.invalid\\.webex.com/start',
                'https://evilwebex.com/start', 'https://user@customer.webex.com/start',
                'https://customer.webex.com:444/start', "https://customer.webex.com/start\r\nInjected: yes",
                'https://customer.webex.com/start#fragment', ['not-a-string']] as $link) {
            $api = new webex_api_stub();
            $api->responses[] = self::response((object)['startLink' => $link, 'joinLink' => 'https://customer.webex.com/join']);
            try {
                $api->get_start_link('series-1');
                $this->fail('Untrusted or missing host URL accepted');
            } catch (\moodle_exception $error) {
                $this->assertSame('startmeetingunavailable', $error->errorcode);
            }
        }
    }

    public function test_start_link_rejects_empty_id_before_calling_api(): void {
        $api = new webex_api_stub();
        try {
            $api->get_start_link(' ');
            $this->fail('Empty meeting ID accepted');
        } catch (\moodle_exception $error) {
            $this->assertSame('nomeetingid', $error->errorcode);
            $this->assertCount(0, $api->requests);
        }
    }

    public function test_start_link_uses_host_email_only_for_organization_profile(): void {
        set_config('api_host_email', 'host@example.test', 'mod_webexmeetings');
        set_config('oauth_scope_profile', 'organization', 'mod_webexmeetings');
        $api = new webex_api_stub();
        $api->responses[] = self::response((object)['startLink' => 'https://customer.webex.com/start']);
        $api->get_start_link('series-1');
        $this->assertSame('host@example.test', $api->requests[0]['data']['hostEmail']);
    }

    public function test_start_link_propagates_api_failure(): void {
        $api = new webex_api_stub();
        $api->responses[] = new \Exception('Webex API request failed (HTTP 403)');
        $this->expectExceptionMessage('Webex API request failed (HTTP 403)');
        $api->get_start_link('series-1');
    }

    public function test_attendance_uses_actual_instances_and_all_pages(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'attendee@example.test']);
        $api = new webex_api_stub();
        $api->responses[] = self::response((object)['id' => 'scheduled-1', 'meetingSeriesId' => 'series-1']);
        $next = 'https://webexapis.com/v1/meetings?cursor=second';
        $api->responses[] = self::response((object)['items' => []], ['Link' => '<' . $next . '>; rel="next"']);
        $api->responses[] = self::response((object)['items' => [
            (object)['id' => 'instance-1', 'meetingType' => 'meeting', 'meetingSeriesId' => 'series-1'],
        ]]);
        $api->responses[] = self::response((object)['items' => [
            (object)['email' => 'attendee@example.test', 'joinedTime' => '2026-10-01T14:00:00Z',
                'leftTime' => '2026-10-01T14:30:00Z'],
            (object)['email' => 'unmatched@example.test', 'id' => 'webex-person',
                'joinedTime' => '2026-10-01T14:05:00Z', 'leftTime' => '2026-10-01T14:10:00Z'],
        ]]);
        $start = strtotime('2026-10-01T14:00:00Z');
        $result = $api->get_attendance('scheduled-1', 42, $start, $start + 3600);
        $this->assertCount(1, $result);
        $this->assertSame($user->id, $result[0]->userid);
        $this->assertSame(1800, $result[0]->total_duration);
        $this->assertStringContainsString('meetingSeriesId=series-1', $api->requests[1]['url']);
        $this->assertSame($next, $api->requests[2]['url']);
        $this->assertStringContainsString('meetingId=instance-1', $api->requests[3]['url']);
        $unmatched = $DB->get_record('webexmeetings_unmatched', ['webex_email' => 'unmatched@example.test']);
        $this->assertSame(42, (int)$unmatched->meetingid);
    }

    public function test_pagination_refuses_to_send_token_to_another_host(): void {
        $api = new webex_api_stub();
        $api->responses[] = self::response((object)['id' => 'series-1']);
        $api->responses[] = self::response((object)['items' => []],
            ['Link' => '<https://untrusted.example/v1/meetings>; rel="next"']);
        $start = strtotime('2026-10-01T14:00:00Z');
        try {
            $api->get_attendance('series-1', 42, $start, $start + 3600);
            $this->fail('Untrusted pagination URL accepted');
        } catch (\Exception $e) {
            $this->assertSame('Invalid Webex pagination URL', $e->getMessage());
            $this->assertCount(2, $api->requests);
        }
    }

    public function test_failed_creation_does_not_store_an_orphan_activity(): void {
        global $DB;
        $this->setAdminUser();
        require_once(__DIR__ . '/../lib.php');
        \curl::mock_response('{"message":"rejected"}');
        $before = $DB->count_records('webexmeetings');
        $start = strtotime('2026-10-01T14:00:00Z');
        $data = (object)[
            'name' => 'Preflight', 'course' => 1, 'coursemodule' => 1,
            'start_time' => $start, 'end_time' => $start + 3600,
            'meeting_password' => '', 'recurring' => 0,
        ];
        try {
            webexmeetings_add_instance($data);
            $this->fail('Failed Webex call created an activity');
        } catch (\moodle_exception $e) {
            $this->assertSame($before, $DB->count_records('webexmeetings'));
        }
    }
}
