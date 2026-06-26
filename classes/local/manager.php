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

namespace tool_enrolreactivate\local;

use context_course;
use context_system;
use core_text;
use stdClass;
use xmldb_table;

/**
 * Core service manager for tool_enrolreactivate.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager {
    /**
     * Minimum permitted extension in days.
     */
    private const MIN_DAYS = 1;

    /**
     * Default fallback extension in days.
     */
    private const DEFAULT_DAYS = 120;

    /**
     * Default fallback maximum extension in days.
     */
    private const DEFAULT_MAX_DAYS = 3650;

    /**
     * Return whether current user can manage all users/courses via this tool.
     *
     * @param context_system $systemcontext
     * @return bool
     */
    public static function can_manage_all(context_system $systemcontext): bool {
        return is_siteadmin()
            || has_capability('tool/enrolreactivate:manageall', $systemcontext)
            || has_capability('moodle/site:config', $systemcontext);
    }

    /**
     * Return whether current user can open this plugin's settings page.
     *
     * @param context_system $systemcontext
     * @return bool
     */
    public static function can_manage_settings(context_system $systemcontext): bool {
        return is_siteadmin() || has_capability('moodle/site:config', $systemcontext);
    }

    /**
     * Return whether current user can access this tool.
     *
     * @param context_system $systemcontext
     * @return bool
     */
    public static function can_access(context_system $systemcontext): bool {
        global $USER;

        return is_siteadmin()
            || has_capability('tool/enrolreactivate:view', $systemcontext)
            || self::can_manage_all($systemcontext)
            || self::user_matches_self_service_policy($USER);
    }

    /**
     * Return whether user-facing navigation shortcuts should be shown.
     *
     * @return bool
     */
    public static function show_menu_entry_enabled(): bool {
        $configured = self::config_value('showmenuentry');
        if ($configured === false) {
            return true;
        }

        return !empty($configured);
    }

    /**
     * Return whether a user-facing navigation shortcut should be shown.
     *
     * The shortcut policy is deliberately separate from the update policy. A
     * matching email domain can reveal the entry point, but submitted actions
     * are still scoped later to the current user's own eligible course records
     * unless the user has manage-all authority.
     *
     * @param stdClass|null $user
     * @param context_system|null $systemcontext
     * @return bool
     */
    public static function should_show_menu_entry(?stdClass $user = null, ?context_system $systemcontext = null): bool {
        global $USER;

        if (!self::show_menu_entry_enabled()) {
            return false;
        }

        $user = $user ?? $USER;
        $systemcontext = $systemcontext ?? context_system::instance();
        $iscurrentuser = (int)($user->id ?? 0) === (int)($USER->id ?? 0);

        if (
            is_siteadmin((int)$user->id) || ($iscurrentuser && self::can_manage_all($systemcontext))
                || has_capability('tool/enrolreactivate:view', $systemcontext, $user)
        ) {
            return true;
        }

        return self::user_matches_self_service_policy($user);
    }

    /**
     * Normalize positive integer list.
     *
     * @param array $values
     * @return int[]
     */
    public static function normalize_int_values(array $values): array {
        $normalized = [];
        foreach ($values as $value) {
            $intvalue = (int)$value;
            if ($intvalue > 0) {
                $normalized[$intvalue] = $intvalue;
            }
        }
        return array_values($normalized);
    }

    /**
     * Return configured default extension days.
     *
     * @return int
     */
    public static function default_days(): int {
        $configured = (int)self::config_value('defaultdays');
        if ($configured < self::MIN_DAYS) {
            $configured = self::DEFAULT_DAYS;
        }

        return min($configured, self::max_days());
    }

    /**
     * Return configured maximum extension days.
     *
     * @return int
     */
    public static function max_days(): int {
        $configured = (int)self::config_value('maxdays');
        if ($configured < self::MIN_DAYS) {
            $configured = self::DEFAULT_MAX_DAYS;
        }

        return $configured;
    }

    /**
     * Normalize submitted extension days to valid range.
     *
     * @param int $days
     * @return int
     */
    public static function normalize_days(int $days): int {
        if ($days < self::MIN_DAYS) {
            $days = self::default_days();
        }

        return min($days, self::max_days());
    }

    /**
     * Normalize a configured email-domain allowlist.
     *
     * Supports comma, semicolon, whitespace, or newline separated values. A
     * leading @ is optional in the admin setting.
     *
     * @param string $rawdomains
     * @return string[]
     */
    public static function normalize_email_domains(string $rawdomains): array {
        $domains = [];
        foreach (preg_split('/[\s,;]+/', core_text::strtolower($rawdomains), -1, PREG_SPLIT_NO_EMPTY) as $domain) {
            $domain = trim($domain);
            $domain = ltrim($domain, '@');
            if ($domain === '' || strpos($domain, '@') !== false || strpos($domain, '.') === false) {
                continue;
            }
            $domains[$domain] = $domain;
        }

        return array_values($domains);
    }

    /**
     * Return whether a user email domain matches the configured allowlist.
     *
     * @param stdClass $user
     * @return bool
     */
    public static function user_matches_allowed_email_domain(stdClass $user): bool {
        $email = trim(core_text::strtolower((string)($user->email ?? '')));
        $atpos = strrpos($email, '@');
        if ($atpos === false) {
            return false;
        }

        $domain = substr($email, $atpos + 1);
        if ($domain === '') {
            return false;
        }

        $alloweddomains = self::normalize_email_domains((string)self::config_value('menuemaildomains'));
        return in_array($domain, $alloweddomains, true);
    }

    /**
     * Build faculty options for admin filters/actions.
     *
     * @param int $includeuserid Optional user id to force-include in the result (e.g. the logged-in admin).
     * @return array<int, string>
     */
    public static function get_teacher_options_for_admin(int $includeuserid = 0): array {
        global $DB;

        $teacherroleids = self::get_teacher_role_ids();
        if (empty($teacherroleids)) {
            return self::add_user_option_if_missing([], $includeuserid);
        }

        [$roleassignsql, $roleassignparams] = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'trarole');
        [$enrolrolesql, $enrolroleparams] = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'terole');
        [$contextsql, $contextparams] = $DB->get_in_or_equal(
            [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE],
            SQL_PARAMS_NAMED,
            'ctxlvl'
        );
        $sql = "SELECT DISTINCT u.id, u.username, u.email, u.firstname, u.lastname, u.middlename, u.alternatename,
                               u.firstnamephonetic, u.lastnamephonetic
                  FROM {user} u
                  JOIN (
                        SELECT DISTINCT ra.userid
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON ctx.id = ra.contextid
                         WHERE ctx.contextlevel {$contextsql}
                           AND ra.roleid {$roleassignsql}

                         UNION

                        SELECT DISTINCT ue.userid
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                          JOIN {course} c ON c.id = e.courseid
                         WHERE c.id <> :siteid
                           AND e.roleid {$enrolrolesql}
                       ) teacherids ON teacherids.userid = u.id
                 WHERE u.deleted = 0
                   AND u.suspended = 0
              ORDER BY u.lastname ASC, u.firstname ASC";
        $params = ['siteid' => SITEID] + $contextparams + $roleassignparams + $enrolroleparams;
        $records = $DB->get_records_sql($sql, $params);

        $options = [];
        foreach ($records as $record) {
            $options[(int)$record->id] = self::user_identity_text($record);
        }
        $options = self::add_user_option_if_missing($options, $includeuserid);

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }

    /**
     * Build course options for teacher self-service.
     *
     * Suspended teacher enrolments are intentionally hidden from the chooser.
     *
     * @param int $teacherid
     * @param bool $includeactive
     * @return array<int, string>
     */
    public static function get_course_options_for_teacher(int $teacherid, bool $includeactive = false): array {
        $statusmap = self::get_teacher_course_status_map($teacherid);
        $options = [];

        foreach ($statusmap as $courseid => $status) {
            $include = false;
            if ($includeactive) {
                $include = !empty($status['hasactive']) || !empty($status['hasexpired']);
            } else {
                $include = !empty($status['hasexpired']);
            }

            if (!$include) {
                continue;
            }

            $options[$courseid] = self::course_option_text((object)$status);
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }

    /**
     * Classify selected teacher courses for action feedback.
     *
     * @param int $teacherid
     * @param int[] $courseids
     * @return array{expiredcourseids: int[], activecourses: array<int, array<string, mixed>>, invalidcourseids: int[]}
     */
    public static function classify_teacher_selected_courses(int $teacherid, array $courseids): array {
        $courseids = self::normalize_int_values($courseids);
        $statusmap = self::get_teacher_course_status_map($teacherid);
        $result = [
            'expiredcourseids' => [],
            'activecourses' => [],
            'invalidcourseids' => [],
        ];

        foreach ($courseids as $courseid) {
            if (!array_key_exists($courseid, $statusmap)) {
                $result['invalidcourseids'][] = $courseid;
                continue;
            }

            $status = $statusmap[$courseid];
            if (!empty($status['hasexpired'])) {
                $result['expiredcourseids'][] = $courseid;
                continue;
            }

            if (!empty($status['hasactive'])) {
                $result['activecourses'][] = [
                    'courseid' => $courseid,
                    'label' => self::course_option_text((object)$status),
                    'timeend' => (int)($status['activeend'] ?? 0),
                ];
                continue;
            }

            $result['invalidcourseids'][] = $courseid;
        }

        return $result;
    }

    /**
     * Build course options for an admin-selected faculty member.
     *
     * The admin UI deliberately remains faculty-scoped: without a selected
     * faculty member there is no safe course list to show.
     *
     * @param int $teacherid
     * @return array<int, string>
     */
    public static function get_course_options_for_admin(int $teacherid = 0): array {
        if ($teacherid <= 0) {
            return [];
        }

        return self::get_course_options_for_teacher($teacherid, true);
    }

    /**
     * Return teacher user IDs mapped to selected courses.
     *
     * This follows the same course-visibility rules as the chooser so teachers
     * whose only enrolment state is suspended are left out.
     *
     * @param int[] $courseids
     * @return int[]
     */
    public static function get_teacher_ids_for_courses(array $courseids): array {
        $courseids = self::normalize_int_values($courseids);
        if (empty($courseids)) {
            return [];
        }

        $courseset = array_fill_keys($courseids, true);
        $teacherids = [];
        foreach (array_keys(self::get_teacher_options_for_admin()) as $teacherid) {
            $courseoptions = self::get_course_options_for_teacher((int)$teacherid, true);
            foreach (array_keys($courseoptions) as $courseid) {
                if (isset($courseset[(int)$courseid])) {
                    $teacherids[(int)$teacherid] = (int)$teacherid;
                    break;
                }
            }
        }

        return array_values($teacherids);
    }

    /**
     * Extend enrolment windows for one teacher across selected courses.
     *
     * @param int $actorid User performing the action.
     * @param int $teacherid Target teacher.
     * @param int[] $courseids Target courses.
     * @param int $extensiondays Number of days to extend.
     * @param bool $expiredonly True to update only already-expired enrolments.
     * @return array
     */
    public static function extend_for_teacher(
        int $actorid,
        int $teacherid,
        array $courseids,
        int $extensiondays,
        bool $expiredonly
    ): array {
        global $DB;

        $summary = [
            'teacherid' => $teacherid,
            'selectedcourses' => 0,
            'coursesupdated' => 0,
            'enrolmentsupdated' => 0,
            'enrolmentscreated' => 0,
            'rolesrestored' => 0,
            'skippedinactive' => 0,
            'skippedsuspended' => 0,
            'skippedinfinite' => 0,
            'skippednotexpired' => 0,
            'skippednomatch' => 0,
            'errors' => 0,
        ];

        if ($teacherid <= 0) {
            return $summary;
        }

        $extensiondays = self::normalize_days($extensiondays);
        $courseids = self::normalize_int_values($courseids);
        if (empty($courseids)) {
            return $summary;
        }

        $teacherroleids = self::get_teacher_role_ids();
        if (empty($teacherroleids)) {
            $summary['errors'] = count($courseids);
            return $summary;
        }
        $teacherroleset = array_fill_keys($teacherroleids, true);

        $courseids = array_values(array_filter($courseids, function (int $courseid) use ($teacherid, $teacherroleset): bool {
            return self::course_is_eligible_for_restore($teacherid, $courseid, $teacherroleset);
        }));
        if (empty($courseids)) {
            return $summary;
        }
        $summary['selectedcourses'] = count($courseids);

        $enrolcache = [];
        $now = time();
        foreach ($courseids as $courseid) {
            $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
            if (!$coursecontext) {
                $summary['errors']++;
                continue;
            }

            $assignedteacherroleids = self::get_teacher_role_ids_in_course($coursecontext, $teacherid, $teacherroleset);
            $allrows = self::get_course_enrolment_rows($teacherid, $courseid);
            $candidates = self::select_teacher_enrolment_candidates($allrows, $assignedteacherroleids, $teacherroleset);

            $coursechanged = false;
            if (!empty($candidates)) {
                foreach ($candidates as $candidate) {
                    $userstatus = (int)$candidate->uestatus;
                    if ($userstatus === ENROL_USER_SUSPENDED) {
                        $summary['skippedsuspended']++;
                        continue;
                    }
                    if ($userstatus !== ENROL_USER_ACTIVE) {
                        $summary['skippedinactive']++;
                        continue;
                    }

                    $oldtimeend = (int)$candidate->timeend;
                    if ($oldtimeend <= 0) {
                        $summary['skippedinfinite']++;
                        continue;
                    }

                    $isexpired = $oldtimeend < $now;
                    if ($expiredonly && !$isexpired) {
                        $summary['skippednotexpired']++;
                        continue;
                    }

                    $enrolinstance = self::get_enrol_instance((int)$candidate->enrolid, $enrolcache);
                    if (!$enrolinstance) {
                        $summary['errors']++;
                        self::log_action(
                            $actorid,
                            $teacherid,
                            $courseid,
                            (int)$candidate->enrolid,
                            (int)$candidate->ueid,
                            $extensiondays,
                            $oldtimeend,
                            max($oldtimeend, $now) + ($extensiondays * DAYSECS),
                            'error',
                            'Enrol instance not found.',
                        );
                        continue;
                    }

                    $plugin = enrol_get_plugin((string)$enrolinstance->enrol);
                    if (!$plugin) {
                        $summary['errors']++;
                        self::log_action(
                            $actorid,
                            $teacherid,
                            $courseid,
                            (int)$candidate->enrolid,
                            (int)$candidate->ueid,
                            $extensiondays,
                            $oldtimeend,
                            max($oldtimeend, $now) + ($extensiondays * DAYSECS),
                            'error',
                            'Enrol plugin not found.',
                        );
                        continue;
                    }

                    $restoreroleid = 0;
                    if (empty($assignedteacherroleids)) {
                        $restoreroleid = self::get_preferred_teacher_role_id(
                            array_filter([(int)$candidate->roleid], static function (int $roleid) use ($teacherroleset): bool {
                                return isset($teacherroleset[$roleid]);
                            })
                        );
                        if ($restoreroleid <= 0) {
                            $restoreroleid = self::get_preferred_teacher_role_id($teacherroleids);
                        }
                    }

                    $oldtimestart = (int)$candidate->timestart;
                    $newtimestart = ($oldtimestart > 0 && $oldtimestart <= $now) ? $oldtimestart : $now;
                    $newtimeend = max($oldtimeend, $now) + ($extensiondays * DAYSECS);

                    try {
                        if ($restoreroleid > 0) {
                            self::assign_teacher_role($enrolinstance, $teacherid, $restoreroleid, $coursecontext);
                            $assignedteacherroleids[] = $restoreroleid;
                            $summary['rolesrestored']++;
                        }

                        $plugin->update_user_enrol(
                            $enrolinstance,
                            $teacherid,
                            ENROL_USER_ACTIVE,
                            $newtimestart,
                            $newtimeend,
                        );
                        $summary['enrolmentsupdated']++;
                        $coursechanged = true;

                        self::log_action(
                            $actorid,
                            $teacherid,
                            $courseid,
                            (int)$candidate->enrolid,
                            (int)$candidate->ueid,
                            $extensiondays,
                            $oldtimeend,
                            $newtimeend,
                            'updated',
                            '',
                        );
                    } catch (\Throwable $e) {
                        $summary['errors']++;
                        self::log_action(
                            $actorid,
                            $teacherid,
                            $courseid,
                            (int)$candidate->enrolid,
                            (int)$candidate->ueid,
                            $extensiondays,
                            $oldtimeend,
                            $newtimeend,
                            'error',
                            $e->getMessage(),
                        );
                    }
                }
            } else {
                $manualinstance = self::find_manual_instance_for_restore($courseid, $teacherroleset);
                $preferredroleid = self::get_preferred_teacher_role_id($assignedteacherroleids);
                $roleidfornewenrolment = empty($assignedteacherroleids)
                    ? ($preferredroleid > 0 ? $preferredroleid : self::get_preferred_teacher_role_id($teacherroleids))
                    : 0;

                if (!$manualinstance || ($roleidfornewenrolment <= 0 && empty($assignedteacherroleids))) {
                    $summary['skippednomatch']++;
                    self::log_action(
                        $actorid,
                        $teacherid,
                        $courseid,
                        $manualinstance ? (int)$manualinstance->id : 0,
                        0,
                        $extensiondays,
                        0,
                        $now + ($extensiondays * DAYSECS),
                        'skipped',
                        'No eligible teacher enrolment path was found for this course.',
                    );
                } else {
                    $plugin = enrol_get_plugin('manual');
                    if (!$plugin) {
                        $summary['errors']++;
                        self::log_action(
                            $actorid,
                            $teacherid,
                            $courseid,
                            (int)$manualinstance->id,
                            0,
                            $extensiondays,
                            0,
                            $now + ($extensiondays * DAYSECS),
                            'error',
                            'Manual enrol plugin not found.',
                        );
                    } else {
                        try {
                            $plugin->enrol_user(
                                $manualinstance,
                                $teacherid,
                                $roleidfornewenrolment,
                                $now,
                                $now + ($extensiondays * DAYSECS),
                                ENROL_USER_ACTIVE,
                            );
                            $summary['enrolmentscreated']++;
                            $summary['enrolmentsupdated']++;
                            if ($roleidfornewenrolment > 0) {
                                $summary['rolesrestored']++;
                            }
                            $coursechanged = true;

                            $ue = $DB->get_record(
                                'user_enrolments',
                                ['enrolid' => $manualinstance->id, 'userid' => $teacherid],
                                'id',
                                IGNORE_MISSING
                            );
                            self::log_action(
                                $actorid,
                                $teacherid,
                                $courseid,
                                (int)$manualinstance->id,
                                $ue ? (int)$ue->id : 0,
                                $extensiondays,
                                0,
                                $now + ($extensiondays * DAYSECS),
                                'created',
                                '',
                            );
                        } catch (\Throwable $e) {
                            $summary['errors']++;
                            self::log_action(
                                $actorid,
                                $teacherid,
                                $courseid,
                                (int)$manualinstance->id,
                                0,
                                $extensiondays,
                                0,
                                $now + ($extensiondays * DAYSECS),
                                'error',
                                $e->getMessage(),
                            );
                        }
                    }
                }
            }

            if ($coursechanged) {
                $summary['coursesupdated']++;
            }
        }

        return $summary;
    }

    /**
     * Convert a user record to "Full Name (username, email)" format.
     *
     * @param stdClass $record
     * @return string
     */
    public static function user_identity_text(stdClass $record): string {
        $name = fullname($record);
        $details = [];

        $username = trim((string)($record->username ?? ''));
        if ($username !== '') {
            $details[] = $username;
        }

        $email = trim((string)($record->email ?? ''));
        if ($email !== '') {
            $details[] = $email;
        }

        if (empty($details)) {
            return $name;
        }

        return $name . ' (' . implode(', ', $details) . ')';
    }

    /**
     * Convert a course record to searchable "shortname - fullname" option text.
     *
     * Moodle autocomplete searches the rendered option text. Including both
     * short name and full name makes partial searches work for class numbers
     * and descriptive course names without adding database-specific search SQL.
     *
     * @param stdClass $course
     * @return string
     */
    public static function course_option_text(stdClass $course): string {
        $shortname = trim(format_string((string)($course->shortname ?? '')));
        $fullname = trim(format_string((string)($course->fullname ?? '')));

        if ($shortname === '') {
            return $fullname;
        }
        if ($fullname === '' || core_text::strtolower($shortname) === core_text::strtolower($fullname)) {
            return $shortname;
        }

        return $shortname . ' - ' . $fullname;
    }

    /**
     * Add a user to an option list when an admin default must point at the logged-in user.
     *
     * @param array $options Existing options.
     * @param int $userid User id to include.
     * @return array<int, string>
     */
    private static function add_user_option_if_missing(array $options, int $userid): array {
        global $DB;

        if ($userid <= 0 || array_key_exists($userid, $options)) {
            return $options;
        }

        $record = $DB->get_record(
            'user',
            [
            'id' => $userid,
            'deleted' => 0,
            'suspended' => 0,
            ],
            'id, username, email, firstname, lastname, middlename, alternatename, firstnamephonetic, lastnamephonetic',
            IGNORE_MISSING
        );
        if ($record) {
            $options[(int)$record->id] = self::user_identity_text($record);
        }

        return $options;
    }

    /**
     * Resolve all role IDs mapped to teacher archetypes.
     *
     * @return int[]
     */
    public static function get_teacher_role_ids(): array {
        $teacherroleids = [];
        foreach (['editingteacher', 'teacher'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                $teacherroleids[(int)$role->id] = (int)$role->id;
            }
        }

        return array_values($teacherroleids);
    }

    /**
     * Build per-course enrolment status map for a teacher.
     *
     * Suspended teacher enrolments are excluded from the resulting map so they
     * do not appear in the chooser.
     *
     * @param int $teacherid
     * @return array<int, array<string, mixed>>
     */
    private static function get_teacher_course_status_map(int $teacherid): array {
        global $DB;

        if ($teacherid <= 0) {
            return [];
        }

        $teacherroleids = self::get_teacher_role_ids();
        if (empty($teacherroleids)) {
            return [];
        }

        // Key the result set by the unique user-enrolment id (ue.id): a teacher may hold more than one
        // enrolment in the same course (for example an expired manual enrolment alongside an active
        // self enrolment), and get_records_sql() indexes by the first column, so a course id first
        // column would collapse those rows and drop all but one enrolment.
        $sql = "SELECT ue.id AS ueid, c.id AS courseid, c.shortname, c.fullname, ue.timestart, ue.timeend,
                       ue.status AS uestatus, e.status AS estatus, e.roleid AS eroleid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE ue.userid = :teacherid
                   AND c.id <> :siteid
              ORDER BY c.fullname ASC";
        $records = $DB->get_records_sql($sql, ['teacherid' => $teacherid, 'siteid' => SITEID]);

        $now = time();
        $statusmap = [];
        $teacherroleset = array_fill_keys($teacherroleids, true);
        $courserolecache = [];
        foreach ($records as $record) {
            $courseid = (int)$record->courseid;
            if (!array_key_exists($courseid, $courserolecache)) {
                $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
                $courserolecache[$courseid] = $coursecontext
                    ? self::get_teacher_role_ids_in_course($coursecontext, $teacherid, $teacherroleset)
                    : [];
            }

            if (empty($courserolecache[$courseid])) {
                $enrolroleid = (int)($record->eroleid ?? 0);
                if ($enrolroleid <= 0 || !isset($teacherroleset[$enrolroleid])) {
                    continue;
                }
            }

            if (empty($record->ueid)) {
                continue;
            }
            if ((int)$record->estatus !== ENROL_INSTANCE_ENABLED) {
                continue;
            }
            if ((int)$record->uestatus === ENROL_USER_SUSPENDED) {
                continue;
            }
            if ((int)$record->uestatus !== ENROL_USER_ACTIVE) {
                continue;
            }

            if (!array_key_exists($courseid, $statusmap)) {
                $statusmap[$courseid] = [
                    'shortname' => (string)$record->shortname,
                    'fullname' => (string)$record->fullname,
                    'hasactive' => false,
                    'hasexpired' => false,
                    'activeend' => 0,
                    'activeunlimited' => false,
                ];
            }

            $timeend = (int)$record->timeend;
            if ($timeend > 0 && $timeend < $now) {
                $statusmap[$courseid]['hasexpired'] = true;
                continue;
            }

            $timestart = (int)$record->timestart;
            if ($timestart > 0 && $timestart > $now) {
                continue;
            }

            $statusmap[$courseid]['hasactive'] = true;
            if ($timeend === 0) {
                $statusmap[$courseid]['activeend'] = 0;
                $statusmap[$courseid]['activeunlimited'] = true;
            } else if (empty($statusmap[$courseid]['activeunlimited']) && (int)$statusmap[$courseid]['activeend'] !== 0) {
                $statusmap[$courseid]['activeend'] = max((int)$statusmap[$courseid]['activeend'], $timeend);
            } else if (empty($statusmap[$courseid]['activeunlimited'])) {
                $statusmap[$courseid]['activeend'] = $timeend;
            }
        }

        return $statusmap;
    }

    /**
     * Return true when user holds teacher archetype role in at least one course.
     *
     * @param int $userid
     * @return bool
     */
    private static function user_has_teacher_role_in_any_course(int $userid): bool {
        global $DB;

        if ($userid <= 0) {
            return false;
        }

        $teacherroleids = self::get_teacher_role_ids();
        if (empty($teacherroleids)) {
            return false;
        }
        $teacherroleset = array_fill_keys($teacherroleids, true);

        $courses = enrol_get_users_courses($userid, false, 'id');
        foreach ($courses as $course) {
            $courseid = (int)$course->id;
            if ($courseid <= 0 || $courseid === SITEID) {
                continue;
            }
            $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
            if (!$coursecontext) {
                continue;
            }
            if (!empty(self::get_teacher_role_ids_in_course($coursecontext, $userid, $teacherroleset))) {
                return true;
            }
        }

        [$rolesql, $roleparams] = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'trole');
        [$contextsql, $contextparams] = $DB->get_in_or_equal(
            [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE],
            SQL_PARAMS_NAMED,
            'tctx'
        );
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ra.roleid {$rolesql}
                   AND ctx.contextlevel {$contextsql}";
        $params = [
            'userid' => $userid,
        ] + $roleparams + $contextparams;

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Return whether a user matches the configured self-service visibility/access policy.
     *
     * @param stdClass $user
     * @return bool
     */
    private static function user_matches_self_service_policy(stdClass $user): bool {
        $userid = (int)($user->id ?? 0);
        if ($userid <= 0) {
            return false;
        }

        return self::menu_entry_teacher_role_enabled()
            && self::user_has_teacher_role_in_any_course($userid);
    }

    /**
     * Return whether the teacher-role shortcut rule is enabled.
     *
     * @return bool
     */
    private static function menu_entry_teacher_role_enabled(): bool {
        $configured = self::config_value('showmenuforteacherrole');
        if ($configured === false) {
            return true;
        }

        return !empty($configured);
    }

    /**
     * Return whether a course still has enough teacher-related state to be eligible for restore.
     *
     * @param int $teacherid
     * @param int $courseid
     * @param array $teacherroleset
     * @return bool
     */
    private static function course_is_eligible_for_restore(int $teacherid, int $courseid, array $teacherroleset): bool {
        if ($teacherid <= 0 || $courseid <= 0) {
            return false;
        }

        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        if ($coursecontext && !empty(self::get_teacher_role_ids_in_course($coursecontext, $teacherid, $teacherroleset))) {
            return true;
        }

        return !empty(self::get_course_enrolment_rows($teacherid, $courseid));
    }

    /**
     * Return teacher role IDs assigned to a user in a course context.
     *
     * @param context_course $coursecontext
     * @param int $userid
     * @param array $teacherroleset
     * @return int[]
     */
    private static function get_teacher_role_ids_in_course(
        context_course $coursecontext,
        int $userid,
        array $teacherroleset
    ): array {
        $roleids = [];
        foreach (get_user_roles($coursecontext, $userid, true) as $assignment) {
            $roleid = (int)($assignment->roleid ?? 0);
            if ($roleid > 0 && isset($teacherroleset[$roleid])) {
                $roleids[$roleid] = $roleid;
            }
        }

        return array_values($roleids);
    }

    /**
     * Return teacher-granting enrolment rows for a user in a course.
     *
     * @param int $teacherid
     * @param int $courseid
     * @return array
     */
    private static function get_course_enrolment_rows(int $teacherid, int $courseid): array {
        global $DB;

        if ($teacherid <= 0 || $courseid <= 0) {
            return [];
        }

        $sql = "SELECT ue.id AS ueid, ue.userid, ue.enrolid, ue.timestart, ue.timeend, ue.status AS uestatus,
                       e.id AS eid, e.courseid, e.enrol, e.status AS estatus, e.roleid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :teacherid
                   AND e.courseid = :courseid
                   AND e.status = :enabled
              ORDER BY e.sortorder ASC, e.id ASC, ue.id ASC";
        $params = [
            'teacherid' => $teacherid,
            'courseid' => $courseid,
            'enabled' => ENROL_INSTANCE_ENABLED,
        ];

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Choose the safest enrolment rows to update for teacher access restoration.
     *
     * @param array $rows
     * @param int[] $assignedteacherroleids
     * @param array $teacherroleset
     * @return array
     */
    private static function select_teacher_enrolment_candidates(
        array $rows,
        array $assignedteacherroleids,
        array $teacherroleset
    ): array {
        if (empty($rows)) {
            return [];
        }

        $teacherrolecandidates = array_values(array_filter($rows, static function (stdClass $row) use ($teacherroleset): bool {
            $roleid = (int)($row->roleid ?? 0);
            return $roleid > 0 && isset($teacherroleset[$roleid]);
        }));
        if (!empty($teacherrolecandidates)) {
            return $teacherrolecandidates;
        }

        $manualrows = array_values(array_filter($rows, static function (stdClass $row): bool {
            return (string)($row->enrol ?? '') === 'manual';
        }));

        if (!empty($assignedteacherroleids)) {
            return !empty($manualrows) ? $manualrows : $rows;
        }

        if (count($manualrows) === 1) {
            return $manualrows;
        }

        return [];
    }

    /**
     * Find a suitable enabled manual instance for creating a fresh enrolment.
     *
     * @param int $courseid
     * @param array $teacherroleset
     * @return stdClass|null
     */
    private static function find_manual_instance_for_restore(int $courseid, array $teacherroleset): ?stdClass {
        global $DB;

        $instances = $DB->get_records('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual',
            'status' => ENROL_INSTANCE_ENABLED,
        ], 'sortorder ASC, id ASC');

        if (empty($instances)) {
            return null;
        }

        foreach ($instances as $instance) {
            $roleid = (int)($instance->roleid ?? 0);
            if ($roleid > 0 && isset($teacherroleset[$roleid])) {
                return $instance;
            }
        }

        return reset($instances) ?: null;
    }

    /**
     * Return cached enrol instance or null when missing.
     *
     * @param int $enrolid
     * @param array $cache
     * @return stdClass|null
     */
    private static function get_enrol_instance(int $enrolid, array &$cache): ?stdClass {
        global $DB;

        if (!array_key_exists($enrolid, $cache)) {
            $cache[$enrolid] = $DB->get_record('enrol', ['id' => $enrolid], '*', IGNORE_MISSING) ?: null;
        }

        return $cache[$enrolid];
    }

    /**
     * Return the preferred teacher role ID from a candidate list.
     *
     * @param int[] $candidateids
     * @return int
     */
    private static function get_preferred_teacher_role_id(array $candidateids): int {
        $candidateids = self::normalize_int_values($candidateids);
        if (empty($candidateids)) {
            return 0;
        }

        $preferredorder = [];
        foreach (['editingteacher', 'teacher'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                $preferredorder[(int)$role->id] = (int)$role->id;
            }
        }

        $candidateset = array_fill_keys($candidateids, true);
        foreach ($preferredorder as $roleid) {
            if (isset($candidateset[$roleid])) {
                return $roleid;
            }
        }

        return reset($candidateids) ?: 0;
    }

    /**
     * Restore a teacher role assignment for a specific enrolment instance.
     *
     * @param stdClass $instance
     * @param int $userid
     * @param int $roleid
     * @param context_course $coursecontext
     * @return void
     */
    private static function assign_teacher_role(stdClass $instance, int $userid, int $roleid, context_course $coursecontext): void {
        $plugin = enrol_get_plugin((string)$instance->enrol);
        if ($plugin && $plugin->roles_protected()) {
            role_assign($roleid, $userid, $coursecontext->id, 'enrol_' . $instance->enrol, $instance->id);
            return;
        }

        role_assign($roleid, $userid, $coursecontext->id, '', 0);
    }

    /**
     * Read config from tool plugin, with fallback to the old local plugin.
     *
     * @param string $name
     * @return mixed
     */
    private static function config_value(string $name) {
        $value = get_config('tool_enrolreactivate', $name);
        if ($value !== false) {
            return $value;
        }

        return get_config('local_enrolreactivate', $name);
    }

    /**
     * Write a single audit row when log table exists.
     *
     * @param int $actorid
     * @param int $targetuserid
     * @param int $courseid
     * @param int $enrolid
     * @param int $userenrolmentid
     * @param int $extensiondays
     * @param int $oldtimeend
     * @param int $newtimeend
     * @param string $status
     * @param string $message
     * @return void
     */
    private static function log_action(
        int $actorid,
        int $targetuserid,
        int $courseid,
        int $enrolid,
        int $userenrolmentid,
        int $extensiondays,
        int $oldtimeend,
        int $newtimeend,
        string $status,
        string $message
    ): void {
        global $DB;

        if (!self::log_table_exists()) {
            return;
        }

        $record = (object)[
            'actorid' => $actorid,
            'targetuserid' => $targetuserid,
            'courseid' => $courseid,
            'enrolid' => $enrolid,
            'userenrolmentid' => $userenrolmentid,
            'extensiondays' => $extensiondays,
            'oldtimeend' => $oldtimeend,
            'newtimeend' => $newtimeend,
            'status' => substr($status, 0, 30),
            'message' => $message === '' ? null : $message,
            'timecreated' => time(),
        ];

        try {
            $DB->insert_record('tool_enrolreactivate_log', $record);
        } catch (\Throwable $e) {
            // Best-effort logging only: never disrupt the enrolment action because the audit insert failed.
            debugging('tool_enrolreactivate audit log insert failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Return true when audit log table exists.
     *
     * @return bool
     */
    private static function log_table_exists(): bool {
        global $DB;

        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $dbman = $DB->get_manager();
            $table = new xmldb_table('tool_enrolreactivate_log');
            $cached = $dbman->table_exists($table);
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
    }
}
