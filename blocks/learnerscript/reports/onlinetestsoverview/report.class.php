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

class report_onlinetestsoverview extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns =['onlinetestfield'=>['onlinetestfield'],'onlinetestsoverviewcolumns' => ['onlinetestname','enrolmentscount','completionscount']];
        $this->components = array('columns', 'filters', 'permissions','plot','orderable');
        $this->filters = array('organization', 'departments', 'subdepartments','level4department','onlinetests');
        $this->orderable = array('onlinetestname','enrolmentscount','completionscount');
        $this->defaultcolumn = 'c.id';
        $this->userid = isset($report->userid) ? $report->userid : 0;
        $this->reportid = isset($report->reportid) ? $report->reportid : 0;
        $this->scheduleflag = isset($report->scheduling) ? true : false;
    }
   function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(c.id)";
    }
    function select() {
        global $USER;
        if(!is_siteadmin()){
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql('u.open_path',$org);               
        }
        $this->sql =  "SELECT c.id as onlinetestid,c.fullname as onlinetestname,
                        (SELECT COUNT(u.id)
                            FROM {role_assignments} ra
                            JOIN {context} cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                            JOIN {role} r ON r.id = ra.roleid
                            JOIN {user} u ON ra.userid = u.id
                            WHERE  r.shortname  IN ('employee','student')
                                AND cxt.instanceid = c.id {$costcenterpathconcatsql} AND c.open_coursetype = 1 AND open_module = 'online_exams' ) as enrolmentscount,
                        (SELECT COUNT(u.id) 
                                FROM {role_assignments} ra
                                JOIN {context} cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                                JOIN {role} r ON r.id = ra.roleid
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND r.shortname  IN ('employee','student')
                                JOIN {course_modules} as cm ON cm.course = c.id 
                                JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id AND u.id = cmc.userid
                                WHERE c.id = cxt.instanceid AND cmc.completionstate > 0 {$costcenterpathconcatsql }
                                AND c.open_module= 'online_exams' AND c.open_coursetype = 1
                                )as completionscount ";

        parent::select();
    }
    function from() {
        $this->sql .= "  FROM {course} c ";
    }
    function joins() {
          parent::joins();
    }
    function where(){
        global $CFG;
        $this->sql .=  " WHERE 1 = 1 AND c.open_coursetype = :type AND open_module = :module";
        $this->params['type'] = 1;
        $this->params['module'] = 'online_exams';
        
        $costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        require_once($CFG->dirroot . "/blocks/learnerscript/lib.php");
        if (is_siteadmin()) {
            $this->sql .= "";
        } else if ($this->scheduleflag && $this->reportid!=0 && $this->userid != 0 ) {             
            $usercostcenterpathconcatsql = scheduled_report( $this->reportid,$this->scheduleflag,$this->userid,'c.open_path','');
            $this->sql .= $usercostcenterpathconcatsql;     
        }else{
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
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }
    
        if (!empty($this->params['filter_onlinetests'])) {
            $this->sql .= " AND c.id = :coursename";
            $this->params['coursename'] = $this->params['filter_onlinetests'];
        }

        if($this->ls_startdate > 0 && $this->ls_enddate > 0){
            $this->sql .= " AND cmc.timemodified  > :report_startdate ";
            $this->params['report_startdate'] = $this->ls_startdate;

            $this->sql .= " AND cmc.timemodified  < :report_enddate ";
            $this->params['report_enddate'] = $this->ls_enddate;
        }
    }    
    public function get_rows($onlinetests) {
        return $onlinetests;
    }
}
