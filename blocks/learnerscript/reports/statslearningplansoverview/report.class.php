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
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_statslearningplansoverview extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
        $columns = array('statslearningplanscolumns' => ['totallearningpaths','noofenrollments', 'noofcompletions','noofinprogress','percentofcompletions']);
        $this->columns = $columns;
        $this->defaultcolumn = 'lp.id'; 
        $this->enablestatistics = true;      
    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(lp.id) ";
    }

    function select() {

        $this->sql = " SELECT lp.id " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {local_learningplan} lp ";
    }

    function joins() {
        parent::joins();
    }

    function where() {
       global $USER;
        $this->sql .= "  WHERE 1=1 AND lp.visible = 1 ";
       
        $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();

        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
            $this->sql .= "";
        } else  {
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path',$org); 
            $this->sql .= $costcenterpathconcatsql;
        }

        parent::where();
    }

    function search() {
      
    }

    function filters() {
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }
        if ($this->params['filter_learningpath'] > 0) {
            $lplan = $this->params['filter_learningpath'];
            $this->sql .= " AND lp.id = $lplan ";
        }
        
        
    }

    public function get_rows($courses) {
       
        global $DB,$USER;
        $data = array();
        if(!is_siteadmin()){
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql('lp.open_path',$org);               
        }
        if($courses){
            $usercostcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
            $totalcountsql = "SELECT COUNT(lp.id) FROM {local_learningplan} lp WHERE lp.visible = 1 {$costcenterpathconcatsql} ";
            
            $enrolsql = "SELECT count(llu.id)
                    FROM {local_learningplan_user} as llu
                    JOIN {user} u ON u.id = llu.userid 
                    JOIN {local_learningplan} lp ON llu.planid = lp.id
                    WHERE lp.visible = 1 {$costcenterpathconcatsql} ";
            
            $completedsql = "SELECT count(llu.id)
                                FROM {local_learningplan_user} as llu
                                JOIN {user} u ON u.id = llu.userid 
                                JOIN {local_learningplan} lp ON llu.planid = lp.id
                                WHERE lp.visible = 1 
                                AND llu.status = 1 AND llu.completiondate IS NOT NULL {$costcenterpathconcatsql}  ";
           

            if($this->ls_startdate > 0 && $this->ls_enddate > 0){
                $enrolsql .= " AND llu.timecreated > :ls_startdate ";
                $completedsql .= " AND llu.completiondate > :ls_startdate ";
      
                $enrolsql .= " AND llu.timecreated < :ls_enddate ";
                $completedsql .= " AND llu.completiondate < :ls_enddate ";
            }
            $orgpath = get_orgpath($this->params);
            if(!empty($orgpath)){              
                $totalcountsql .= " AND concat(lp.open_path,'/') like '{$orgpath}' ";
                $enrolsql .= " AND concat(lp.open_path,'/') like '{$orgpath}'  ";
                $completedsql .= " AND concat(lp.open_path,'/') like '{$orgpath}'  ";
                if(!is_siteadmin()){
                    $enrolsql .= " AND concat(u.open_path,'/') like '{$orgpath}' ";
                    $completedsql .= " AND concat(u.open_path,'/') like '{$orgpath}' "; 
                }
            }
            $data['totallearningpaths'] = $DB->get_field_sql($totalcountsql) ;
            
            foreach($this->columns['statslearningplanscolumns'] as $key=>$column){
                $data['noofenrollments'] = $DB->get_field_sql($enrolsql,array('ls_fstartdate' => $this->ls_startdate, 'ls_fenddate' => $this->ls_enddate));
                $data['noofcompletions'] = $DB->get_field_sql($completedsql,array('ls_fstartdate' => $this->ls_startdate, 'ls_fenddate' => $this->ls_enddate)); 
            }
            if(!empty($data)){

                $data['noofinprogress'] = ($data['noofcompletions'] > 0 && $data['noofenrollments'] > 0) ? $data['noofenrollments']-$data['noofcompletions'] : 0 ;
    
                $data['progress'] = ($data['noofcompletions'] > 0 && $data['noofenrollments'] > 0) ? round(($data['noofcompletions']/$data['noofenrollments'])*100) : 0 ;
    
                $data['percentofcompletions'] =$data['progress'].'%';
                return array((object)$data);
            }       
           
        }
       
        return $data;
     
    }
}
