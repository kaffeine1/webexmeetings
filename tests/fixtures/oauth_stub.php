<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Token endpoint test double; no external requests.
 *
 * @package mod_webexmeetings
 * @copyright 2026 Michele Dipace
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth_stub extends oauth {
    /** @var array Captured request parameters. */
    public array $requests = [];
    /** @var array Queued status/body responses. */
    public array $responses = [];
    /** @var callable|null Optional concurrent configuration change. */
    public $onrequest = null;

    /** @param array $params Token parameters. @return array Response tuple. */
    protected function transport(array $params): array {
        $this->requests[] = $params;
        if ($this->onrequest) {
            ($this->onrequest)();
        }
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) {
            throw $response;
        }
        if ($response === null) {
            throw new \LogicException('Unexpected network request in test');
        }
        return $response;
    }
}
