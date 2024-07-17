<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
defined('MOODLE_INTERNAL') || die();
function xmldb_local_costcenter_upgrade($oldversion) {
	global $DB, $CFG;
	$dbman = $DB->get_manager();
	if ($oldversion < 2017051505) {
		$table = new xmldb_table('local_costcenter');
		$field = new xmldb_field('multipleorg', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		upgrade_plugin_savepoint(true, 2017051505, 'local', 'costcenter');
	}
	if ($oldversion < 2017051509) {
		$table = new xmldb_table('local_costcenter');
		$field = new xmldb_field('costcenter_logo', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		upgrade_plugin_savepoint(true, 2017051506, 'local', 'costcenter');
	}
	if ($oldversion < 2017051511.09) {
		$table = new xmldb_table('local_costcenter');
		$field = new xmldb_field('shell', XMLDB_TYPE_CHAR, '50', null, NULL, null, null);
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		upgrade_plugin_savepoint(true, 2017051511.09, 'local', 'costcenter');
	}
    //local_costcenter
    if ($oldversion <  2022101100) {
        $table = new xmldb_table('local_costcenter');
        $field = new xmldb_field('button_color', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field1 = new xmldb_field('brand_color', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
       upgrade_plugin_savepoint(true,2022101100, 'local', 'costcenter');
    }



    if ($oldversion <  2022101300.03) {
        $table = new xmldb_table('local_costcenter');
        
        $field2 = new xmldb_field('hover_color', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        upgrade_plugin_savepoint(true,2022101300.03, 'local', 'costcenter');
    }

	if ($oldversion <  2023060100.01) {
        $table = new xmldb_table('local_costcenter');

        $field = new xmldb_field('profileinfocategory', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('courseinfocategory', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true,2023060100.01, 'local', 'costcenter');
    }
    if ($oldversion <  2023060100.02) {
        $table = new xmldb_table('local_costcenter');

        $field = new xmldb_field('eventcolor', XMLDB_TYPE_CHAR, '225', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true,2023060100.02, 'local', 'costcenter');
    }

    if ($oldversion <  2023060100.05) { 
        $costcenters = $DB->get_records('local_costcenter',  array('parentid' => 0 , 'profileinfocategory' => 0));
       
        if(!empty($costcenters)){
            foreach($costcenters as $costcenter){
                $userinfocategory = new stdClass();
                $userinfocategory->name = $costcenter->shortname;
                $sortordersql = "SELECT sortorder from {user_info_category} order by sortorder desc ";
                $sortorderrec = $DB->get_record_sql($sortordersql, array(), '0', '1');
                $userinfocategory->sortorder = $sortorderrec->sortorder+1;
                $profileinfocategory = $DB->insert_record('user_info_category', $userinfocategory);

                $datarecord = new \stdClass();
                $datarecord->id = $costcenter->id;
                $datarecord->profileinfocategory = $profileinfocategory;
                $DB->update_record('local_costcenter',  $datarecord);
            }           
        } 
        upgrade_plugin_savepoint(true,2023060100.05, 'local', 'costcenter');
    }
	return true;
}
