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

class report_statscoursesoverview extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
        $columns = array('statscoursesoverviewcolumns' => ['totalcourses','noofenrollments', 'noofcompletions','noofinprogress','percentofcompletions']);
        $this->columns = $columns;
        $this->defaultcolumn = 'c.id'; 
        $this->enablestatistics = true;      
    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql  = " SELECT COUNT(DISTINCT (SELECT COUNT(DISTINCT c.id) AS totalcourses
                            FROM {course} c 
                            JOIN {local_costcenter} AS co ON co.path = c.open_path
                            JOIN {course_categories} AS cc ON cc.id = c.category
                            WHERE c.id <> 1 AND c.open_coursetype = 0 AND c.visible  =1 )) ";
    }

    function select() {

        $this->sql = " SELECT c.id " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {course} c ";
    }

    function joins() {
        parent::joins();
    }

 
    function where() {
        global $USER;
       
        $this->sql .= " WHERE c.id <> :siteid AND c.open_coursetype = :type ";//$costcenterpathconcatsql
       
        $this->params['siteid'] = SITEID;
        $this->params['type'] = 0;
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
       
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
            $this->sql .= "";
        } else  {
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path',$org);
            $this->sql .= $costcenterpathconcatsql;
        }

        parent::where();
    }

    function search() {
      
    }

    function filters() {     
        
    }

    public function get_rows($courses) {
      
        global $DB,$USER;
        $data = array();
        if(!is_siteadmin()){
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql('c.open_path',$org);               
        }

       if($courses){
           $totalcountsql = "SELECT COUNT(c.id) FROM {course} c WHERE c.id <> 1  AND c.open_coursetype = 0 AND c.visible = 1 {$costcenterpathconcatsql}";
            $usercostcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
            $enrolsql = "SELECT COUNT(DISTINCT ue.id) 
                            FROM {user_enrolments} ue
                            JOIN {enrol} e ON ue.enrolid = e.id
                            JOIN {role_assignments} ra ON ra.userid = ue.userid
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')
                            JOIN {user} u ON ra.userid = u.id 
                            JOIN {context} AS ctx ON ctx.id = ra.contextid
                            JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 AND c.open_coursetype = 0
                            WHERE e.courseid = c.id {$costcenterpathconcatsql} {$usercostcenterpathconcatsql} ";//
        
                                   
            $completedsql = "SELECT (COUNT(DISTINCT cc.id)) 
                                FROM {user_enrolments} ue
                                JOIN {enrol} e ON ue.enrolid = e.id
                                JOIN {role_assignments} ra ON ra.userid = ue.userid
                                JOIN {role} r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')
                                JOIN {user} u ON ra.userid = u.id
                                JOIN {context} AS ctx ON ctx.id = ra.contextid
                                JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 AND c.open_coursetype = 0
                                LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0
                                WHERE e.courseid = c.id {$costcenterpathconcatsql} {$usercostcenterpathconcatsql} "; // {$costcenterpathconcatsql}

            if($this->ls_startdate > 0 && $this->ls_enddate > 0){
                $enrolsql .= " AND ra.timemodified > :ls_fstartdate ";
                $completedsql .= " AND cc.timecompleted > :ls_fstartdate ";
           
                $enrolsql .= " AND ra.timemodified < :ls_fenddate ";
                $completedsql .= " AND cc.timecompleted < :ls_fenddate ";
            }           

            $orgpath = get_orgpath($this->params);
            if(!empty($orgpath)){
                $totalcountsql .= " AND concat(c.open_path,'/') like '{$orgpath}' ";
                $enrolsql .= " AND concat(c.open_path,'/') like '{$orgpath}'  ";
                $completedsql .= " AND concat(c.open_path,'/') like '{$orgpath}'  ";
                if(!is_siteadmin()){
                    $enrolsql .= " AND concat(u.open_path,'/') like '{$orgpath}' ";
                    $completedsql .= " AND concat(u.open_path,'/') like '{$orgpath}' "; 
                }
            }
            $data['totalcourses'] = $DB->count_records_sql($totalcountsql) ;
            
            foreach($this->columns['statscoursesoverviewcolumns'] as $key=>$column){
                $data['noofenrollments'] = $DB->count_records_sql($enrolsql,array('ls_fstartdate' => $this->ls_startdate, 'ls_fenddate' => $this->ls_enddate));
                $data['noofcompletions'] = $DB->count_records_sql($completedsql,array('ls_fstartdate' => $this->ls_startdate, 'ls_fenddate' => $this->ls_enddate)); 
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
