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
 * Tool plugin callbacks.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a preferences-page shortcut for users who can use the tool.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 * @return void
 */
function tool_enrolreactivate_extend_navigation_user_settings(
    navigation_node $navigation,
    stdClass $user,
    $usercontext,
    stdClass $course,
    $coursecontext
): void {
    global $USER, $PAGE;

    if ($user->id !== $USER->id || isguestuser()) {
        return;
    }
    if (
        !$PAGE->url->compare(new moodle_url('/user/preferences.php'), URL_MATCH_BASE)
        && !$PAGE->url->compare(new moodle_url('/admin/tool/enrolreactivate/index.php'), URL_MATCH_BASE)
    ) {
        return;
    }

    $systemcontext = context_system::instance();
    if (!\tool_enrolreactivate\local\manager::should_show_menu_entry($USER, $systemcontext)) {
        return;
    }

    $url = new moodle_url('/admin/tool/enrolreactivate/index.php');
    $navigation->add(
        get_string('menuentry', 'tool_enrolreactivate'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'tool_enrolreactivate_preferences',
        new pix_icon('i/settings', '')
    );
}

/**
 * Add a link to the user profile tree.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass $course
 * @return bool
 */
function tool_enrolreactivate_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course): bool {
    if (!$iscurrentuser || isguestuser()) {
        return false;
    }

    $systemcontext = context_system::instance();
    if (!\tool_enrolreactivate\local\manager::should_show_menu_entry($user, $systemcontext)) {
        return false;
    }

    $url = new moodle_url('/admin/tool/enrolreactivate/index.php');
    $node = new \core_user\output\myprofile\node(
        'miscellaneous',
        'tool_enrolreactivate',
        get_string('menuentry', 'tool_enrolreactivate'),
        null,
        $url
    );
    $tree->add_node($node);
    return true;
}

/**
 * Add quick menu entry in global navigation for eligible users.
 *
 * @param global_navigation $navigation
 * @return void
 */
function tool_enrolreactivate_extend_navigation(global_navigation $navigation): void {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $systemcontext = context_system::instance();
    if (!\tool_enrolreactivate\local\manager::should_show_menu_entry($USER, $systemcontext)) {
        return;
    }

    $url = new moodle_url('/admin/tool/enrolreactivate/index.php');
    $title = get_string('mycoursesmenuentry', 'tool_enrolreactivate');

    $parent = null;
    if (defined('navigation_node::TYPE_ROOTNODE')) {
        $parent = $navigation->find('mycourses', navigation_node::TYPE_ROOTNODE);
    } else if (defined('navigation_node::TYPE_ROOT')) {
        $parent = $navigation->find('mycourses', navigation_node::TYPE_ROOT);
    }
    if (!$parent) {
        $parent = $navigation;
    }

    if (!$parent->find('tool_enrolreactivate', navigation_node::TYPE_CUSTOM)) {
        $parent->add(
            $title,
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'tool_enrolreactivate',
            new pix_icon('i/settings', ''),
        );
    }
}

/**
 * Add settings-navigation shortcut to the tool.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 * @return void
 */
function tool_enrolreactivate_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $systemcontext = context_system::instance();
    if (!\tool_enrolreactivate\local\manager::should_show_menu_entry($USER, $systemcontext)) {
        return;
    }

    $title = get_string('menuentry', 'tool_enrolreactivate');
    $url = new moodle_url('/admin/tool/enrolreactivate/index.php');

    $node = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);
    if (!$node) {
        $node = $settingsnav;
    }

    if (!$node->find('tool_enrolreactivate_settingslink', navigation_node::TYPE_CUSTOM)) {
        $node->add(
            $title,
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'tool_enrolreactivate_settingslink',
            new pix_icon('i/settings', ''),
        );
    }
}
