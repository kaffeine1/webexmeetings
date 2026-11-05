<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use core\hook\navigation\primary_extend;
use mod_webexmeetings\local\oauth;

defined('MOODLE_INTERNAL') || die();

/**
 * Integrate the connection page into Moodle's primary navigation.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Show the connection link only to users who may authorise Webex.
     *
     * @param primary_extend $hook Primary navigation extension point.
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        if (!isloggedin() || isguestuser() || !oauth::can_connect()) {
            return;
        }

        $navigation = $hook->get_primaryview();
        if ($navigation->get('mod_webexmeetings_oauth', \navigation_node::TYPE_CUSTOM)) {
            return;
        }

        $navigation->add(
            get_string('webexnavigation', oauth::COMPONENT),
            oauth::management_url(),
            \navigation_node::TYPE_CUSTOM,
            null,
            'mod_webexmeetings_oauth',
        );
    }
}
