<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use core\hook\navigation\primary_extend;
use mod_webexmeetings\local\oauth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

/**
 * Visibility and destination of the primary Webex connection link.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /** @return array User states and expected link visibility. */
    public static function user_provider(): array {
        return [
            'administrator' => ['admin', true],
            'active delegate' => ['delegate', true],
            'ordinary user' => ['ordinary', false],
            'anonymous visitor' => ['anonymous', false],
            'guest' => ['guest', false],
            'suspended delegate' => ['suspended', false],
            'unconfirmed delegate' => ['unconfirmed', false],
            'revoked delegate' => ['revoked', false],
            'replaced delegate' => ['replaced', false],
        ];
    }

    /**
     * Check registered hook dispatch and the resulting node for each user state.
     *
     * @param string $kind User state.
     * @param bool $visible Expected visibility.
     */
    #[DataProvider('user_provider')]
    public function test_connection_link(string $kind, bool $visible): void {
        global $CFG;
        $this->resetAfterTest();

        if ($kind === 'admin') {
            $this->setAdminUser();
        } else if ($kind === 'anonymous') {
            $this->setUser(null);
        } else if ($kind === 'guest') {
            $this->setGuestUser();
        } else {
            $user = $this->getDataGenerator()->create_user([
                'suspended' => (int)($kind === 'suspended'),
                'confirmed' => (int)($kind !== 'unconfirmed'),
            ]);
            if ($kind !== 'ordinary' && $kind !== 'revoked') {
                $delegate = $kind === 'replaced' ? $this->getDataGenerator()->create_user() : $user;
                set_config('oauth_delegate_userid', $delegate->id, oauth::COMPONENT);
            }
            $this->setUser($user);
        }

        $manager = \core\hook\manager::phpunit_get_instance([
            oauth::COMPONENT => $CFG->dirroot . '/mod/webexmeetings/db/hooks.php',
        ]);
        \core\di::set(\core\hook\manager::class, $manager);
        $page = new \moodle_page();
        $GLOBALS['PAGE'] = $page;
        $page->set_context(\context_system::instance());
        $page->set_url('/');
        $navigation = new \core\navigation\views\primary($page);
        $navigation->initialise();
        $node = $navigation->get('mod_webexmeetings_oauth', \navigation_node::TYPE_CUSTOM);

        if (!$visible) {
            $this->assertFalse($node);
            return;
        }

        $this->assertInstanceOf(\navigation_node::class, $node);
        $this->assertSame(get_string('oauthconnect', oauth::COMPONENT), $node->text);
        $this->assertSame(oauth::management_url()->out(false), $node->action->out(false));
        $this->assertSame([], $node->action->params());
        $count = $navigation->children->count();
        $manager->dispatch(new primary_extend($navigation));
        $this->assertSame($count, $navigation->children->count());
    }
}
