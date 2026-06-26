# Extend Enrollment for Editing Teachers (tool_enrolreactivate)

A Moodle admin tool that restores **expired teacher (and other) enrolments** by pushing the
enrolment end date forward, so staff regain access to courses they can no longer open — without
an administrator having to edit each course's enrolment by hand.

It is built entirely on Moodle's own enrolment API (no direct database writes), works on any theme,
and records every change in an audit log.

## Why you might need it

When a course's teacher enrolment reaches its end date, the teacher loses access and the course
disappears from their **My courses**. Restoring access normally means visiting each course's
*Participants* page and editing the enrolment one at a time. This tool turns that into a single,
searchable, multi-course action — available both as **teacher self-service** and as an
**administrator action on behalf of a colleague**.

## Features

- **Teacher self-service** — a teacher reactivates their *own* expired enrolments. Only courses
  where the enrolment end date is already in the past are offered.
- **Administrator mode** — an administrator (or any user with `tool/enrolreactivate:manageall`)
  reactivates enrolments on behalf of a chosen faculty member; the course list is scoped to that
  person, so unrelated courses can't be touched by mistake.
- **Bulk + searchable** — pick several courses at once; the chooser matches on course short name
  (class number) and full name.
- **Safe by design** — uses `update_user_enrol()` / `enrol_user()` on the manual enrolment plugin,
  restores a missing teacher role assignment, and *skips* suspended access, permanent (no-end)
  access, and enrolments that are already active.
- **Direct course links** — after a reactivation the result includes a one-click link to each course.
- **Optional notification email** — when an administrator reactivates on behalf of a teacher, they
  can email the teacher a fully **editable, placeholder-driven** message (course link, access-until
  date, how to extend their own access, and how to manage enrolments in their course).
- **Audit log** — every completed change is written to `tool_enrolreactivate_log`.
- **My courses shortcut** — eligible users can see an "Extend my teacher enrolment" shortcut
  (visibility configurable).

## Requirements

- Moodle **4.5** (2024100100) or later.
- The **manual enrolment** plugin enabled (standard in core Moodle).

## Installation

**From the Moodle plugins directory / a ZIP**

1. Site administration → *Plugins* → *Install plugins*.
2. Upload the ZIP (its single top-level folder is `enrolreactivate`) and follow the upgrade prompt.

**Manually**

1. Unzip into `admin/tool/` so the plugin lives at `admin/tool/enrolreactivate/`.
2. Visit Site administration → *Notifications* to complete the install.

## Using it

Open **Site administration → Plugins → Teacher enrolment reactivation → Open reactivation tool**,
or go to `/admin/tool/enrolreactivate/index.php`. Eligible users also get a *My courses* shortcut.

- **As a teacher** — your expired teacher courses are preloaded. Select the course(s), enter the
  number of days, and choose **Extend access**.
- **As an administrator** — choose the faculty member, select **Load courses** to list that person's
  courses, choose the course(s), set the number of days, and choose **Extend access**. You can also
  tick **Email the faculty member** (or use the Send button on the result page) to notify them.

## Settings

`Site administration → Plugins → Admin tools → Teacher enrolment reactivation`

| Setting | Purpose |
| --- | --- |
| Default extension days | Pre-filled number of days on the form (default 120). |
| Maximum extension days | Upper limit accepted by the form. |
| Show navigation menu entry | Whether eligible users see the *My courses* shortcut. |
| Show shortcut for users with a teacher role | Show the shortcut to anyone with a teacher-archetype role. |
| Notification email subject / template | The editable email an administrator can send to the teacher. The template description lists every available `<<placeholder>>`. |

### Notification email placeholders

Type these exactly (including the double angle brackets) in the subject or template:

`<<Teacher Name>>`, `<<Course List>>`, `<<Access Until>>`, `<<Course Name>>`, `<<Course Link>>`,
`<<Course Participants Link>>`, `<<Reactivation Tool Link>>`, `<<Admin First Name>>`,
`<<Admin Name>>`, `<<Site Name>>`.

`<<Course List>>` expands to one entry per reactivated course (name, access-until date, a link to
the course, and a link to its Participants page).

## Capabilities

- `tool/enrolreactivate:view` — use the tool for one's own eligible enrolments (teacher, editing
  teacher, manager by default).
- `tool/enrolreactivate:manageall` — reactivate on behalf of any faculty member and email them
  (manager by default).

## Privacy

The plugin stores an audit row per action (actor, target user, course, enrolment ids, requested
days, old/new end dates, status). These are described to Moodle's privacy subsystem via the
provider in `classes/privacy/`.

## License

GNU GPL v3 or later — https://www.gnu.org/copyleft/gpl.html
