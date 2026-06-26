<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_enrolreactivate;

use advanced_testcase;
use context_coursecat;
use context_system;
use tool_enrolreactivate\local\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/enrollib.php');

/**
 * Unit tests for manager helpers.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_enrolreactivate\local\manager
 */
final class manager_test extends advanced_testcase {
    /**
     * Test extension days normalization.
     *
     * @return void
     */
    public function test_normalize_days_respects_configured_limits(): void {
        $this->resetAfterTest(true);

        set_config('defaultdays', 120, 'tool_enrolreactivate');
        set_config('maxdays', 365, 'tool_enrolreactivate');

        $this->assertSame(120, manager::normalize_days(0));
        $this->assertSame(1, manager::normalize_days(1));
        $this->assertSame(365, manager::normalize_days(999));
    }

    /**
     * Verify extension preserves start date and extends from the later of now or the current end date.
     *
     * @return void
     */
    public function test_extend_for_teacher_preserves_window_start_and_extends_safely(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);

        $now = time();
        $timestart = $now - (15 * DAYSECS);
        $timeend = $now - DAYSECS;
        $generator->enrol_user(
            (int)$teacher->id,
            (int)$course->id,
            (int)$editingteacher->id,
            'manual',
            $timestart,
            $timeend,
            ENROL_USER_ACTIVE
        );

        $extensiondays = 30;
        $before = time();
        $summary = manager::extend_for_teacher(
            (int)$teacher->id,
            (int)$teacher->id,
            [(int)$course->id],
            $extensiondays,
            true
        );
        $after = time();

        $this->assertSame(1, (int)$summary['enrolmentsupdated']);
        $this->assertSame(1, (int)$summary['coursesupdated']);

        $sql = "SELECT ue.timestart, ue.timeend, ue.status
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid = :courseid";
        $ue = $DB->get_record_sql($sql, ['userid' => (int)$teacher->id, 'courseid' => (int)$course->id], MUST_EXIST);

