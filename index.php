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

/**
 * UI for extending teacher enrolment windows.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/admin/tool/enrolreactivate/classes/form/extend_form.php');

use tool_enrolreactivate\local\manager;

require_login();

$systemcontext = context_system::instance();
if (!manager::can_access($systemcontext)) {
    throw new required_capability_exception($systemcontext, 'tool/enrolreactivate:view', 'nopermissions', '');
}
$canmanageall = manager::can_manage_all($systemcontext);

// Administrators (manage-all) extend access on behalf of a faculty member: they choose the faculty
// member, load that person's courses, then choose the course(s) to extend. The faculty member whose
// courses are loaded comes from a submitted form value first (the "Load courses" no-submit button or
// the Extend submit), then a filterteacherid URL param; an admin starts with nobody selected.
$scopeteacherid = optional_param('teacherid', 0, PARAM_INT);
$requestedteacherid = optional_param('filterteacherid', -1, PARAM_INT);
$selectedteacherid = $canmanageall
    ? ($scopeteacherid > 0 ? $scopeteacherid : ($requestedteacherid > 0 ? $requestedteacherid : 0))
    : (int)$USER->id;
$teacheroptions = $canmanageall ? manager::get_teacher_options_for_admin((int)$USER->id) : [];
if ($canmanageall && $selectedteacherid > 0 && !array_key_exists($selectedteacherid, $teacheroptions)) {
    $selectedteacherid = 0;
}

if ($canmanageall) {
    $courseoptions = manager::get_course_options_for_admin($selectedteacherid);
    $defaultselectedcourseids = [];
} else {
    $courseoptions = manager::get_course_options_for_teacher((int)$USER->id, true);
    $defaultselectedcourseids = array_keys(manager::get_course_options_for_teacher((int)$USER->id, false));
}

$teacheroptionsforform = $teacheroptions;
if ($canmanageall) {
    // Keep an explicit empty option so single-select autocomplete does not auto-pick first faculty.
    $teacheroptionsforform = ['' => ''] + $teacheroptions;
}

$urlparams = [];
if ($canmanageall && $selectedteacherid > 0) {
    $urlparams['filterteacherid'] = $selectedteacherid;
}
$baseurl = new moodle_url('/admin/tool/enrolreactivate/index.php', $urlparams);

$PAGE->set_url($baseurl);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(tool_enrolreactivate_str('pluginname', 'Teacher enrolment reactivation'));
$PAGE->set_heading(tool_enrolreactivate_str('pluginname', 'Teacher enrolment reactivation'));

// Admin-only post-reactivation email actions (driven from the result-page panel below). They act on the
// notify context stashed in the session by the last extend, then redirect (PRG). Sesskey-protected.
$notifyaction = optional_param('notifyaction', '', PARAM_ALPHA);
if ($canmanageall && $notifyaction !== '' && confirm_sesskey()) {
    if ($notifyaction === 'send' && !empty($SESSION->tool_enrolreactivate_notify)) {
        $sent = tool_enrolreactivate_notify_send($SESSION->tool_enrolreactivate_notify, $USER);
        if ($sent) {
            $SESSION->tool_enrolreactivate_notify->sent = true;
        }
        $tname = (string)($teacheroptions[(int)$SESSION->tool_enrolreactivate_notify->teacherid] ?? '');
        redirect(
            $baseurl,
            $sent ? tool_enrolreactivate_str('notifysent', 'A notification email was sent to {$a}.', $tname)
                  : tool_enrolreactivate_str('notifyfailed', 'The notification email could not be sent.'),
            null,
            $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING,
        );
    }
    if ($notifyaction === 'dismiss') {
        unset($SESSION->tool_enrolreactivate_notify);
        redirect($baseurl);
    }
}
// Both the faculty selector and the course chooser are native moodleform `autocomplete` elements with
// options preloaded server-side. The admin faculty->course dependency uses Moodle's no-submit button
// ("Load courses"): choosing a faculty member and loading re-renders the form server-side with that
// faculty member's courses preloaded — no custom AJAX endpoint or JS is required.

$defaultdays = manager::default_days();
$maxdays = manager::max_days();

$formdefaults = [
    'teacherid' => $selectedteacherid > 0 ? $selectedteacherid : '',
    'courseids' => $defaultselectedcourseids,
    'extensiondays' => $defaultdays,
];

$form = new \tool_enrolreactivate\form\extend_form($baseurl, [
    'canmanageall' => $canmanageall,
    'teacheroptions' => $teacheroptionsforform,
    'courseoptions' => $courseoptions,
    'defaults' => $formdefaults,
    'maxdays' => $maxdays,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my/'));
}

if ($data = $form->get_data()) {
    $submittedcourseids = manager::normalize_int_values((array)($data->courseids ?? []));
    $submitteddays = manager::normalize_days((int)($data->extensiondays ?? $defaultdays));
    $submittedteacherid = $canmanageall ? (int)($data->teacherid ?? 0) : (int)$USER->id;

    if ($canmanageall && $submittedteacherid > 0 && !array_key_exists($submittedteacherid, $teacheroptions)) {
        redirect(
            $baseurl,
            tool_enrolreactivate_str('invalidteacher', 'Please select a valid faculty member.'),
            null,
            \core\output\notification::NOTIFY_WARNING,
        );
    }
    if ($canmanageall && $submittedteacherid <= 0) {
        redirect(
            $baseurl,
            tool_enrolreactivate_str('selectfacultyfirst', 'Select a faculty member before choosing courses.'),
            null,
            \core\output\notification::NOTIFY_WARNING,
        );
    }

    if ($canmanageall) {
        $scopecourseoptions = manager::get_course_options_for_admin($submittedteacherid);
    } else {
        $scopecourseoptions = manager::get_course_options_for_teacher((int)$USER->id, true);
    }

    $validcourseids = array_values(array_filter(
        $submittedcourseids,
        static function (int $courseid) use ($scopecourseoptions): bool {
            return array_key_exists($courseid, $scopecourseoptions);
        }
    ));
    if (empty($validcourseids)) {
        $messagekey = $canmanageall ? 'novalidcoursesselected' : 'coursenotfoundorteacher';
        $fallback = $canmanageall
            ? 'No valid course selection was submitted for your current scope.'
            : 'Course does not exist or you are not a teacher in the course';
        redirect(
            $baseurl,
            tool_enrolreactivate_str($messagekey, $fallback),
            null,
            \core\output\notification::NOTIFY_WARNING,
        );
    }

    $notifications = [];
    $hasinformationalnotice = false;
    $totalchanges = 0;
    $totalerrors = 0;
    if (!$canmanageall) {
        $selectionstatus = manager::classify_teacher_selected_courses((int)$USER->id, $validcourseids);
        foreach ($selectionstatus['activecourses'] as $activecourse) {
            $timeend = (int)($activecourse['timeend'] ?? 0);
            if ($timeend > 0) {
                $notifications[] = tool_enrolreactivate_str(
                    'courseenrollmentvaliduntil',
                    'Course enrollment is valid and is available till {$a}.',
                    tool_enrolreactivate_format_datetime($timeend)
                );
            } else {
                $notifications[] = tool_enrolreactivate_str(
                    'courseenrollmentvalidnoend',
                    'Course enrollment is valid and has no end date.'
                );
            }
            $hasinformationalnotice = true;
        }

        $expiredcourseids = manager::normalize_int_values($selectionstatus['expiredcourseids']);
        if (!empty($expiredcourseids)) {
            $summary = manager::extend_for_teacher((int)$USER->id, (int)$USER->id, $expiredcourseids, $submitteddays, true);
            $notifications[] = tool_enrolreactivate_summary_text($summary);
            $totalchanges += (int)($summary['enrolmentsupdated'] ?? 0) + (int)($summary['rolesrestored'] ?? 0);
            $totalerrors += (int)($summary['errors'] ?? 0);
        }
    } else if ($submittedteacherid > 0) {
        $summary = manager::extend_for_teacher((int)$USER->id, $submittedteacherid, $validcourseids, $submitteddays, false);
        $notifications[] = tool_enrolreactivate_summary_text($summary);
        $totalchanges += (int)($summary['enrolmentsupdated'] ?? 0) + (int)($summary['rolesrestored'] ?? 0);
        $totalerrors += (int)($summary['errors'] ?? 0);
    }

    // Remember this admin reactivation so the result page can offer to email the faculty member; send
    // immediately if the "Email the faculty member" checkbox was ticked on the form.
    if ($canmanageall && $submittedteacherid > 0 && !empty($validcourseids)) {
        $SESSION->tool_enrolreactivate_notify = (object)[
            'teacherid' => (int)$submittedteacherid,
            'courseids' => array_values(array_map('intval', $validcourseids)),
            'days' => (int)$submitteddays,
            'when' => time(),
            'sent' => false,
        ];
        if (!empty($data->notifyteacher)) {
            $tname = (string)($teacheroptions[(int)$submittedteacherid] ?? '');
            if (tool_enrolreactivate_notify_send($SESSION->tool_enrolreactivate_notify, $USER)) {
                $SESSION->tool_enrolreactivate_notify->sent = true;
                $notifications[] = tool_enrolreactivate_str('notifysent', 'A notification email was sent to {$a}.', $tname);
            } else {
                $notifications[] = tool_enrolreactivate_str('notifyfailed', 'The notification email could not be sent.');
            }
        }
    }

    $message = implode(' ', $notifications);
    $message = trim($message);
    if ($message === '') {
        $message = tool_enrolreactivate_str('updatenotice', 'Request completed.');
    }

    // Direct "open the course" link(s) for everything just processed, so the user can go straight to the
    // course instead of hunting for it. Rendered as clickable HTML in the redirect notification:
    // redirect() -> \core\notification::add() (no escaping) and the template outputs {{{message}}}.
    // Course names are escaped with s(); URLs are moodle_url, so the HTML is safe.
    $courselinks = [];
    foreach ($validcourseids as $linkcourseid) {
        $linklabel = trim((string)($scopecourseoptions[(int)$linkcourseid] ?? ''));
        if ($linklabel === '') {
            $linklabel = 'Course ' . (int)$linkcourseid;
        }
        $courselinks[] = html_writer::link(
            new moodle_url('/course/view.php', ['id' => (int)$linkcourseid]),
            s($linklabel)
        );
    }
    if (!empty($courselinks)) {
        $shownlinks = array_slice($courselinks, 0, 12);
        $extralinks = count($courselinks) - count($shownlinks);
        $linklist = implode(' &middot; ', $shownlinks);
        if ($extralinks > 0) {
            $linklist .= ' &middot; ' . tool_enrolreactivate_str('andmorecourses', 'and {$a} more', (string)$extralinks);
        }
        $message .= html_writer::div(
            html_writer::tag('strong', tool_enrolreactivate_str('opencoursecta', 'Open the course in Moodle:')) . ' ' . $linklist,
            'tool_enrolreactivate_courselinks'
        );
    }

    $notificationtype = \core\output\notification::NOTIFY_SUCCESS;
    if ($totalerrors > 0 || ($totalchanges === 0 && !$hasinformationalnotice)) {
        $notificationtype = \core\output\notification::NOTIFY_WARNING;
    } else if ($totalchanges === 0 && $hasinformationalnotice) {
        $notificationtype = \core\output\notification::NOTIFY_INFO;
    }

    $redirectparams = [];
    if ($canmanageall && $submittedteacherid > 0) {
        $redirectparams['filterteacherid'] = $submittedteacherid;
    }
    redirect(
        new moodle_url('/admin/tool/enrolreactivate/index.php', $redirectparams),
        $message,
        null,
        $notificationtype,
    );
}

echo $OUTPUT->header();

if ($canmanageall) {
        echo $OUTPUT->notification(
            tool_enrolreactivate_str(
                'adminmodeinfo',
                'Administrator mode: extend access on behalf of a faculty member. Choose the faculty member, '
                    . 'select Load courses to list their courses, then choose the course(s) and Extend access.'
            ),
            \core\output\notification::NOTIFY_INFO,
        );
} else {
    echo $OUTPUT->notification(
        tool_enrolreactivate_str(
            'teachermodeinfo',
            'Teacher mode: expired teacher-enrolment courses are preloaded for self-service extension.'
        ),
        \core\output\notification::NOTIFY_INFO,
    );
}

// Show the "nothing eligible" warning only once a scope is actually established: for a teacher always,
// and for an admin only after they have selected a faculty member (otherwise the empty list is expected).
if (empty($courseoptions) && !($canmanageall && $selectedteacherid <= 0)) {
    echo $OUTPUT->notification(
        tool_enrolreactivate_str(
            'nocoursesinscope',
            'No courses are currently eligible in your scope.'
        ),
        \core\output\notification::NOTIFY_WARNING,
    );
}

// After an admin reactivation, offer to email the faculty member (preview + Send/Re-send + Dismiss).
if ($canmanageall && !empty($SESSION->tool_enrolreactivate_notify)) {
    $notify = $SESSION->tool_enrolreactivate_notify;
    $built = tool_enrolreactivate_notify_build($notify, $USER);
    if ($built !== null) {
        $teachername = fullname($built['teacher']);
        $alreadysent = !empty($notify->sent);
        $panel = html_writer::tag('h4', tool_enrolreactivate_str('notifypaneltitle', 'Notify the faculty member'));
        $panel .= html_writer::tag('p', $alreadysent
            ? tool_enrolreactivate_str('notifypanelsentinfo', 'A notification email was already sent to {$a}.', s($teachername))
            : tool_enrolreactivate_str('notifypanelask', 'Email {$a} about this reactivation?', s($teachername)));
        $panel .= html_writer::tag(
            'div',
            html_writer::tag('strong', s($built['subject'])) .
            html_writer::tag(
                'pre',
                s($built['bodytext']),
                [
                    'style' => 'white-space:pre-wrap;margin:6px 0;padding:8px;border:1px solid #ddd;'
                        . 'border-radius:4px;background:#fafafa;max-height:280px;overflow:auto;',
                ]
            ),
            ['class' => 'tool_enrolreactivate_notifypreview']
        );
        $sesskeyinput = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $sendform = html_writer::tag(
            'form',
            $sesskeyinput .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'notifyaction', 'value' => 'send']) .
            html_writer::tag(
                'button',
                $alreadysent
                ? tool_enrolreactivate_str('notifyresend', 'Re-send email')
                : tool_enrolreactivate_str('notifysend', 'Send email'),
                ['type' => 'submit', 'class' => 'btn btn-primary']
            ),
            ['method' => 'post', 'action' => $baseurl->out(false), 'style' => 'display:inline']
        );
        $dismissform = html_writer::tag(
            'form',
            $sesskeyinput .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'notifyaction', 'value' => 'dismiss']) .
            html_writer::tag(
                'button',
                tool_enrolreactivate_str('notifydismiss', 'Dismiss'),
                ['type' => 'submit', 'class' => 'btn btn-secondary']
            ),
            ['method' => 'post', 'action' => $baseurl->out(false), 'style' => 'display:inline;margin-left:8px']
        );
        $panel .= html_writer::div($sendform . $dismissform, '', ['style' => 'margin-top:8px;']);
        echo html_writer::div(
            $panel,
            'tool_enrolreactivate_notifypanel',
            ['style' => 'margin:12px 0;padding:12px;border:1px solid #ccc;border-radius:6px;']
        );
    } else {
        unset($SESSION->tool_enrolreactivate_notify);
    }
}

$form->display();

if (manager::can_manage_settings($systemcontext)) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/admin/settings.php', ['section' => 'tool_enrolreactivate']),
            tool_enrolreactivate_str('openpluginsettings', 'Open plugin settings')
        ),
        'mt-3'
    );
}

echo $OUTPUT->footer();

/**
 * Build readable summary text from update result.
 *
 * @param array $summary
 * @param bool $aggregate
 * @return string
 */
