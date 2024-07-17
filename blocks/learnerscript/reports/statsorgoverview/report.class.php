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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_statsorgoverview extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->columns = array('statsorgoverviewcolumns' => ['totalorg','totaldepartments', 'totalcourses', 'totallp' ,'totalilts', 'totalprogram', 'totalactuser']);
        $this->enablestatistics = true;
        $this->defaultcolumn = 'c.id';   

    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql  = " SELECT COUNT(DISTINCT (SELECT COUNT(DISTINCT c.id) AS totalorg
                            FROM {local_costcenter} c
                            WHERE c.depth = 1 AND c.visible = 1 )) ";
    
    }
    function select() {
        $this->sql = "SELECT c.id ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_costcenter} c ";
    }
    function joins() {
        parent::joins();
    }
    function where() {
        global $CFG,$USER;
        $this->sql .= " WHERE c.depth = 1 AND c.visible = 1 ";
      
        // getscheduled report
        require_once($CFG->dirroot . "/blocks/learnerscript/lib.php");
        if (is_siteadmin()) {
            $this->sql .= "";
        } else if ($this->scheduleflag && $this->reportid!=0 && $this->userid != 0 ) {             
            $usercostcenterpathconcatsql = scheduled_report( $this->reportid,$this->scheduleflag,$this->userid,'c.path','');
            $this->sql .= $usercostcenterpathconcatsql;     
        }else{
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql('c.path',$org);           
            $this->sql .= $costcenterpathconcatsql;
        } 
        
        parent::where();
    }
    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("c.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters() {
   
    }

    public function get_rows($costcenters) {

        global $DB,$USER;
      
        $data = array();
        $costcenterpathconcatsql = '';
        if(!is_siteadmin()){
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql('c.path', $org);             
            $othercostcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql('oc.open_path', $org);             
        }
       
        
        if($costcenters){         
            
             $totalorgsql = $DB->count_records_sql("SELECT COUNT(c.id) AS totalorg FROM {local_costcenter} c WHERE c.depth = 1 AND c.visible = 1 {$costcenterpathconcatsql} ");
            if ($this->params['filter_organization'] > 0) {
                $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
                $orgpath = $orgpath.'/%'; 
                $totalorgsql = $DB->count_records_sql("SELECT COUNT(c.id) AS totalorg FROM {local_costcenter} c WHERE c.depth = 1 AND c.visible = 1 {$costcenterpathconcatsql} AND concat(c.path,'/') like '{$orgpath}'");   
            }
           $orgpath = get_orgpath($this->params);
            $data['totalorg'] = $totalorgsql;
            $sql = "SELECT COUNT(c.id) FROM {local_costcenter} c WHERE c.depth = :depth AND c.visible = :visible {$costcenterpathconcatsql} ";
            if(!empty($orgpath))
            {
                $sql .= " AND concat(c.path,'/') like '{$orgpath}' ";
            }
            $data['totaldepartments'] = $DB->count_records_sql($sql, array('depth' => 2,'visible' => 1));

            $sql = "SELECT COUNT(oc.id) FROM {course} oc WHERE  oc.open_coursetype = :type AND oc.visible = :visible {$othercostcenterpathconcatsql} ";
            if(!empty($orgpath))
            {
                $sql .= " AND concat(oc.open_path,'/') like '{$orgpath}' ";
            }
            $data['totalcourses']  = $DB->count_records_sql($sql,array('type' => 0,'visible' => 1));
            $sql = "SELECT COUNT(oc.id) FROM {local_learningplan} oc WHERE  oc.visible = 1 {$othercostcenterpathconcatsql} ";
            if(!empty($orgpath))
            {
                $sql .= " AND concat(oc.open_path,'/') like '{$orgpath}' ";
            }
            $data['totallp'] = $DB->count_records_sql($sql);
      
            $sql = "SELECT COUNT(oc.id) FROM {local_classroom} oc WHERE  oc.visible = 1 {$othercostcenterpathconcatsql} ";
            if(!empty($orgpath))
            {
                $sql .= " AND concat(oc.open_path,'/') like '{$orgpath}' ";
            }
            $data['totalilts'] = $DB->count_records_sql($sql,array());
           
            $sql = "SELECT COUNT(oc.id) FROM {local_program} oc WHERE  oc.visible = 1 {$othercostcenterpathconcatsql} ";
            if(!empty($orgpath))
            {
                $sql .= " AND concat(oc.open_path,'/') like '{$orgpath}' ";
            }
            $data['totalprogram'] = $DB->count_records_sql($sql,array());
            
            $sql = "SELECT COUNT(oc.id) FROM {user} oc WHERE oc.suspended = 0 AND oc.deleted = 0 AND oc.deleted = 0 AND oc.suspended = 0 {$othercostcenterpathconcatsql} ";
            if(!empty($orgpath))
            {
                $sql .= " AND concat(oc.open_path,'/') like '{$orgpath}' ";
            }
            $data['totalactuser'] = $DB->count_records_sql($sql,array()); 
        }
        if(!empty($data)){
            return array((object)$data);
        }    
        return $data;
    }

}
