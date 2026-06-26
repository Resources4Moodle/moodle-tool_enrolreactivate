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
 * Language strings.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Extend Enrollment for Editing Teachers';
$string['menuentry'] = 'Extend Enrollment for Editing Teachers';
$string['mycoursesmenuentry'] = 'Extend my teacher enrolment';
$string['managetool'] = 'Open reactivation tool';
$string['pluginsettings'] = 'Reactivation settings';
$string['tool_enrolreactivate:view'] = 'View teacher enrolment reactivation tool';
$string['tool_enrolreactivate:manageall'] = 'Manage enrolment reactivation for all faculty/courses';

$string['defaultdays'] = 'Default extension days';
$string['defaultdays_desc'] = 'Default duration (in days) used in the form. Recommended default is 120.';
$string['maxdays'] = 'Maximum extension days';
$string['maxdays_desc'] = 'Upper limit for extension duration that can be submitted from the form.';
$string['showmenuentry'] = 'Show navigation menu entry';
$string['showmenuentry_desc'] = 'If enabled, eligible users see a shortcut in My courses and related navigation areas.';
$string['showmenuforteacherrole'] = 'Show shortcut for users with a teacher role';
$string['showmenuforteacherrole_desc'] = 'If enabled, users who currently have a teacher archetype role in at least one course can see and access the self-service reactivation shortcut.';
$string['accessscope'] = 'Who can use this tool';
$string['accessscope_desc'] = 'Faculty self-service access is limited to users who currently have a teacher role in Moodle. Site administrators and users with the manage-all permission can work for selected faculty members. Email-domain access is not used for this tool.';
$string['showmenuforemaildomain'] = 'Show shortcut for allowed email domains';
$string['showmenuforemaildomain_desc'] = 'Legacy setting. Email-domain access is not used; faculty self-service access is based on the user having a teacher role in Moodle.';
$string['menuemaildomains'] = 'Allowed email domains for shortcut';
$string['menuemaildomains_desc'] = 'Legacy setting. These domains are not used for reactivation access.';

$string['extendheader'] = 'Extend teacher enrolment access';
$string['reactivationhelp'] = 'How to use this tool';
$string['reactivationhelp_help'] = 'Use this page to restore teacher access to a course whose teacher-enrolment end date has already passed.

**Teachers (your own access).** Your expired teacher courses are preloaded. Select one or more, enter how many days of access to grant, and choose Extend access. Only courses where Moodle already recognises you as a teacher are changed.

**Administrators (on behalf of a colleague).** Choose the faculty member, select Load courses to list that person\'s courses, then choose the course(s) and Extend access. The list is limited to the selected person, so unrelated courses are never touched. You can also tick "Email the faculty member" (or use the Send button on the result page) to notify them.

What it does, and does not do:

* It moves the teacher-access end date forward. If a future start date was blocking access, it is brought to now so the course appears in My courses.
* It restores a missing teacher role assignment when needed.
* It does **not** change student enrolments.
* It skips suspended access, permanent access with no end date, and access that is already active.

After a successful change the result shows a direct link to each course, and every change is written to the audit log.';
$string['facultyowner'] = 'Faculty member';
$string['teachersearchplaceholder'] = 'Search by first name, last name, username, or email ID';
$string['allfacultyowners'] = 'All faculty members';
$string['courses'] = 'Courses';
$string['coursesearchplaceholder'] = 'Type class number or course name';
$string['coursefilterhint'] = 'Tip: Search by any part of the course short name/class number or full name.';
$string['selectcourses'] = 'Select one or more courses';
$string['extensiondays'] = 'Extension duration (days)';
$string['extendaccess'] = 'Extend access';
$string['loadcourses'] = 'Load courses';
$string['loadcourseshint'] = 'Choose a faculty member above, then select Load courses to list the courses where that person is a teacher.';