        $this->assertSame($timestart, (int)$ue->timestart);
        $this->assertGreaterThanOrEqual($before + ($extensiondays * DAYSECS), (int)$ue->timeend);
        $this->assertLessThanOrEqual(($after + 1) + ($extensiondays * DAYSECS), (int)$ue->timeend);
        $this->assertSame(ENROL_USER_ACTIVE, (int)$ue->status);
    }

    /**
     * Future start dates must be reset so restored enrolments appear in My courses immediately.
     *
     * @return void
     */
    public function test_extend_for_teacher_resets_future_start_on_expired_enrolment(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);

        $now = time();
        $generator->enrol_user(
            (int)$teacher->id,
            (int)$course->id,
            (int)$editingteacher->id,
            'manual',
            $now + (10 * DAYSECS),
            $now - DAYSECS,
            ENROL_USER_ACTIVE
        );

        $options = manager::get_course_options_for_teacher((int)$teacher->id, false);
        $this->assertArrayHasKey((int)$course->id, $options);

        $before = time();
        $summary = manager::extend_for_teacher(
            (int)$teacher->id,
            (int)$teacher->id,
            [(int)$course->id],
            30,
            true
        );
        $after = time();

        $this->assertSame(1, (int)$summary['enrolmentsupdated']);

        $sql = "SELECT ue.timestart, ue.timeend, ue.status
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid = :courseid";
        $ue = $DB->get_record_sql($sql, ['userid' => (int)$teacher->id, 'courseid' => (int)$course->id], MUST_EXIST);

        $this->assertSame(ENROL_USER_ACTIVE, (int)$ue->status);
        $this->assertGreaterThanOrEqual($before, (int)$ue->timestart);
        $this->assertLessThanOrEqual($after + 1, (int)$ue->timestart);
        $this->assertGreaterThan($after, (int)$ue->timeend);
    }

    /**
     * Expired teacher course should remain listed even if another active enrolment exists.
     *
     * @return void
     */
    public function test_teacher_course_options_include_expired_even_with_active_record(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);

        $now = time();
        $generator->enrol_user(
            (int)$teacher->id,
            (int)$course->id,
            (int)$editingteacher->id,
            'manual',
            $now - (30 * DAYSECS),
            $now - DAYSECS,
            ENROL_USER_ACTIVE
        );

        // Add a second, still-active enrolment through a different enrol method. The manual plugin
        // permits only one instance per course, so use self enrolment for the additional instance.
        $self = enrol_get_plugin('self');
        $this->assertNotFalse($self);

        $secondinstanceid = $self->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => (int)$editingteacher->id,
            'name' => 'Secondary teacher instance',
        ]);
        $secondinstance = $DB->get_record('enrol', ['id' => $secondinstanceid], '*', MUST_EXIST);

        $self->enrol_user(
            $secondinstance,
            (int)$teacher->id,
            (int)$editingteacher->id,
            $now - DAYSECS,
            $now + (10 * DAYSECS),
            ENROL_USER_ACTIVE
        );

        $options = manager::get_course_options_for_teacher((int)$teacher->id, false);
        $this->assertArrayHasKey((int)$course->id, $options);
        $this->assertStringContainsString((string)$course->shortname, $options[(int)$course->id]);
        $this->assertStringContainsString((string)$course->fullname, $options[(int)$course->id]);
    }

    /**
     * Suspended teacher enrolments should not appear in the course chooser.
     *
     * @return void
     */
    public function test_suspended_teacher_courses_are_hidden_from_course_options(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);

        $now = time();
        $generator->enrol_user(
            (int)$teacher->id,
            (int)$course->id,
            (int)$editingteacher->id,
            'manual',
            $now - (10 * DAYSECS),
            $now - DAYSECS,
            ENROL_USER_SUSPENDED
        );

        $options = manager::get_course_options_for_teacher((int)$teacher->id, true);
        $this->assertArrayNotHasKey((int)$course->id, $options);
    }

    /**
     * Active teacher enrolments should be searchable and classified without being extended.
     *
     * @return void
     */
    public function test_active_teacher_course_is_searchable_and_classified(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $activecourse = $generator->create_course();
        $expiredcourse = $generator->create_course();
        $teacher = $generator->create_user();
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);

        $now = time();
        $activeend = $now + (30 * DAYSECS);
        $generator->enrol_user(
            (int)$teacher->id,
            (int)$activecourse->id,
            (int)$editingteacher->id,
            'manual',
            $now - DAYSECS,
            $activeend,
            ENROL_USER_ACTIVE
        );
        $generator->enrol_user(
            (int)$teacher->id,
            (int)$expiredcourse->id,
            (int)$editingteacher->id,
            'manual',
            $now - (30 * DAYSECS),
            $now - DAYSECS,
            ENROL_USER_ACTIVE
        );

        $expiredonly = manager::get_course_options_for_teacher((int)$teacher->id, false);
        $this->assertArrayNotHasKey((int)$activecourse->id, $expiredonly);
        $this->assertArrayHasKey((int)$expiredcourse->id, $expiredonly);

        $searchable = manager::get_course_options_for_teacher((int)$teacher->id, true);
        $this->assertArrayHasKey((int)$activecourse->id, $searchable);
        $this->assertArrayHasKey((int)$expiredcourse->id, $searchable);

        $classified = manager::classify_teacher_selected_courses((int)$teacher->id, [
            (int)$activecourse->id,
            (int)$expiredcourse->id,
        ]);
        $this->assertSame([(int)$expiredcourse->id], $classified['expiredcourseids']);
        $this->assertCount(1, $classified['activecourses']);
        $this->assertSame((int)$activecourse->id, (int)$classified['activecourses'][0]['courseid']);
        $this->assertSame($activeend, (int)$classified['activecourses'][0]['timeend']);
    }

    /**
     * Admin course chooser is scoped to the selected faculty member.
     *
     * @return void
     */
    public function test_admin_course_options_are_scoped_to_selected_teacher(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $unrelatedcourse = $generator->create_course();
        $assignedcourse = $generator->create_course();
        $teacher = $generator->create_user();
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);

        $generator->role_assign(
            (int)$editingteacher->id,
            (int)$teacher->id,
            \context_course::instance((int)$assignedcourse->id)->id
        );
        $generator->enrol_user(
            (int)$teacher->id,
            (int)$assignedcourse->id,
            (int)$editingteacher->id,
            'manual',
            time() - DAYSECS,
            time() + DAYSECS,
            ENROL_USER_ACTIVE
        );

        $options = manager::get_course_options_for_admin((int)$teacher->id);
        $this->assertArrayHasKey((int)$assignedcourse->id, $options);
        $this->assertArrayNotHasKey((int)$unrelatedcourse->id, $options);
        $this->assertSame([], manager::get_course_options_for_admin(0));
    }

    /**
     * Admin faculty picker should include users with teacher role assigned at category context.
     *
     * @return void
     */
    public function test_admin_teacher_options_include_category_context_teachers(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);
        $teacher = $generator->create_user();
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);
        $generator->role_assign((int)$editingteacher->id, (int)$teacher->id, context_coursecat::instance((int)$category->id)->id);

        $options = manager::get_teacher_options_for_admin();
        $this->assertArrayHasKey((int)$teacher->id, $options);
    }

    /**
     * Category-level editingteacher archetype roles should satisfy self-service access checks.
     *
     * @return void
     */
    public function test_category_context_editingteacher_can_access_self_service(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $teacher = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id', MUST_EXIST);
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);

        $generator->role_assign((int)$editingteacher->id, (int)$teacher->id, context_coursecat::instance((int)$category->id)->id);
        $generator->enrol_user(
            (int)$teacher->id,
            (int)$course->id,
            (int)$studentrole->id,
            'manual',
            time() - (30 * DAYSECS),
            time() - DAYSECS,
            ENROL_USER_ACTIVE
        );

        $this->setUser($teacher);

        $this->assertTrue(manager::can_access(\context_system::instance()));
        $options = manager::get_course_options_for_teacher((int)$teacher->id, false);
        $this->assertArrayHasKey((int)$course->id, $options);
        $this->assertStringContainsString((string)$course->shortname, $options[(int)$course->id]);
    }

    /**
     * Admin faculty picker can include the current user as default without relying on duplicate SQL placeholders.
     *
     * @return void
     */
    public function test_admin_teacher_options_can_include_current_user_default(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $admin = $generator->create_user();

        $options = manager::get_teacher_options_for_admin((int)$admin->id);
        $this->assertArrayHasKey((int)$admin->id, $options);
    }

    /**
     * Domain allowlist parser should tolerate admin-friendly separators and leading @.
     *
     * @return void
     */
    public function test_normalize_email_domains_accepts_common_admin_input(): void {
        $domains = manager::normalize_email_domains(" @Example.org,\nexample.edu; invalid@value moodle.org ");

        $this->assertSame(['example.org', 'example.edu', 'moodle.org'], $domains);
    }

    /**
     * A matching email domain must not, on its own, grant tool access or the navigation shortcut.
     *
     * Email-domain matching is a legacy setting. The domain matcher utility still recognises the
     * configured domains, but self-service access is governed solely by holding a teacher role.
     *
     * @return void
     */
    public function test_email_domain_match_does_not_grant_self_service_access(): void {
        $this->resetAfterTest(true);

        set_config('showmenuforteacherrole', 0, 'tool_enrolreactivate');
        set_config('showmenuforemaildomain', 1, 'tool_enrolreactivate');
        set_config('menuemaildomains', "example.org\nexample.edu", 'tool_enrolreactivate');

        $user = $this->getDataGenerator()->create_user(['email' => 'Faculty.Member@Example.org']);
        $this->setUser($user);

        // The domain matcher utility still recognises the configured domains...
        $this->assertTrue(manager::user_matches_allowed_email_domain($user));

        // ...but a domain match alone does not grant tool access or the navigation shortcut: this
        // user holds no teacher role, so self-service access is denied.
        $this->assertFalse(manager::can_access(context_system::instance()));
        $this->assertFalse(manager::should_show_menu_entry($user, context_system::instance()));
    }
}
