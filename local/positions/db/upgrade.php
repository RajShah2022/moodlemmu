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
function xmldb_local_positions_upgrade($oldversion) {
	global $DB, $CFG;
	$dbman = $DB->get_manager();
	if ($oldversion < 2016080911.07) {
		$table = new xmldb_table('local_positions');
		$field1 = new xmldb_field('path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
		if (!$dbman->field_exists($table, $field1)) {
			$dbman->add_field($table, $field1);
		}
		$field2 = new xmldb_field('depth', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
		if (!$dbman->field_exists($table, $field2)) {
			$dbman->add_field($table, $field2);
		}
		$field3 = new xmldb_field('sortorder', XMLDB_TYPE_CHAR, '255', null, NULL, null, null);
		if (!$dbman->field_exists($table, $field3)) {
			$dbman->add_field($table, $field3);
		}
		upgrade_plugin_savepoint(true, 2016080911.07, 'local', 'positions');
	}
	return true;
}