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
 * Plugin settings.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category(
        'tool_enrolreactivate_category',
        get_string('pluginname', 'tool_enrolreactivate'),
    ));

    $ADMIN->add('tool_enrolreactivate_category', new admin_externalpage(
        'tool_enrolreactivate_manage',
        get_string('managetool', 'tool_enrolreactivate'),
        new moodle_url('/admin/tool/enrolreactivate/index.php'),
        'tool/enrolreactivate:view',
    ));

    $settings = new admin_settingpage('tool_enrolreactivate', get_string('pluginsettings', 'tool_enrolreactivate'));
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'tool_enrolreactivate/defaultdays',
            get_string('defaultdays', 'tool_enrolreactivate'),
            get_string('defaultdays_desc', 'tool_enrolreactivate'),
            120,
            PARAM_INT,
        ));

        $settings->add(new admin_setting_configtext(
            'tool_enrolreactivate/maxdays',
            get_string('maxdays', 'tool_enrolreactivate'),
            get_string('maxdays_desc', 'tool_enrolreactivate'),
            3650,
            PARAM_INT,
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_enrolreactivate/showmenuentry',
            get_string('showmenuentry', 'tool_enrolreactivate'),
            get_string('showmenuentry_desc', 'tool_enrolreactivate'),
            1,
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_enrolreactivate/showmenuforteacherrole',
            get_string('showmenuforteacherrole', 'tool_enrolreactivate'),
            get_string('showmenuforteacherrole_desc', 'tool_enrolreactivate'),
            1,
        ));

        // Editable email sent to the faculty member when an admin reactivates their access.
        // The template description lists every available <<placeholder>>.
        $settings->add(new admin_setting_heading(
            'tool_enrolreactivate/notifyheading',
            get_string('notifyheading', 'tool_enrolreactivate'),
            get_string('notifyheading_desc', 'tool_enrolreactivate'),
        ));
        $settings->add(new admin_setting_configtext(
            'tool_enrolreactivate/notifysubject',
            get_string('notifysubjectsetting', 'tool_enrolreactivate'),
            get_string('notifysubjectsetting_desc', 'tool_enrolreactivate'),
            get_string('notifysubjectdefault', 'tool_enrolreactivate'),
            PARAM_TEXT,
        ));
        $settings->add(new admin_setting_configtextarea(
            'tool_enrolreactivate/notifytemplate',
            get_string('notifytemplatesetting', 'tool_enrolreactivate'),
            get_string('notifytemplatesetting_desc', 'tool_enrolreactivate'),
            get_string('notifytemplatedefault', 'tool_enrolreactivate'),
            PARAM_RAW,
        ));

        $settings->add(new admin_setting_heading(
            'tool_enrolreactivate/accessscope',
            get_string('accessscope', 'tool_enrolreactivate'),
            get_string('accessscope_desc', 'tool_enrolreactivate'),
        ));
    }

    $ADMIN->add('tool_enrolreactivate_category', $settings);
}