function tool_enrolreactivate_summary_text(array $summary, bool $aggregate = false): string {
    $base = tool_enrolreactivate_str(
        'summarytemplate',
        'Enrolments updated: {$a->enrolments}. New enrolments: {$a->created}. Roles restored: {$a->roles}. '
            . 'Courses touched: {$a->courses}. Skipped suspended: {$a->suspended}. Skipped inactive: {$a->inactive}. '
            . 'Skipped unlimited: {$a->infinite}. Skipped not-expired: {$a->notexpired}. '
            . 'No matching teacher path: {$a->nomatch}. Errors: {$a->errors}.',
        (object)[
            'enrolments' => (int)($summary['enrolmentsupdated'] ?? 0),
            'created' => (int)($summary['enrolmentscreated'] ?? 0),
            'roles' => (int)($summary['rolesrestored'] ?? 0),
            'courses' => (int)($summary['coursesupdated'] ?? 0),
            'suspended' => (int)($summary['skippedsuspended'] ?? 0),
            'inactive' => (int)($summary['skippedinactive'] ?? 0),
            'infinite' => (int)($summary['skippedinfinite'] ?? 0),
            'notexpired' => (int)($summary['skippednotexpired'] ?? 0),
            'nomatch' => (int)($summary['skippednomatch'] ?? 0),
            'errors' => (int)($summary['errors'] ?? 0),
        ]
    );

    if ($aggregate) {
        $teachercount = (int)($summary['teachercount'] ?? 0);
        $prefix = tool_enrolreactivate_str(
            'aggregatesummaryprefix',
            'Teachers processed: {$a}.',
            $teachercount
        );
        return $prefix . ' ' . $base;
    }

    return $base;
}

