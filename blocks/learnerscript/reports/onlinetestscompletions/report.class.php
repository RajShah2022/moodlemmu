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

class report_onlinetestscompletions extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        // 'userfield' =>['userfield'],
        $this->columns = ['onlinetestfield'=>['onlinetestfield'],'userfield'=>['userfield'],'onlinetestscompletionscolumns' => ['onlinetestname','achievedgrade','completionstatus','completiondate']];
        $this->components = array('columns', 'filters', 'permissions');
        $this->filters = array('organization','departments', 'subdepartments','level4department', 'onlinetests','user','completionstatus');
        $this->orderable = array('onlinetestname');
        $this->defaultcolumn = 'ue.id';
        $this->userid = isset($report->userid) ? $report->userid : 0;
        $this->reportid = isset($report->reportid) ? $report->reportid : 0;
        $this->scheduleflag = isset($report->scheduling) ? true : false;
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(ra.id)";
    }
    function select() {
        $this->sql = "SELECT MAX(ue.id), ue.userid as userid, CONCAT(u.firstname,' ',u.lastname) AS fullname, c.id as courseid, c.open_categoryid,c.fullname as onlinetestname ,
                        cmc.timemodified as completiondate, cmc.completionstate as completionstatus,u.*,c.open_path  ";

    }
    function from() {
        $this->sql .= " FROM {user_enrolments} as ue";
    }
    function joins() {
        $this->sql .= " JOIN {user} u ON ue.userid = u.id
                        JOIN {enrol} as e ON e.id = ue.enrolid AND e.enrol IN ('auto','self','manual')
                        JOIN {role_assignments} as ra ON ra.userid = ue.userid
                        JOIN {context} AS cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                                                AND cxt.instanceid = e.courseid
                        JOIN {role} as r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')
                        JOIN {course} as c ON c.id = e.courseid 
                        JOIN {course_modules} as cm ON cm.course = c.id 
                        LEFT JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id  AND cmc.userid = ue.userid ";

          parent::joins();
    }
    function where(){
        global $USER, $CFG;
        $this->sql .=  " WHERE c.visible = 1 AND c.open_coursetype = :type AND open_module = :module ";
        $this->params['type'] = 1; 
        $this->params['module'] = 'online_exams';
        $costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        require_once($CFG->dirroot . "/blocks/learnerscript/lib.php");
        if (is_siteadmin()) {
            $this->sql .= "";
        } else if ($this->scheduleflag && $this->reportid!=0 && $this->userid != 0 ) {             
            $usercostcenterpathconcatsql = scheduled_report( $this->reportid,$this->scheduleflag,$this->userid,'c.open_path','u.open_path');
            $this->sql .= $usercostcenterpathconcatsql;     
        }else{
            $usercostcenterpathconcatsql = get_user_costcenterpath($USER->open_path);
            $costcenterpathconcatsql  = $costcenterpathconcatsql  . $usercostcenterpathconcatsql  ; 
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
      
        if (!empty($this->params['filter_user'])) {
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid']= $this->params['filter_user'];
        }   
         if(isset($this->params['filter_completionstatus'])){

            if ($this->params['filter_completionstatus'] == 1) {
            $this->sql .= " AND cmc.completionstate > 0 ";
            }

            if ($this->params['filter_completionstatus'] == 0) {
                $this->sql .= "  AND cmc.completionstate = 0 ";
            }
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
