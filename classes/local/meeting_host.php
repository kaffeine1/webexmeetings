<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings\local;

defined('MOODLE_INTERNAL') || die();

/** Restrict use of the shared Webex host identity to its authorised operators. */
final class meeting_host {
    /** @return bool Whether the current user may launch this activity as host. */
    public static function can_start(\context_module $context): bool {
        $config = oauth::configuration();
        return ($config->auth_method ?? '') === 'oauth' && oauth::can_connect() &&
            has_capability('mod/webexmeetings:view', $context) &&
            has_capability('mod/webexmeetings:managemeeting', $context);
    }
}