/**
 * Format a timestamp for teacher-facing enrolment availability messages.
 *
 * @param int $timestamp
 * @return string
 */
function tool_enrolreactivate_format_datetime(int $timestamp): string {
    $formatted = userdate($timestamp, '%d-%b-%Y %H:%M');
    if (preg_match('/^(\d)-/', $formatted) === 1) {
        $formatted = '0' . $formatted;
    }

    return $formatted;
}

/**
 * Read local string with fallback for missing language values.
 *
 * @param string $key
 * @param string $fallback
 * @param mixed $a
 * @return string
 */
function tool_enrolreactivate_str(string $key, string $fallback, $a = null): string {
    $value = get_string($key, 'tool_enrolreactivate', $a);
    if (preg_match('/^\[\[[a-z0-9_]+(?:,[a-z0-9_]+)?\]\]$/i', $value) === 1) {
        if ($a !== null && strpos($fallback, '{$a}') !== false) {
            return str_replace('{$a}', (string)$a, $fallback);
        }
        return $fallback;
    }

    return $value;
}

/**
 * Course descriptors used to build the notification email.
 *
 * @param int[] $courseids
 * @param int $until access-until timestamp
 * @return array[]
 */
function tool_enrolreactivate_notify_courses(array $courseids, int $until): array {
    global $DB;
    $courses = [];
    foreach ($courseids as $cid) {
        $cid = (int)$cid;
        if ($cid <= 1) {
            continue;
        }
        $course = $DB->get_record('course', ['id' => $cid], 'id,fullname,shortname');
        if (!$course) {
            continue;
        }
        $courses[] = [
            'name' => format_string($course->fullname),
            'short' => (string)$course->shortname,
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $cid]))->out(false),
            'partsurl' => (new moodle_url('/user/index.php', ['id' => $cid]))->out(false),
            'until' => $until,
        ];
    }
    return $courses;
}

