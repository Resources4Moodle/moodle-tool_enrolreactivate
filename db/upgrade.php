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
 * Upgrade steps for tool_enrolreactivate.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs tool upgrades.
 *
 * @param int $oldversion old plugin version
 * @return bool
 */
function xmldb_tool_enrolreactivate_upgrade(int $oldversion): bool {
    if ($oldversion < 2026052500) {
        upgrade_plugin_savepoint(true, 2026052500, 'tool', 'enrolreactivate');
    }

    if ($oldversion < 2026052501) {
        if (get_config('tool_enrolreactivate', 'showmenuforteacherrole') === false) {
            set_config('showmenuforteacherrole', 1, 'tool_enrolreactivate');
        }
        if (get_config('tool_enrolreactivate', 'showmenuforemaildomain') === false) {
            set_config('showmenuforemaildomain', 0, 'tool_enrolreactivate');
        }
        if (get_config('tool_enrolreactivate', 'menuemaildomains') === false) {
            set_config('menuemaildomains', '', 'tool_enrolreactivate');
        }
        upgrade_plugin_savepoint(true, 2026052501, 'tool', 'enrolreactivate');
    }

    if ($oldversion < 2026052502) {
        upgrade_plugin_savepoint(true, 2026052502, 'tool', 'enrolreactivate');
    }

    if ($oldversion < 2026052503) {
        upgrade_plugin_savepoint(true, 2026052503, 'tool', 'enrolreactivate');
    }

    if ($oldversion < 2026052504) {
        upgrade_plugin_savepoint(true, 2026052504, 'tool', 'enrolreactivate');
    }

    if ($oldversion < 2026052505) {
        upgrade_plugin_savepoint(true, 2026052505, 'tool', 'enrolreactivate');
    }

    if ($oldversion < 2026052700) {
        upgrade_plugin_savepoint(true, 2026052700, 'tool', 'enrolreactivate');
    }

    return true;
}