// Field-level help (the ? icons on the form).
$string['extensiondays_help'] = 'How many days of teacher access to grant. New access ends this many days from today; access that has not yet expired is extended from its current end date. The site administrator sets the default and the maximum.';
$string['courses_help'] = 'Only courses whose teacher-enrolment end date has already passed are offered. Search by course short name (for example the class number) or by full name, and select one or more.';
$string['facultyowner_help'] = 'The faculty member whose access you are restoring. After choosing them, select Load courses to list the courses where they are a teacher; only that person\'s courses can then be selected.';
$string['notifyteachercheckbox_help'] = 'If ticked, an email is sent to the faculty member as soon as you extend access, using the template set in this plugin\'s settings. You can also preview and send (or re-send) it from the result page.';

$string['invalidextensiondays'] = 'Extension days must be at least 1.';
$string['invalidextensiondaysmax'] = 'Extension days cannot exceed {$a}.';
$string['selectatleastonecourse'] = 'Select at least one course.';
$string['selectfacultyfirst'] = 'Select a faculty member before choosing courses.';
$string['invalidteacher'] = 'Please select a valid faculty member.';
$string['novalidcoursesselected'] = 'No valid course selection was submitted for your current scope.';
$string['coursenotfoundorteacher'] = 'Course does not exist or you are not a teacher in the course';
$string['courseenrollmentvaliduntil'] = 'Course enrollment is valid and is available till {$a}.';
$string['courseenrollmentvalidnoend'] = 'Course enrollment is valid and has no end date.';
$string['noteachersfound'] = 'No teacher accounts were found for the selected courses.';
$string['updatenotice'] = 'Request completed.';
$string['partialupdatenotice'] = 'The request completed with some issues. Please review the summary.';
$string['opencoursecta'] = 'Open the course in Moodle:';
$string['andmorecourses'] = 'and {$a} more';

// Teacher notification email (sent when an admin reactivates access).
$string['notifyteachercheckbox'] = 'Email the faculty member about this reactivation';
$string['notifyheading'] = 'Teacher notification email';
$string['notifyheading_desc'] = 'The email an administrator can send to a faculty member after reactivating their access (one click from the extend form, or with a Send/preview button on the result page). Teacher self-service never emails anyone.';
$string['notifysubjectsetting'] = 'Notification email subject';
$string['notifysubjectsetting_desc'] = 'Subject line of the email. The placeholders listed under the template below may be used here too.';
$string['notifysubjectdefault'] = 'Your <<Site Name>> course access has been reactivated';
$string['notifytemplatesetting'] = 'Notification email template';
$string['notifytemplatesetting_desc'] = 'Body of the email sent to the faculty member. Use these placeholders, typed exactly as shown (including the double angle brackets):
<<Teacher Name>> &ndash; the faculty member\'s full name.
<<Course List>> &ndash; a list of the reactivated course(s): each course\'s name, its access-until date, a link to open the course, and a link to its Participants page.
<<Access Until>> &ndash; the date the access is valid until.
<<Course Name>> &ndash; the (first) reactivated course\'s name.
<<Course Link>> &ndash; a direct link to the (first) course.
<<Course Participants Link>> &ndash; link to the (first) course\'s Participants page (where the teacher manages enrolments).
<<Reactivation Tool Link>> &ndash; link to the "Extend my teacher enrolment" tool (for extending their own access).
<<Admin First Name>> &ndash; your first name (the administrator sending the email).
<<Admin Name>> &ndash; your full name.
<<Site Name>> &ndash; this Moodle site\'s name.';
$string['notifytemplatedefault'] = 'Dear <<Teacher Name>>,

Greetings from the <<Site Name>> support team.

Your teaching access on <<Site Name>> has been reactivated for:

<<Course List>>

This access is valid until <<Access Until>>.

Extending your own access later:
You can extend your own teaching access at any time using the "Extend my teacher enrolment" tool -
<<Reactivation Tool Link>>
Open it, select your course(s), enter the number of days, and choose Extend access.

