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

namespace tool_enrolreactivate\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Extend enrolment period form.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extend_form extends \moodleform {
    /**
     * Define the form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $canmanageall = !empty($customdata['canmanageall']);
        $teacheroptions = (array)($customdata['teacheroptions'] ?? []);
        $courseoptions = (array)($customdata['courseoptions'] ?? []);
        $defaultvalues = (array)($customdata['defaults'] ?? []);
        $hascourseoptions = !empty($courseoptions);

        $mform->addElement('header', 'extendheader', $this->str('extendheader', 'Extend teacher enrolment access'));
        $mform->addElement('static', 'reactivationhelp', $this->str('reactivationhelp', 'How to use this tool'), '');
        $mform->addHelpButton('reactivationhelp', 'reactivationhelp', 'tool_enrolreactivate');

        if ($canmanageall) {
            $mform->addElement(
                'autocomplete',
                'teacherid',
                $this->str('facultyowner', 'Faculty member'),
                $teacheroptions,
                [
                    'placeholder' => $this->str('teachersearchplaceholder', 'Type to filter faculty members'),
                    'noselectionstring' => $this->str('allfacultyowners', 'All faculty members'),
                ],
            );
            $mform->setType('teacherid', PARAM_INT);
            $mform->addHelpButton('teacherid', 'facultyowner', 'tool_enrolreactivate');

            // Native dependent-field pattern: "Load courses" is a no-submit button. Pressing it re-renders
            // the form server-side with the selected faculty member's courses preloaded into the chooser
            // below, so no custom AJAX endpoint or JavaScript is needed for the faculty->course dependency.
            $mform->registerNoSubmitButton('loadcourses');
            $mform->addElement('submit', 'loadcourses', $this->str('loadcourses', 'Load courses'));
            $mform->addElement('static', 'loadcourseshint', '', $this->str(
                'loadcourseshint',
                'Choose a faculty member above, then select Load courses to list the courses where that person is a teacher.'
            ));
        }

        if ($canmanageall || $hascourseoptions) {
            $mform->addElement(
                'autocomplete',
                'courseids',
                $this->str('courses', 'Courses'),
                $courseoptions,
                [
                    'multiple' => true,
                    'placeholder' => $this->str('coursesearchplaceholder', 'Type class number or course name'),
                    'noselectionstring' => $this->str('selectcourses', 'Select one or more courses'),
                ],
            );
            $mform->setType('courseids', PARAM_INT);
            $mform->disabledIf('courseids', 'teacherid', 'eq', '');
            $mform->addHelpButton('courseids', 'courses', 'tool_enrolreactivate');
            $mform->addElement('static', 'coursehint', '', $this->str(
                'coursefilterhint',
                $canmanageall
                    ? 'Select a faculty member first. The course list is limited to that faculty member.'
                    : 'Tip: search by any part of the course short name or full name.'
            ));
        } else {
            $mform->addElement('static', 'courseids_empty', $this->str('courses', 'Courses'), $this->str(
                'noexpiredcoursesformhint',
                'No expired teacher-enrolment courses are currently available for your account. '
                    . 'Active, future-dated, or unlimited enrolments are not listed here.'
            ));
        }

        $mform->addElement('text', 'extensiondays', $this->str('extensiondays', 'Extension duration (days)'), [
            'size' => 8,
            'placeholder' => '120',
        ]);
        $mform->setType('extensiondays', PARAM_INT);
        $mform->addHelpButton('extensiondays', 'extensiondays', 'tool_enrolreactivate');

        // Admins can also email the faculty member about the reactivation in one click (a Send/preview
        // button is also offered on the result page). Teacher self-service does not email anyone.
        if ($canmanageall && $hascourseoptions) {
            $mform->addElement(
                'advcheckbox',
                'notifyteacher',
                '',
                $this->str('notifyteachercheckbox', 'Email the faculty member about this reactivation')
            );
            $mform->addHelpButton('notifyteacher', 'notifyteachercheckbox', 'tool_enrolreactivate');
            $mform->setDefault('notifyteacher', 0);
        }

        // Show "Extend access" only once there are courses to extend; for an admin that means after they
        // have loaded a faculty member's courses (the no-submit "Load courses" button drives that step).
        if ($hascourseoptions) {
            $mform->addElement('submit', 'extendaccess', $this->str('extendaccess', 'Extend access'));
        }

        $this->set_data($defaultvalues);
    }

    /**
     * Validate submitted values.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $customdata = $this->_customdata;

        $canmanageall = !empty($customdata['canmanageall']);
        $maxdays = (int)($customdata['maxdays'] ?? 3650);
        if ($maxdays < 1) {
            $maxdays = 3650;
        }

        $days = (int)($data['extensiondays'] ?? 0);
        if ($days < 1) {
            $errors['extensiondays'] = $this->str('invalidextensiondays', 'Extension days must be at least 1.');
        } else if ($days > $maxdays) {
            $errors['extensiondays'] = $this->str(
                'invalidextensiondaysmax',
                'Extension days cannot exceed {$a}.',
                $maxdays
            );
        }

        $courseids = $data['courseids'] ?? [];
        if (!is_array($courseids)) {
            $courseids = [];
        }
        $courseids = array_values(array_filter(array_map(static function ($value): int {
            return (int)$value;
        }, $courseids), static function (int $value): bool {
            return $value > 0;
        }));
        if (empty($courseids) && ($canmanageall || !empty((array)($customdata['courseoptions'] ?? [])))) {
            $errors['courseids'] = $canmanageall
                ? $this->str('selectatleastonecourse', 'Select at least one course.')
                : $this->str('coursenotfoundorteacher', 'Course does not exist or you are not a teacher in the course');
        }

        if ($canmanageall) {
            $teacherid = (int)($data['teacherid'] ?? 0);
            if ($teacherid <= 0) {
                $errors['teacherid'] = $this->str('selectfacultyfirst', 'Select a faculty member before choosing courses.');
            }
        }

        return $errors;
    }

    /**
     * Read local string with fallback for missing language packs.
     *
     * @param string $key
     * @param string $fallback
     * @param mixed $a
     * @return string
     */
    private function str(string $key, string $fallback, $a = null): string {
        $value = get_string($key, 'tool_enrolreactivate', $a);
        if (preg_match('/^\[\[[a-z0-9_]+(?:,[a-z0-9_]+)?\]\]$/i', $value) === 1) {
            if ($a !== null && strpos($fallback, '{$a}') !== false) {
                return str_replace('{$a}', (string)$a, $fallback);
            }
            return $fallback;
        }

        return $value;
    }
}
