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

function xmldb_local_custom_matrix_upgrade($oldversion)
{
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2023082204) {
        $time = time();
        $table = new xmldb_table('local_performance_matrix');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('performancetype', XMLDB_TYPE_CHAR,  '255', null, null, null, null);
            $table->add_field('performancecatid', XMLDB_TYPE_CHAR,  '255', null, null, null, null);
            $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('role', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('month', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('year', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('maxscore', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
            $table->add_field('userscore', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
            $table->add_field('weightage', XMLDB_TYPE_CHAR, '20', null, null, null, null);          
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2023082204, 'local', 'custom_matrix');
    }

    if ($oldversion < 2023082206.02) {
        $time = time();
        $table = new xmldb_table('local_custom_category');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('fullname', XMLDB_TYPE_CHAR,  '255', null, null, null, null);
            $table->add_field('shortname', XMLDB_TYPE_CHAR,  '255', null, null, null, null);
            $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('path', XMLDB_TYPE_CHAR,  '512', null, null, null, null);
            $table->add_field('depth', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
            $table->add_field('visible', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('type', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2023082206.02, 'local', 'custom_matrix');
    }

    if ($oldversion < 2023082206.03) {
       
        $table = new xmldb_table('local_performance_overall');
        $field = new xmldb_field('financialyear');
        $field->set_attributes(XMLDB_TYPE_CHAR, '128', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
       
        upgrade_plugin_savepoint(true, 2023082206.03, 'local', 'custom_matrix');
    }


    if ($oldversion < 2023082207.06) {
       
        $table = new xmldb_table('local_custom_category');
        $field = new xmldb_field('type');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
       
        upgrade_plugin_savepoint(true, 2023082207.06, 'local', 'custom_matrix');
    }

    if ($oldversion < 2023082207.08) {
       
        $table = new xmldb_table('local_performance_overall');
        $field = new xmldb_field('role');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
       
        upgrade_plugin_savepoint(true, 2023082207.08, 'local', 'custom_matrix');
    }

    if ($oldversion < 2023082207.10) {
       
        $table = new xmldb_table('local_performance_overall');
        $field1 = new xmldb_field('parentid');
        $field1->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $field2 = new xmldb_field('type');
        $field2->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
       
        $table = new xmldb_table('local_performance_logs');
        $field1 = new xmldb_field('parentid');
        $field1->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $field2 = new xmldb_field('type');
        $field2->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
       
        upgrade_plugin_savepoint(true, 2023082207.10, 'local', 'custom_matrix');
    }

    if ($oldversion < 2023082207.11) {
       
        $table = new xmldb_table('local_performance_matrix');
        $field = new xmldb_field('type');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
       
        upgrade_plugin_savepoint(true, 2023082207.11, 'local', 'custom_matrix');
    }

    if ($oldversion < 2023092700.02) {
       
        $table = new xmldb_table('local_performance_logs');
        $field = new xmldb_field('period');
        $field->set_attributes(XMLDB_TYPE_CHAR, '30', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('local_performance_overall');
        $field = new xmldb_field('period');
        $field->set_attributes(XMLDB_TYPE_CHAR, '30', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
              
        $table = new xmldb_table('local_performance_logs');
        $field = new xmldb_field('role');
        $field->set_attributes(XMLDB_TYPE_CHAR, '225', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
       
        $table = new xmldb_table('local_performance_logs');
        $field = new xmldb_field('financialyear');
        $field->set_attributes(XMLDB_TYPE_CHAR, '128', null, null, null, null);
     
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        if (date('m') >= 4) {
            $year = date('Y')."-".(date('Y') +1);
        }
        else {
            $year = (date('Y')-1)."-".date('Y');
        }

        $matrixrecs = $DB->get_records('local_performance_logs',  array('financialyear' => NULL));
       
        if($dbman->field_exists($table, $field)){
            $matrixrecs = $DB->get_records('local_performance_logs',  array('financialyear' => NULL));
           if(!empty($matrixrecs)){
                foreach($matrixrecs as $rec){
                    $datarecord = new \stdClass();
                    $datarecord->id = $rec->id;
                    $datarecord->financialyear = $year;
                    $DB->update_record('local_performance_logs',  $datarecord);
                }   
            }        
        }         
       
        $table = new xmldb_table('local_performance_monthly');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('role', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('moduletype', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

            $table->add_field('performancetype', XMLDB_TYPE_CHAR,  '255', null, null, null, null);
            $table->add_field('performancecatid', XMLDB_TYPE_CHAR,  '255', null, null, null, null);
            $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('type', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);            
            $table->add_field('month', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('year', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('financialyear', XMLDB_TYPE_CHAR, '128', null, null, null, 0);
            $table->add_field('maxpoints', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

            $table->add_field('pointsachieved', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('weightage', XMLDB_TYPE_CHAR, '20', null, null, null, 0);
            $table->add_field('period', XMLDB_TYPE_CHAR, '30', null, null, null, 0);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);  
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table);
        }
        $table1 = new xmldb_table('local_performance_quarterly');
        
        if (!$dbman->table_exists($table1)) {
            $table1->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table1->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table1->add_field('role', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table1->add_field('moduletype', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table1->add_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

            $table1->add_field('performancetype', XMLDB_TYPE_CHAR,  '255', null, null, null, null);
            $table1->add_field('performancecatid', XMLDB_TYPE_CHAR,  '255', null, null, null, null);
            $table1->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table1->add_field('type', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);            
            $table1->add_field('month', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table1->add_field('year', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table1->add_field('financialyear', XMLDB_TYPE_CHAR, '128', null, null, null, 0);
            $table1->add_field('maxpoints', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

            $table1->add_field('pointsachieved', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table1->add_field('weightage', XMLDB_TYPE_CHAR, '20', null, null, null, 0);
            $table1->add_field('period', XMLDB_TYPE_CHAR, '30', null, null, null, 0);
            $table1->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table1->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table1->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table1->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);  
            $table1->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table1);
        }
        upgrade_plugin_savepoint(true, 2023092700.02, 'local', 'custom_matrix');
    }

    if($oldversion < 2023100300.07){
        $ptemplate_table = new xmldb_table('local_performance_template');
        if(!$dbman->table_exists($ptemplate_table)){
            $ptemplate_table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $ptemplate_table->add_field('template_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $ptemplate_table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);            
            $ptemplate_table->add_field('financialyear', XMLDB_TYPE_CHAR, '128', null, null, null, 0);
            $ptemplate_table->add_field('active', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);            
            $ptemplate_table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $ptemplate_table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $ptemplate_table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $ptemplate_table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);  
            $ptemplate_table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($ptemplate_table);
        }

        $pm_table = new xmldb_table('local_performance_matrix');
        $pm_field = new xmldb_field('templateid');        
        $pm_field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, 0);               
        if (!$dbman->field_exists($pm_table, $pm_field)) {
            $dbman->add_field($pm_table, $pm_field);            
        }

        $mm_table = new xmldb_table('local_matrix_mapped');

        if ($dbman->table_exists($mm_table)) {
            $dbman->drop_table($mm_table);
        }

        upgrade_plugin_savepoint(true, 2023100300.07, 'local', 'custom_matrix');
    }

    if($oldversion < 2023100300.09){

        $plo_table = new xmldb_table('local_performance_logs');
        $plo_field = new xmldb_field('templateid');        
        $plo_field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($plo_table, $plo_field)) {
            $dbman->add_field($plo_table, $plo_field);            
        }

        $po_table = new xmldb_table('local_performance_overall');
        $po_field = new xmldb_field('templateid');        
        $po_field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($po_table, $po_field)) {
            $dbman->add_field($po_table, $po_field);            
        }

        $pm_table = new xmldb_table('local_performance_matrix');        
        $pmn_table = new xmldb_table('local_performance_monthly');        
        $pq_table = new xmldb_table('local_performance_quarterly');        
        $pm_field1 = new xmldb_field('userscore');        
        $pm_field2 = new xmldb_field('submitteduser');        
                       
        if ($dbman->field_exists($pm_table, $pm_field1)) {
            $dbman->drop_field($pm_table, $pm_field1);            
        }
        if ($dbman->field_exists($pm_table, $pm_field2)) {
            $dbman->drop_field($pm_table, $pm_field2);            
        }
        if ($dbman->field_exists($plo_table, $pm_field2)) {
            $dbman->drop_field($plo_table, $pm_field2);            
        }
        if ($dbman->field_exists($po_table, $pm_field2)) {
            $dbman->drop_field($po_table, $pm_field2);            
        }
        if ($dbman->field_exists($pq_table, $pm_field2)) {
            $dbman->drop_field($pq_table, $pm_field2);            
        }

        upgrade_plugin_savepoint(true, 2023100300.09, 'local', 'custom_matrix');
    }

    if ($oldversion < 2023100300.10) {
       
        $table = new xmldb_table('local_custom_matrix');       
        if ($dbman->table_exists('local_custom_matrix')) {
            $dbman->rename_table($table, 'local_custom_category');
        }       
        upgrade_plugin_savepoint(true, 2023100300.10, 'local', 'custom_matrix');
    }

    return true;
}
