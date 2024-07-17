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
 * @package   local_costcenter
 * @copyright 2023 Moodle India Information Solutions Pvt Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_costcenter\lib;
use stdClass;
use moodle_url;

class costcenter {

    // protected $db = null;
    protected $user = null;

    function __construct () {
        global $DB, $CFG, $OUTPUT, $USER, $PAGE;
        $this->db = $DB;
        $this->user = $USER;
    }

    /**
     * Get the specific field value for costcenter
     * 
     * @param string $field the field to return the value of.
     * @param array $data optional array $fieldname=>requestedvalue with AND in between
     * @return mixed the specified value false if not found
     */
    public static function costcenter_field($field, $data) {
        global $DB;
        return $DB->get_field('local_costcenter', $field, $data);
    }

    /**
     * Get a single database record as an object where all the given conditions met for costcenter.
     * 
     * @param array $data optional array $fieldname=>requestedvalue with AND in between
     * @param string $fields A comma separated list of fields to be returned from the chosen table.
     * @param int $multidata optional value (IGNORE_MISSING | IGNORE_MULTIPLE | MUST_EXIST)
     * @return mixed a fieldset object containing the first matching record, false or exception if error not found depending on mode
     */
    public static function costcenter_record($data, $fields = '*', $multidata = false) {
        global $DB;
        return $DB->get_record('local_costcenter', $data, $fields, $multidata);
    }

    /**
     * Get a number of records as an array of objects where all the given conditions met for costcenter.     
     *
     * @param array $data optional array $fieldname=>requestedvalue with AND in between
     * @param string $fields a comma separated list of fields to return (optional, by default
     *   all fields are returned). The first field will be used as key for the
     *   array so must be a unique field such as 'id'.
     * @return array An array of Objects indexed by first column.
     */
    public static  function costcenter_records($data, $fields = '*') {
        global $DB;
        return $DB->get_records('local_costcenter', $data, $fields);
    }

    /**
     * Get a single database record as an object using a SQL statement.
     *
     * The SQL statement should normally only return one record.
     *
     * @param string $fields a comma separated list of fields to return,
     * must be a unique field such as 'id'.
     * @param array $data array of sql parameters
     * @param string $costcenterpathconcatsql list of sql condition
     * @return mixed a fieldset object containing the first matching record, false or exception if error not found depending on mode
     */
    public static function costcenter_record_sql($fields, $data, $costcenterpathconcatsql = false) {
        global $DB;
        $costcentersql = "SELECT ".$fields."
                            FROM {local_costcenter} lc
                           WHERE lc.id = :id ".$costcenterpathconcatsql ;
        return $DB->get_record_sql($costcentersql, $data);
    }

    /**
     * Get the first two columns from a number of records as an associative array using a SQL statement.
     *
     * @param string $fields a comma separated list of fields to return,
     * must be a unique field such as 'id'.
     * @param array $data array of sql parameters
     * @param string $costcenterpathconcatsql list of sql condition
     * @return array an associative array
     */
    public static function costcenter_records_sql_menu($fields, $data, $costcenterpathconcatsql = false) {
        global $DB;
        $depsql = "SELECT ".$fields." 
                     FROM {local_costcenter} lc 
                    WHERE parentid = :parentid ".$costcenterpathconcatsql;
        return $DB->get_records_sql_menu($depsql, $data);
    }

    /**
     * Test whether a record exists in a table where all the given conditions met for costcenter.
     *
     * @param array $data optional array $fieldname=>requestedvalue with AND in between
     * @param int $multidata optional value (IGNORE_MISSING | IGNORE_MULTIPLE | MUST_EXIST)
     * @param string $fields A comma separated list of fields default all.
     * @return bool true if a matching record exists, else false.
     */
    public static function costcenter_exist($data, $multidata = false, $fields = '*') {
        global $DB;
        return $DB->record_exists('local_costcenter', $data, $fields, $multidata);
    }

