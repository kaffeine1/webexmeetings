<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace mod_webexmeetings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/webexmeetings/mod_form.php');

/** Creation-time validation using the actual Moodle activity form. */
#[CoversClass(\mod_webexmeetings_mod_form::class)]
final class mod_form_test extends \advanced_testcase {
    public static function start_provider(): array {
        return [
            'past minute' => [-60, false, true],
            'current time' => [0, false, true],
            'too close to submission' => [30, false, true],
            'safe future start' => [120, false, false],
            'default future start' => [3600, false, false],
            'existing started meeting' => [-3600, true, false],
            'existing future meeting' => [3600, true, false],
        ];
    }

    #[DataProvider('start_provider')]
    public function test_start_validation(int $offset, bool $existing, bool $invalid): void {
        global $CFG, $DB, $PAGE, $COURSE;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/course/lib.php');
        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $start = time() + $offset;
        $instance = 0;
        $cm = null;
        if ($existing) {
            $instance = $DB->insert_record('webexmeetings', (object)['course' => $course->id,
                'name' => 'Synthetic meeting', 'start_time' => $start, 'end_time' => $start + 3600]);
            $cmid = add_course_module((object)['course' => $course->id,
                'module' => $DB->get_field('modules', 'id', ['name' => 'webexmeetings']),
                'instance' => $instance, 'section' => 0]);
            course_add_cm_to_section($course->id, $cmid, 0);
            $cm = get_coursemodule_from_id('webexmeetings', $cmid);
        }
        $current = (object)['instance' => $instance, 'course' => $course->id,
            'coursemodule' => $cm->id ?? 0, 'modulename' => 'webexmeetings',
            'name' => 'Synthetic meeting', 'start_time' => $start, 'end_time' => $start + 3600,
            'intro' => '', 'introformat' => FORMAT_HTML, 'grade' => 100,
            'visible' => 1, 'groupmode' => 0, 'groupingid' => 0, 'completion' => 0];
        $form = new \mod_webexmeetings_mod_form($current, 0, $cm, $course);
        $data = (array)$current + ['cmidnumber' => '', 'section' => 0, 'availabilityconditionsjson' => ''];
        $before = $DB->count_records('webexmeetings');
        $errors = $form->validation($data, []);
        $this->assertSame($invalid, isset($errors['start_time']));
        if ($invalid) {
            $this->assertSame(get_string('starttimetoosoon', 'webexmeetings'), $errors['start_time']);
        }
        $this->assertArrayNotHasKey('end_time', $errors);
        $this->assertSame($before, $DB->count_records('webexmeetings'));
        $this->assertSame('Synthetic meeting', $data['name']);
    }
}
