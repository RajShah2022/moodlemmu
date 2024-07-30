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

/**
 * This file keeps track of upgrades to the ltiprovider plugin
 *
 * @package    local
 * @subpackage costcenter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_costcenter_install(){
    global $DB, $CFG;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    $table = new xmldb_table('event');
    if ($dbman->table_exists($table)) {
        $pluginfield = new xmldb_field('plugin', XMLDB_TYPE_CHAR, '225', null, null, null, null);
        if (!$dbman->field_exists($table, $pluginfield)) {
            $field1 = new xmldb_field('plugin');
            $field1->set_attributes(XMLDB_TYPE_CHAR, '225', null, null, null, null);
            $dbman->add_field($table, $field1);
        }

        $pluginfield = new xmldb_field('plugin_instance', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
        if (!$dbman->field_exists($table, $pluginfield)) {
            $field2 = new xmldb_field('plugin_instance');
            $field2->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
            $dbman->add_field($table, $field2);
        }

        $pluginfield = new xmldb_field('relateduserid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
        if (!$dbman->field_exists($table, $pluginfield)) {
            $field3 = new xmldb_field('relateduserid');
            $field3->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
            $dbman->add_field($table, $field3);
        }

        $pluginfield = new xmldb_field('local_eventtype', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        if (!$dbman->field_exists($table, $pluginfield)) {
            $field4 = new xmldb_field('local_eventtype');
            $field4->set_attributes(XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $dbman->add_field($table, $field4);
        }

        $pluginfield = new xmldb_field('plugin_itemid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
        if (!$dbman->field_exists($table, $pluginfield)) {
            $field5 = new xmldb_field('plugin_itemid');
            $field5->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
            $dbman->add_field($table, $field5);
        }
    }

    // data insertion.
    $time = time();
    $defaultcategory = array('name' => 'MathMeUp', 'idnumber' => 'MathMeUp', 'parent' => '0', 'timemodified' => $time, 'depth' => 1,'path' => '/1');
    $categoryid = $DB->insert_record('course_categories', $defaultcategory);

    $organization = array('fullname' => 'MathMeUp', 'shortname' => 'MathMeUp', 'parentid' => '0', 'timecreated' => $time, 'usermodified' => 2,'timemodified' => NULL, 'path' => '/1', 'depth' => 1, 'sortorder' => 01, 'category' => $categoryid, 'profileinfocategory' => 1, 'courseinfocategory' => 1, 'multipleorg' => 1 );
    $organizationid = $DB->insert_record('local_costcenter', $organization);
    if (function_exists('blocks_add_default_org_blocks')) {
        require_once($CFG->dirroot . '/local/costcenter/lib.php');
        blocks_add_default_org_blocks($organizationid);
    }
}