    /**
     * @method get_next_child_sortthread Get costcenter child list
     * @param  int $parentid which is id of a parent costcenter
     * @param  [string] $table is a table name 
     * @return list of costcenter children
     * */
    public static function get_next_child_sortthread($parentid, $table) {
        global $DB, $CFG;

        $maxthread = $DB->get_record_sql("SELECT MAX(sortorder) sortorder
                                            FROM {$CFG->prefix}{$table}
                                           WHERE parentid = :parentid", array('parentid' => $parentid)
                                        );
        
        if (!$maxthread || strlen($maxthread->sortorder) == 0) {
            if ($parentid == 0) {
                // first top level item
                return self::inttovancode(1);
            } else {
                // parent has no children yet
                return self::costcenter_field('sortorder', array('id' => $parentid)) . '.' . self::inttovancode(1);
            }
        }
        return self::increment_sortorder($maxthread->sortorder);
    }

    /**
     * Convert an integer to a vancode
     * 
     * @param int $int integer to convert.
     * @return vancode The vancode representation of the specified integer
     */
    public static function inttovancode($int = 0) {
        $num = base_convert((int) $int, 10, 36);
        $length = strlen($num);
        return chr($length + ord('0') - 1) . $num;
    }

    /**
     * Convert a vancode to an integer
     * 
     * @param string $char Vancode to convert. Must be <= '9zzzzzzzzzz'
     * @return integer The integer representation of the specified vancode
     */
    public static function vancodetoint($char = '00') {
        return base_convert(substr($char, 1), 36, 10);
    }

    /**
     * Increment a vancode by N (or decrement if negative)
     *
     */
    public static function increment_vancode($char, $inc = 1) {
        return self::inttovancode(self::vancodetoint($char) + (int) $inc);
    }

    /**
     * Increment a sortorder by N (or decrement if negative)
     *
     */
    public static function increment_sortorder($sortorder, $inc = 1) {
        if (!$lastdot = strrpos($sortorder, '.')) {
            // root level, just increment the whole thing
            return self::increment_vancode($sortorder, $inc);
        }
        $start = substr($sortorder, 0, $lastdot + 1);
        $last = substr($sortorder, $lastdot + 1);
        // increment the last vancode in the sequence
        return $start . self::increment_vancode($last, $inc);
    }

    /**
     * Get a single database record as an object using a SQL statement.
     * 
     * @return mixed a fieldset object containing the first matching record, false or exception if error not found depending on mode
     */
    public static function get_costcenter_theme() {
        global $USER, $DB;
        if (!is_siteadmin()) {
            $path = (new \local_costcenter\lib\accesslib())::get_user_role_switch_path();
            $orgid = ($path) ? explode('/',$path[0])[1] : null;
            if ($orgid == NULL) {
                $orgid = (empty($path)) ? explode('/',$USER->open_path)[1] : null;
            }
            if ($orgid) {
                $fields = 'lc.theme, lc.button_color, lc.brand_color, lc.hover_color';
                $id = array('id' => $orgid);
                $condition = 'lc.visible = 1';
            } else {
                return false;
            }
            if (!empty($costcentertheme = self::costcenter_record_sql($fields, $id, $condition))) {
                return $costcentertheme;
            }
        } else {
            return false;
        }
    }

    /**
     * Get hierarchy wise navigation/breadcrumb bar.
     * 
     * @param int $depth level of hierarchy
     * @param int $parentid of current hierarchy
     * 
     * @return stdClass object containing
     */
    public static function costcenterpagenavbar($depth, $parentid) {
        global $DB, $USER, $PAGE;
    
        $PAGE->navbar->add(get_string('dashboard', 'local_costcenter'), new moodle_url('/my/dashboard.php'));
        if (is_siteadmin()) {
            $PAGE->navbar->add(get_string('orgmanage', 'local_costcenter'), new moodle_url('/local/costcenter/index.php'));
        }
        $accessdepth = (isset($USER->useraccess)) ? $USER->useraccess['currentroleinfo']['depth'] : 0;
        $nodes[] = [];
        $prevparent = $parentid;
        for ($i = $accessdepth; $i <= $depth; $i++) {
            ${'hierarchy_'.$i} = self::costcenter_record(array('id'=>$prevparent),'id, fullname, parentid');
            $nodes[] = ${'hierarchy_'.$i};
            $prevparent = ${'hierarchy_'.$i}->parentid;
        }
        $nodes = array_reverse($nodes);

        foreach (array_filter($nodes) AS $node) {
            $PAGE->navbar->add($node->fullname, new moodle_url('/local/costcenter/costcenterview.php', array('id' => $node->id)));
        }
            
        $PAGE->navbar->add(get_string('viewcostcenter_'.$depth, 'local_costcenter'));

        return $PAGE;
    }
}
