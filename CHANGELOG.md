# Changelog

All notable changes to this plugin are documented here.

## [1.0.0] - 2026-06-26
Initial public release.

### Features
- Teacher self-service reactivation of expired teacher enrolments, and an
  administrator "on behalf of a faculty member" mode scoped to that person's
  courses.
- Bulk, searchable course chooser (matches short name / class number and full name).
- Safe by design: uses the enrolment API (`update_user_enrol()` / `enrol_user()`),
  restores a missing teacher role assignment, and skips suspended, no-end-date,
  and already-active enrolments.
- Optional, editable placeholder-driven notification email to the faculty member.
- Audit log of every change, with a privacy provider.

### Quality / compatibility
- Verified against **Moodle 4.5 and 5.2** on **MySQL and PostgreSQL**
  (PHPUnit + Behat + the full Moodle plugin-CI static suite: phpcs, phpdoc,
  validate, savepoints, mustache, grunt — all clean).