/**
 * Placeholder => value map for the notification template.
 *
 * @param stdClass $teacher
 * @param array[] $courses
 * @param int $until
 * @param stdClass $admin
 * @return array
 */
function tool_enrolreactivate_notify_vars(stdClass $teacher, array $courses, int $until, stdClass $admin): array {
    global $SITE;
    $datefmt = get_string('strftimedatefullshort', 'langconfig');
    $lines = [];
    foreach ($courses as $c) {
        $lines[] = '- ' . $c['name'] . ' (' . $c['short'] . ') - access until ' . userdate($c['until'], $datefmt) . "\n"
            . '    Open the course: ' . $c['courseurl'] . "\n"
            . '    Manage enrolments (people, roles, duration): ' . $c['partsurl'];
    }
    $first = $courses[0] ?? null;
    return [
        '<<Teacher Name>>' => fullname($teacher),
        '<<Course List>>' => implode("\n\n", $lines),
        '<<Access Until>>' => userdate($until, $datefmt),
        '<<Course Name>>' => $first ? $first['name'] : '',
        '<<Course Link>>' => $first ? $first['courseurl'] : '',
        '<<Course Participants Link>>' => $first ? $first['partsurl'] : '',
        '<<Reactivation Tool Link>>' => (new moodle_url('/admin/tool/enrolreactivate/index.php'))->out(false),
        '<<Admin First Name>>' => (string)$admin->firstname,
        '<<Admin Name>>' => fullname($admin),
        '<<Site Name>>' => format_string($SITE->fullname),
    ];
}

