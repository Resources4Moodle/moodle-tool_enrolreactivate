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

namespace tool_enrolreactivate\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use xmldb_table;

/**
 * Privacy provider for tool_enrolreactivate.
 *
 * @package    tool_enrolreactivate
 * @copyright  2026 jsp <plugins@resources4moodle.icu>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored data.
     *
     * @param collection $items
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table('tool_enrolreactivate_log', [
            'actorid' => 'privacy:metadata:tool_enrolreactivate_log:actorid',
            'targetuserid' => 'privacy:metadata:tool_enrolreactivate_log:targetuserid',
            'courseid' => 'privacy:metadata:tool_enrolreactivate_log:courseid',
            'enrolid' => 'privacy:metadata:tool_enrolreactivate_log:enrolid',
            'userenrolmentid' => 'privacy:metadata:tool_enrolreactivate_log:userenrolmentid',
            'extensiondays' => 'privacy:metadata:tool_enrolreactivate_log:extensiondays',
            'oldtimeend' => 'privacy:metadata:tool_enrolreactivate_log:oldtimeend',
            'newtimeend' => 'privacy:metadata:tool_enrolreactivate_log:newtimeend',
            'status' => 'privacy:metadata:tool_enrolreactivate_log:status',
            'message' => 'privacy:metadata:tool_enrolreactivate_log:message',
            'timecreated' => 'privacy:metadata:tool_enrolreactivate_log:timecreated',
        ], 'privacy:metadata:tool_enrolreactivate_log');

        return $items;
    }

    /**
     * Return contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if (!$DB->get_manager()->table_exists(new xmldb_table('tool_enrolreactivate_log'))) {
            return $contextlist;
        }

        $exists = $DB->record_exists_select(
            'tool_enrolreactivate_log',
            'actorid = :actorid OR targetuserid = :targetuserid',
            ['actorid' => $userid, 'targetuserid' => $userid]
        );
        if ($exists) {
            $contextlist->add_context(context_system::instance()->id);
        }

        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->get_contextids())) {
            return;
        }
        if (!$DB->get_manager()->table_exists(new xmldb_table('tool_enrolreactivate_log'))) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records_select(
            'tool_enrolreactivate_log',
            'actorid = :actorid OR targetuserid = :targetuserid',
            ['actorid' => $userid, 'targetuserid' => $userid],
            'timecreated DESC, id DESC'
        );
        if (empty($records)) {
            return;
        }

        $data = array_values(array_map(static function (\stdClass $record): \stdClass {
            unset($record->id);
            return $record;
        }, $records));

        $context = context_system::instance();
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'tool_enrolreactivate')],
            (object)['logs' => $data]
        );
    }

    /**
     * Delete all data in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        if (!$DB->get_manager()->table_exists(new xmldb_table('tool_enrolreactivate_log'))) {
            return;
        }

        $DB->delete_records('tool_enrolreactivate_log');
    }

    /**
     * Delete user data in approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->get_contextids())) {
            return;
        }
        if (!$DB->get_manager()->table_exists(new xmldb_table('tool_enrolreactivate_log'))) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $DB->delete_records_select(
            'tool_enrolreactivate_log',
            'actorid = :actorid OR targetuserid = :targetuserid',
            ['actorid' => $userid, 'targetuserid' => $userid]
        );
    }
}
