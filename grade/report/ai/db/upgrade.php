<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_gradereport_ai_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024112800) {
        // Define table user_ai_reports to be created.
        $table = new xmldb_table('user_ai_reports');

        // Add fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ai_report_data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('tokens_usage', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_at', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('updated_at', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null);

        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);

        // Add indexes.
        $table->add_index('userid_index', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('contextid_index', XMLDB_INDEX_NOTUNIQUE, ['contextid']);

        // Conditionally create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Save upgrade state.
        upgrade_plugin_savepoint(true, 2024112800, 'gradereport', 'ai');
    }

    return true;
}
