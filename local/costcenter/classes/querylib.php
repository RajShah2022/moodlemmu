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
 * @package BizLMS
 * @subpackage querylib.php
 */
namespace local_costcenter;
use stdClass;
global $DB;
class querylib{
	function __construct()
	{
		global $DB, $CFG, $OUTPUT,  $USER, $PAGE;
		$this->db = $DB;
		$this->user = $USER;
	}
	
	function costcenter_field($field, $data){
	    return $this->db->get_field('local_costcenter', $field, $data);
	}

    function costcenter_record($data, $fields = '*', $multidata = false){
        return $this->db->get_record('local_costcenter', $data, $fields, $multidata);
    }

    function costcenter_records($data, $fields = '*'){
        return $this->db->get_records('local_costcenter', $data, $fields);
    }

    function costcenter_record_sql($fields, $data, $costcenterpathconcatsql = false){
        $costcentersql = "SELECT ".$fields."
                    FROM {local_costcenter} AS lc WHERE lc.id = :id ".$costcenterpathconcatsql ;
        return $this->db->get_record_sql($costcentersql, $data);
    }

    function costcenter_records_sql_menu($fields, $data, $costcenterpathconcatsql = false){
        $depsql = "SELECT ".$fields." 
            FROM {local_costcenter} as lc 
            WHERE parentid = :parentid ".$costcenterpathconcatsql;
        return $this->db->get_records_sql_menu($depsql, $data);
    }

    function costcenter_exist($data, $multidata = false, $fields = '*'){
        return $this->db->record_exists('local_costcenter', $data, $fields, $multidata);
    }

    function get_costcenterfield($field, $data){
	    return $this->db->get_field('local_costcenter', $field, $data);
	}
}
