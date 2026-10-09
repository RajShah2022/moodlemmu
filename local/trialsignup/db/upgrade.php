<?php
/**
 * Upgrade script for local_trialsignup.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_trialsignup_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // 0.1.0 → 0.2.0: assignments table.
    if ($oldversion < 2026042201) {
        $table = new xmldb_table('local_trialsignup_assignments');

        $table->add_field('id',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cohortid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeadded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('expires',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('userid_cohortid', XMLDB_INDEX_UNIQUE,    ['userid', 'cohortid']);
        $table->add_index('expires',         XMLDB_INDEX_NOTUNIQUE, ['expires']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026042201, 'local', 'trialsignup');
    }

    if ($oldversion < 2026042702) {
        upgrade_plugin_savepoint(true, 2026042702, 'local', 'trialsignup');
    }

    if ($oldversion < 2026042703) {
        upgrade_plugin_savepoint(true, 2026042703, 'local', 'trialsignup');
    }

    // 0.4.x → 0.5.0: partners table + user column.
    if ($oldversion < 2026042704) {
        // Partners table.
        $partnerstable = new xmldb_table('local_trialsignup_partners');
        $partnerstable->add_field('id',          XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $partnerstable->add_field('name',        XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, null);
        $partnerstable->add_field('active',      XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '1');
        $partnerstable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, null);

        $partnerstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $partnerstable->add_index('active', XMLDB_INDEX_NOTUNIQUE, ['active']);

        if (!$dbman->table_exists($partnerstable)) {
            $dbman->create_table($partnerstable);
        }

        // User column.
        $usertable = new xmldb_table('user');
        $partnercol = new xmldb_field('trialsignup_partnerid',
            XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'open_project');

        if (!$dbman->field_exists($usertable, $partnercol)) {
            $dbman->add_field($usertable, $partnercol);
        }

        upgrade_plugin_savepoint(true, 2026042704, 'local', 'trialsignup');
    }

    // 0.5.0 → 0.5.1: add status column to assignments table.
    if ($oldversion < 2026050401) {
        $table = new xmldb_table('local_trialsignup_assignments');
        $statusfield = new xmldb_field('status',
            XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active', 'expires');

        if (!$dbman->field_exists($table, $statusfield)) {
            $dbman->add_field($table, $statusfield);
        }

        // Add index on status.
        $statusindex = new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->index_exists($table, $statusindex)) {
            $dbman->add_index($table, $statusindex);
        }

        // All existing rows are active (expired ones were deleted in previous versions).
        $DB->execute("UPDATE {local_trialsignup_assignments} SET status = 'active' WHERE status = '' OR status IS NULL");

        upgrade_plugin_savepoint(true, 2026050401, 'local', 'trialsignup');
    }

    // 0.5.1 → 0.6.0: no schema change — extend page added.
    if ($oldversion < 2026051101) {
        upgrade_plugin_savepoint(true, 2026051101, 'local', 'trialsignup');
    }

    return true;
}
