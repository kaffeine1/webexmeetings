<?php
// Synthetic browser fixture. Never loaded by the plugin or on a real site.
namespace mod_webexmeetings\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
if (PHP_SAPI !== 'cli-server' || $CFG->wwwroot !== 'http://127.0.0.1:8122' ||
        $CFG->dbname !== 'webex22_qa' || empty($CFG->webexqa_entry_state)) {
    throw new \coding_exception('Disposable localhost browser fixture required');
}

/** Test double for the controller; the actual API class has separate native tests. */
class webex_api {
    public function __construct() {
        self::state(static function(array $state): array {
            $state['constructs'] = ($state['constructs'] ?? 0) + 1;
            return $state;
        });
    }

    private static function state(callable $update): array {
        global $CFG;
        $state = json_decode(file_get_contents($CFG->webexqa_entry_state), true, 512, JSON_THROW_ON_ERROR);
        $state = $update($state);
        file_put_contents($CFG->webexqa_entry_state, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX);
        return $state;
    }

    public function get_join_link(string $meetingid, string $email, string $displayname): string {
        $state = self::state(static function(array $state) use ($meetingid, $email, $displayname): array {
            $state['requests'][] = compact('meetingid', 'email', 'displayname');
            return $state;
        });
        if (!empty($state['fail'])) {
            throw new \Exception('Synthetic API failure');
        }
        return 'https://example.webex.com/personal/synthetic';
    }

    public function get_start_link(string $meetingid): string {
        throw new \coding_exception('Unexpected organiser API call in the synthetic browser fixture');
    }
}
