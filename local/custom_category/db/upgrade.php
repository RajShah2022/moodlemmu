<?php

/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_custom_category_upgrade($oldversion)
{
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2022101300.22) {
        $time = time();
        $table = new xmldb_table('local_category_mapped');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('costcenterid', XMLDB_TYPE_INTEGER,  '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('moduletype', XMLDB_TYPE_CHAR,  '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('moduleid', XMLDB_TYPE_INTEGER,  '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('category', XMLDB_TYPE_CHAR, '225', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2022101300.22, 'local', 'custom_category');
    }
    if ($oldversion < 2022101300.23) {
        $time = time();
        $table = new xmldb_table('local_category_ta');

        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'local_category_mapped');
        }
        upgrade_plugin_savepoint(true, 2022101300.23, 'local', 'custom_category');
    }      

    if ($oldversion < 2023101300.00) {
       
        $table = new xmldb_table('local_custom_category');       
        if ($dbman->table_exists('local_custom_category')) {
            $dbman->rename_table($table, 'local_custom_fields');
        }       
        upgrade_plugin_savepoint(true, 2023101300.00, 'local', 'custom_category');
    }
    if ($oldversion < 2023101300.01) {       
        $table = new xmldb_table('local_custom_fields');
        $field = new xmldb_field('performancestatus');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
     
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }               
        upgrade_plugin_savepoint(true, 2023101300.01, 'local', 'custom_category');
    }


    return true;
}
