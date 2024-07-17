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
namespace local_custom_matrix;
use stdClass;
class querylib{
	function __construct()
	{
		global $DB, $CFG, $OUTPUT,  $USER, $PAGE;
		$this->db = $DB;
		$this->user = $USER;
	}	
	function matrix_exist($data){
	    return $this->db->record_exists('local_custom_category', $data);
	}

	function get_matrixfield($field, $data){
    	return $this->db->get_field('local_custom_category',$field, $data);
	}

	function matrix_child_count($data){
		return $this->db->count_records('local_custom_category', $data);
	}

	function get_matrixshortname($data){
	    return $this->db->get_record_sql('SELECT * FROM {local_custom_category} WHERE shortname = ? AND  id <> ? AND costcenterid = ?', $data);
	}

	function matrix_record($data){
	    return $this->db->get_record('local_custom_category', $data);
	}
	//For fetching multiple records
	function matrix_records($data){
	    return $this->db->get_records('local_custom_category', $data);
	}
	//For fetching multiple records
	function join_performance_matrix_records($data){
		$sql = "SELECT cm.id, cm.fullname, cm.parentid,cm.type,pm.id as pmid,pm.maxscore,pm.weightage,pm.role,pm.type FROM {local_custom_category} cm LEFT JOIN {local_performance_matrix} pm ON cm.id=pm.performancecatid WHERE cm.costcenterid = :costcenter AND role = :role"; 
		if(isset($data['performancecatid'])){
			$sql .= " AND pm.performancecatid = :performancecatid";
		}
		if(isset($data['type'])){
			$sql .= " AND cm.type = :type";
		}
		if(isset($data['templateid'])){
			$sql .= " AND pm.templateid = :templateid";
		}
    	$result = $this->db->get_records_sql($sql, $data); 
    	return $result;
	}
	//For fetching single record
	function join_performance_matrix_record($data){
		$sql = "SELECT cm.id, cm.fullname, cm.parentid,cm.type,pm.id as pmid,pm.maxscore,pm.weightage,pm.role,pm.type FROM {local_custom_category} cm LEFT JOIN {local_performance_matrix} pm ON cm.id=pm.performancecatid WHERE costcenterid = :costcenter AND role = :role"; 
		if(isset($data['performancecatid'])){
			$sql .= " AND pm.performancecatid = :performancecatid";
		}
		if(isset($data['type'])){
			$sql .= " AND cm.type = :type";
		}
		
    	$result = $this->db->get_record_sql($sql, $data); 
    	return $result;
	}
	
	
	function performance_matrix($data){
		return $this->db->get_record('local_performance_matrix', $data);
	}
	function performance_matrix_all($data){
		return $this->db->get_records('local_performance_matrix', $data);
	}
	function performance_matrix_count($data){
		return $this->db->count_records('local_performance_matrix', $data);
	}
	//For fetching performance overall record based on where condition
	function performance_matrix_overall($data){
		return $this->db->get_record('local_performance_overall', $data);
	}
	function performance_logs($data){
		return $this->db->get_record('local_performance_logs', $data);
	}
	//For fetching multiple records	
	function performance_logs_records($data){
		return $this->db->get_records('local_performance_logs', $data);
	}
	//For fetching the periods to show in select dropdown
	function get_periods($data){
		$sql = "SELECT DISTINCT period,financialyear,year FROM {local_performance_overall} WHERE userid = :userid AND year !=:year AND period != 'M'";
		$result = $this->db->get_records_sql($sql, $data); 

    	return $result;

	}
	function get_periods_months($data){
		$query = "SELECT * FROM {local_performance_overall} WHERE userid = :userid AND period = 'M' AND month!=:month OR year!=:year GROUP BY templateid,month,year";				
		$result = $this->db->get_records_sql($query, $data); 		
    	return $result;
	}
	function performance_monthly($data){
		return $this->db->get_record('local_performance_monthly', $data);
	}
	function get_performancelog_groupby($data){
		$groupbysql = "";
		$sql = "SELECT *,SUM(pointsachieved) as pointsachieved FROM {local_performance_logs} ";
		$wheresql = " WHERE type = :type AND period = :period" ;
		
		if($data['period'] == 'M'){
			$groupbysql .= " GROUP BY performancecatid,userid,financialyear,month ";
		}else{
			$groupbysql .= " GROUP BY performancecatid,userid,financialyear ";
		}
		$log_intrecords = $this->db->get_records_sql($sql.$wheresql.$groupbysql,$data);
		return $log_intrecords;
	}
	function performance_quarterly($data){
		return $this->db->get_record('local_performance_quarterly', $data);
	}
	function delete_local_performance_log($data){		
		return $this->db->delete_records('local_performance_logs', $data);
	}
	function delete_local_performance_overall($data){		
		return $this->db->delete_records('local_performance_overall', $data);
	}
	function get_performance_log_month($data){
		$sql = "SELECT DISTINCT month FROM {local_performance_logs} WHERE performancecatid =:performancecatid AND moduleid=:moduleid";
	    
	    return $this->db->get_field_sql($sql,$data);
	}
	function get_performance_log_year($data){
		$sql = "SELECT DISTINCT year FROM {local_performance_logs} WHERE performancecatid =:performancecatid AND moduleid=:moduleid";
	    
	    return $this->db->get_field_sql($sql,$data);
	}
	function get_user_financialyear($data){
		$sql = "SELECT DISTINCT financialyear,year FROM {local_performance_overall} WHERE userid = :userid AND year !=:year";
		$result = $this->db->get_records_sql($sql, $data); 
    	return $result;
	}
	function internal_performance_subcategories($data){
		
		$query = "SELECT * FROM {local_performance_matrix} WHERE templateid = :templateid AND role = :role AND type=:type AND parentid =:parentid AND performancecatid != 0";				
		$result = $this->db->get_records_sql($query, $data); 		
    	return $result;
	}
	function template_record($data){
	    return $this->db->get_record('local_performance_template', $data);
	}
	function get_templatefield($field, $data){
    	return $this->db->get_field('local_performance_template',$field, $data);
	}
	
	
}