/**
 * Builds ['teacher','subject','bodytext'] for a stored notify context (placeholders substituted), or null.
 *
 * @param stdClass $notify
 * @param stdClass $admin
 * @return array|null
 */
function tool_enrolreactivate_notify_build(stdClass $notify, stdClass $admin): ?array {
    global $DB;
    $teacher = $DB->get_record('user', ['id' => (int)$notify->teacherid, 'deleted' => 0]);
    if (!$teacher || trim((string)$teacher->email) === '') {
        return null;
    }
    $until = (int)$notify->when + ((int)$notify->days * DAYSECS);
    $courses = tool_enrolreactivate_notify_courses((array)$notify->courseids, $until);
    if (empty($courses)) {
        return null;
    }
    $vars = tool_enrolreactivate_notify_vars($teacher, $courses, $until, $admin);
    $subjecttpl = (string)get_config('tool_enrolreactivate', 'notifysubject');
    if (trim($subjecttpl) === '') {
        $subjecttpl = tool_enrolreactivate_str('notifysubjectdefault', 'Your course access has been reactivated');
    }
    $bodytpl = (string)get_config('tool_enrolreactivate', 'notifytemplate');
    if (trim($bodytpl) === '') {
        $bodytpl = tool_enrolreactivate_str('notifytemplatedefault', '<<Course List>>');
    }
    return [
        'teacher' => $teacher,
        'subject' => trim(strtr($subjecttpl, $vars)),
        'bodytext' => strtr($bodytpl, $vars),
    ];
}

/**
 * Sends the notification email for a stored notify context.
 *
 * @param stdClass $notify
 * @param stdClass $admin
 * @return bool
 */
function tool_enrolreactivate_notify_send(stdClass $notify, stdClass $admin): bool {
    $built = tool_enrolreactivate_notify_build($notify, $admin);
    if ($built === null) {
        return false;
    }
    $bodyhtml = text_to_html($built['bodytext'], false, false, true);
    return (bool)email_to_user($built['teacher'], $admin, $built['subject'], $built['bodytext'], $bodyhtml);
}