Managing people in your course:
Once you can open your course, you control its enrolments from the course Participants page (linked for each course above). There you can enrol others, assign or change their role, and extend or modify how long they remain enrolled.

Warm regards,

<<Admin First Name>>
(On behalf of the <<Site Name>> support team)';
$string['notifypaneltitle'] = 'Notify the faculty member';
$string['notifypanelask'] = 'Email {$a} about this reactivation?';
$string['notifypanelsentinfo'] = 'A notification email was already sent to {$a}. You can re-send it if needed.';
$string['notifypreview'] = 'Preview';
$string['notifysend'] = 'Send email';
$string['notifyresend'] = 'Re-send email';
$string['notifydismiss'] = 'Dismiss';
$string['notifysent'] = 'A notification email was sent to {$a}.';
$string['notifyfailed'] = 'The notification email could not be sent. Check that the faculty member has an email address and that site email is configured.';
$string['nocandidaterestore'] = 'No eligible teacher enrolment record was found for this course.';
$string['createdenrolment'] = 'Created a fresh teacher enrolment record.';
$string['restoredrole'] = 'Restored the missing teacher role assignment.';

$string['adminmodeinfo'] = 'Administrator mode: you can extend teacher access on behalf of a faculty member (for example, when they email you a request to increase their course access). Choose the faculty member, select Load courses to list the courses where they are a teacher, then choose the course(s), set the duration, and select Extend access.';
$string['teachermodeinfo'] = 'Teacher mode: expired teacher-enrolment courses are preloaded for self-service extension.';
$string['nocoursesinscope'] = 'No courses are currently eligible in your scope.';
$string['noexpiredcoursesformhint'] = 'No expired teacher-enrolment courses are currently available for your account. Active, future-dated, or unlimited enrolments are not listed here.';
$string['openpluginsettings'] = 'Open plugin settings';

$string['summarytemplate'] = 'Enrolments updated: {$a->enrolments}. New enrolments: {$a->created}. Roles restored: {$a->roles}. Courses touched: {$a->courses}. Skipped suspended: {$a->suspended}. Skipped inactive: {$a->inactive}. Skipped unlimited: {$a->infinite}. Skipped not-expired: {$a->notexpired}. No matching teacher path: {$a->nomatch}. Errors: {$a->errors}.';
$string['aggregatesummaryprefix'] = 'Teachers processed: {$a}.';

$string['privacy:metadata:tool_enrolreactivate_log'] = 'Audit entries for enrolment-window extension actions.';
$string['privacy:metadata:tool_enrolreactivate_log:actorid'] = 'User ID of the person who initiated the extension action.';
$string['privacy:metadata:tool_enrolreactivate_log:targetuserid'] = 'User ID of the teacher whose enrolment record was targeted.';
$string['privacy:metadata:tool_enrolreactivate_log:courseid'] = 'Course ID where the enrolment extension was applied.';
$string['privacy:metadata:tool_enrolreactivate_log:enrolid'] = 'Enrol instance ID linked to the user enrolment.';
$string['privacy:metadata:tool_enrolreactivate_log:userenrolmentid'] = 'User enrolment ID that was considered for update.';
$string['privacy:metadata:tool_enrolreactivate_log:extensiondays'] = 'Requested extension duration in days.';
$string['privacy:metadata:tool_enrolreactivate_log:oldtimeend'] = 'Previous enrolment end timestamp.';
$string['privacy:metadata:tool_enrolreactivate_log:newtimeend'] = 'Updated enrolment end timestamp.';
$string['privacy:metadata:tool_enrolreactivate_log:status'] = 'Result status for the action row (for example, updated or error).';
$string['privacy:metadata:tool_enrolreactivate_log:message'] = 'Optional technical message captured for error rows.';
$string['privacy:metadata:tool_enrolreactivate_log:timecreated'] = 'Timestamp when this audit row was created.';
