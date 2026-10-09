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

function xmldb_local_courses_upgrade($oldversion)
{
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2017111300) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('approvalreqd', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017111300, 'local', 'courses');
    }
    if ($oldversion < 2017111301) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('selfenrol', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017111301, 'local', 'courses');
    }
    if ($oldversion < 2017111302) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('open_level', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017111302, 'local', 'courses');
    }
    if ($oldversion < 2019091300.01) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('open_subdepartment', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019091300.01, 'local', 'courses');
    }
    if ($oldversion < 2019091300.08) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('open_securecourse');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2', XMLDB_NOTNULL, null, 0, 0, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019091300.08, 'local', 'courses');
    }
    if ($oldversion < 2019091300.09) {
        $table = new xmldb_table('course');
        $field1 = new xmldb_field('open_hrmsrole');
        $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        $field2 = new xmldb_field('open_location');
        $field2->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        upgrade_plugin_savepoint(true, 2019091300.09, 'local', 'courses');
    }
    if ($oldversion < 2019091300.10) {
        $table = new xmldb_table('course');
        $field1 = new xmldb_field('open_departmentid');
        if ($dbman->field_exists($table, $field1)) {
            $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $dbman->change_field_type($table, $field1);
        }
        $field2 = new xmldb_field('open_subdepartment');
        if ($dbman->field_exists($table, $field2)) {
            $field2->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $dbman->change_field_type($table, $field2);
        }
        upgrade_plugin_savepoint(true, 2019091300.10, 'local', 'courses');
    }
    if ($oldversion < 2022101800) {
        $table = new xmldb_table('local_logs');
        $table1 = new xmldb_table('local_courseerrors');
        $index = new xmldb_index('module', XMLDB_INDEX_NOTUNIQUE, array('module'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index1 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        if (!$dbman->index_exists($table1, $index1)) {
            $dbman->add_index($table1, $index1);
        }
        upgrade_plugin_savepoint(true, 2022101800, 'local', 'courses');
    }
    if ($oldversion < 2022101800.03) {
        $table = new xmldb_table('local_emaillogs');
        $field1 = new xmldb_field('status');
        $field1->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2022101800.03, 'local', 'local_emaillogs');
    }
    if ($oldversion < 2022101800.08) {
        $table = new xmldb_table('course');
        $field1 = new xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2022101800.08, 'local', 'courses');
    }
    if ($oldversion < 2022101800.10) {
        $table = new xmldb_table('course');
        $field1 = new xmldb_field('open_categoryid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2022101800.10, 'local', 'courses');
    }
    if ($oldversion < 2022101800.18) {
        $table = new xmldb_table('course');
        $field1 = new xmldb_field('open_group');
        $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        $field2 = new xmldb_field('open_designation');
        $field2->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        upgrade_plugin_savepoint(true, 2022101800.18, 'local', 'courses');
    }

    if ($oldversion < 2023060600.02) {
        $time = time();
        $initcontent = array('name' => 'Course', 'shortname' => 'course', 'parent_module' => '0', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'courses');
        $parentid = $DB->get_field('local_notification_type', 'id', array('shortname' => 'course'));
        if (!$parentid) {
            $parentid = $DB->insert_record('local_notification_type', $initcontent);
        }

        $notification_type_data = array(array('name' => 'Course Completion Reminder(After Completion)','shortname' => 'course_completion_reminder','parent_module' => $parentid,'usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'courses'));
        foreach($notification_type_data as $notification_type){
            unset($notification_type['timecreated']);
            if(!$DB->record_exists('local_notification_type',  $notification_type)){
                $notification_type['timecreated'] = $time;
                $DB->insert_record('local_notification_type', $notification_type);
            }
        }
      
        $table = new xmldb_table('local_frequencylogs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('notification_infoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('from_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('to_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $table->add_field('from_emailid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('to_emailid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('moduletype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('moduleid', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('teammemberid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('reminderdays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('enable_cc', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('emailbody', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '0');
            $table->add_field('adminbody', XMLDB_TYPE_TEXT, null, null, null, null, '0');
            $table->add_field('attachment_filepath', XMLDB_TYPE_CHAR, null, null, null, null, '0');
            $table->add_field('status', XMLDB_TYPE_INTEGER, 10, null, null, null, '0');

            $table->add_field('nextdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');        
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

            $table->add_field('sent_date', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('sent_by', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $result = $dbman->create_table($table);
        }
               
        if ($DB->record_exists('local_notification_type',  array('shortname' => 'course_reminder'))) {
            $id = $DB->get_field('local_notification_type','id', array('shortname' => 'course_reminder'));
            $record = new stdClass;
            $record->id = $id ;
            $record->name = 'Course Reminder(Before Completion)';
            $DB->update_record('local_notification_type', $record);
        }

        upgrade_plugin_savepoint(true, 2023060600.02, 'local', 'courses');
    }

    if ($oldversion < 2023060600.03) {
        $time = time();
        $table = new xmldb_table('local_course_targetaudience');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER,  '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('component', XMLDB_TYPE_CHAR,  '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('fieldname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('fieldvalue', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);            
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2023060600.03, 'local', 'courses');
    }

    if ($oldversion < 2023060600.04) {

        $table = new xmldb_table('local_course_targetaudience');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('local_module_targetaudience');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('moduleid', XMLDB_TYPE_INTEGER,  '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('module', XMLDB_TYPE_CHAR,  '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('fieldvalue', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);            
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2023060600.04, 'local', 'courses');
    }

    if ($oldversion < 2023060600.07) {
        if ($DB->record_exists('local_notification_type',  array('shortname' => 'course_completion_reminder'))) {
            $id = $DB->get_field('local_notification_type','id', array('shortname' => 'course_completion_reminder'));
            $record = new stdClass;
            $record->id = $id ;
            $record->name = 'Course Completion Reminder';
            $DB->update_record('local_notification_type', $record);
        }

        upgrade_plugin_savepoint(true, 2023060600.07, 'local', 'courses');
    }

    if ($oldversion < 2023060600.11) {
        $table = new xmldb_table('course');
        $field1 = new xmldb_field('open_group');
        $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        $field2 = new xmldb_field('open_designation');
        $field2->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        upgrade_plugin_savepoint(true, 2023060600.11, 'local', 'courses');
    }

    $corecomponent = new \core_component();
    $pluginexist = $corecomponent::get_plugin_directory('local', 'custom_matrix');
    if($pluginexist ){
        if ($oldversion < 2023060600.08) {
            $table = new xmldb_table('course');
            $field1 = new xmldb_field('performancecatid');
            $field1->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($table, $field1)) {
                $dbman->add_field($table, $field1);
            }
            $field2 = new xmldb_field('performanceparentid');
            $field2->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($table, $field2)) {
                $dbman->add_field($table, $field2);
            }
            upgrade_plugin_savepoint(true, 2023060600.08, 'local', 'courses');
        }
    }
     if ($oldversion < 2023060600.12) {
        $table = new xmldb_table('user_info_field');
        $field1 = new xmldb_field('targetaudience');
        $field1->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }       
        upgrade_plugin_savepoint(true, 2023060600.12, 'local', 'courses');
    }

     if ($oldversion < 2023060600.13) {
        $time = time();
        $initcontent = array('name' => 'Course', 'shortname' => 'course', 'parent_module' => '0', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'courses');
        $parentid = $DB->get_field('local_notification_type', 'id', array('shortname' => 'course'));
        if (!$parentid) {
            $parentid = $DB->insert_record('local_notification_type', $initcontent);
        }

        $notification_type_data = array(array('name' => 'Course Completion Reminder','shortname' => 'course_completion_reminder','parent_module' => $parentid,'usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'courses'));
        foreach($notification_type_data as $notification_type){
            unset($notification_type['timecreated']);
            if(!$DB->record_exists('local_notification_type',  $notification_type)){
                $notification_type['timecreated'] = $time;
                $DB->insert_record('local_notification_type', $notification_type);
            }
        }      
         
        if ($DB->record_exists('local_notification_type',  array('shortname' => 'course_reminder'))) {
            $id = $DB->get_field('local_notification_type','id', array('shortname' => 'course_reminder'));
            $record = new stdClass;
            $record->id = $id ;
            $record->name = 'Course Reminder(Before Completion)';
            $DB->update_record('local_notification_type', $record);
        }

        upgrade_plugin_savepoint(true, 2023060600.13, 'local', 'courses');
    }
    if ($oldversion < 2023060600.14) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('open_difficultylevel');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2023060600.14, 'local', 'courses');
    }

    if ($oldversion < 2023060600.16) {
        $table = new xmldb_table('local_coursetopics');
        if (!$dbman->table_exists($table)) {
            // Adding fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('topic', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

            // Adding key.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Create table.
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2023060600.16, 'local', 'courses');
    }

    if ($oldversion < 2023060600.18) {
        $table = new xmldb_table('local_coursegroups');
        if (!$dbman->table_exists($table)) {
            // Adding fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

            // Adding key.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Create table.
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2023060600.18, 'local', 'courses');
    }

    if ($oldversion < 2023060600.20) {
        $table1 = new xmldb_table('local_coursetopics');
        $table2 = new xmldb_table('local_coursegroups');
        $field = new xmldb_field('open_path');
        $field->set_attributes(XMLDB_TYPE_CHAR, '100', null, null, null, null, null);
        if (!$dbman->field_exists($table1, $field)) {
            $dbman->add_field($table1, $field);
        }
        if (!$dbman->field_exists($table2, $field)) {
            $dbman->add_field($table2, $field);
        }
        upgrade_plugin_savepoint(true, 2023060600.20, 'local', 'courses');
    }
    if ($oldversion < 2023060600.22) {
        // $table = new xmldb_table('local_coursetopics');
        // $field1 = new xmldb_field('teacherenabled');
        // $field1->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 0);
        // $field2 = new xmldb_field('groupid');
        // $field2->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 0);
        // if (!$dbman->field_exists($table, $field1)) {
        //     $dbman->add_field($table, $field1);
        // }
        // if (!$dbman->field_exists($table, $field2)) {
        //     $dbman->add_field($table, $field2);
        // }
        $table1 = new xmldb_table('local_coursegrouptopics');
        $table2 = new xmldb_table('local_coursegroupmodules');
        if (!$dbman->table_exists($table1)) {
            // Adding fields.
            $table1->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table1->add_field('open_path', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table1->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table1->add_field('topic', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table1->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table1->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table1->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table1->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table1->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

            // Adding key.
            $table1->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Create table.
            $dbman->create_table($table1);
        }
        if (!$dbman->table_exists($table2)) {
            // Adding fields.
            $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table2->add_field('open_path', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table2->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table2->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table2->add_field('topicid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table2->add_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table2->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table2->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table2->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table2->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

            // Adding key.
            $table2->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Create table.
            $dbman->create_table($table2);
        }
        upgrade_plugin_savepoint(true, 2023060600.22, 'local', 'courses');
    }

    if ($oldversion < 2023060600.28) {
        $time = time();
        $initcontent = array('name' => 'Course', 'shortname' => 'course', 'parent_module' => '0', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'courses');
        $parentid = $DB->get_field('local_notification_type', 'id', array('shortname' => 'course'));
        if (!$parentid) {
            $parentid = $DB->insert_record('local_notification_type', $initcontent);
        }

        $notification_type_data = array(array('name' => 'Pending Activity Work Reminder', 'shortname' => 'course_pending_work', 'parent_module' => $parentid, 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'courses'));
        foreach($notification_type_data as $notification_type){
            unset($notification_type['timecreated']);
            if(!$DB->record_exists('local_notification_type',  $notification_type)){
                $notification_type['timecreated'] = $time;
                $DB->insert_record('local_notification_type', $notification_type);
            }
        }

        upgrade_plugin_savepoint(true, 2023060600.28, 'local', 'courses');
    }

    return true;
}
