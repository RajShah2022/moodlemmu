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

class report_statsclassroomoverview extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
        $columns = array('statsclassroomcolumns' => ['totalclassrooms','newclassroomcount','totalactive','totalhold','totalcancelled','totalcompleted','noofenrollments', 'noofcompletions','totalsessions','percentofcompletions']);
        $this->columns = $columns;
        $this->defaultcolumn = 'lc.id'; 
        $this->enablestatistics = true;      
    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(DISTINCT (SELECT COUNT(DISTINCT lc.id) AS totalclassrooms
                            FROM {local_classroom} lc
                            WHERE 1=1 AND lc.visible = 1 )) ";
    }

    function select() {

        $this->sql = " SELECT lc.id " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }

    function joins() {
        parent::joins();
    }

    function where() {
        global $USER;
        $this->sql .= "  WHERE 1=1 AND lc.visible = 1 ";
       
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
        
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
            $this->sql .= "";
        } else  {
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql('lc.open_path',$org);           
            $this->sql .= $costcenterpathconcatsql;
        }

        parent::where();
    }

    function search() {
      
    }

    function filters() {           
        
    }

    public function get_rows($classrooms) {
       
        global $DB,$USER;
        $data = array();
        if(!is_siteadmin()){
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql('lc.open_path',$org);               
        }
        $newsql = $activesql = $cancelledsql = $completedsql = $holdsql = '';
        if($classrooms){
            $usercostcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
            $sql = "SELECT count(lc.id) FROM {local_classroom} lc WHERE 1=1 AND lc.visible = 1 {$costcenterpathconcatsql}";

            $count = $DB->count_records_sql($sql);
        
            $newsql .= " AND status = 0 ";
            $activesql .= " AND status = 1 ";
            $cancelledsql .= " AND status = 3 ";
            $completedsql .= " AND status = 4 ";        
            $holdsql .= " AND status = 2 ";

            $userscompleted_count = "SELECT count(cu.id) from {local_classroom_users} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.completion_status=? {$usercostcenterpathconcatsql}  ";
            $usersenroled_count = "SELECT count(cu.id) from {local_classroom_users} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0  {$usercostcenterpathconcatsql}  ";
            $session_count = "SELECT count(cu.id) from {local_classroom_sessions} cu, {local_classroom} lc where lc.id = cu.classroomid {$costcenterpathconcatsql} ";

            $orgpath = get_orgpath($this->params);
            if(!empty($orgpath)){     
                $newsql .= " AND concat(lc.open_path,'/') like '{$orgpath}' ";
                $activesql .= " AND concat(lc.open_path,'/') like '{$orgpath}'  ";
                $cancelledsql .= " AND concat(lc.open_path,'/') like '{$orgpath}'  ";
                $completedsql .= " AND concat(lc.open_path,'/') like '{$orgpath}' ";
                $holdsql .= " AND concat(lc.open_path,'/') like '{$orgpath}' ";
                $session_count .= " AND concat(lc.open_path,'/') like '{$orgpath}' ";
                
                if(!is_siteadmin()){
                    $userscompleted_count .= " AND concat(u.open_path,'/') like '{$orgpath}' ";
                    $usersenroled_count .= " AND concat(u.open_path,'/') like '{$orgpath}' "; 
                }
            }
            $totalclassroomscount = $DB->count_records_sql($sql);        
            $newclassroomscount = $DB->count_records_sql($sql . $newsql);        
            $activeclassroomscount = $DB->count_records_sql($sql . $activesql);
            $cancelledclassroomscount = $DB->count_records_sql($sql . $cancelledsql);
            $completedclassroomscount = $DB->count_records_sql($sql . $completedsql);        
            $holdclassroomscount = $DB->count_records_sql($sql . $holdsql);

            $data['totalclassrooms'] = $totalclassroomscount;
            $data['newclassroomcount'] = $newclassroomscount;
            $data['totalactive'] = $activeclassroomscount;
            $data['totalhold'] = $holdclassroomscount ;
            $data['totalcancelled'] = $cancelledclassroomscount ;
            $data['totalcompleted'] = $completedclassroomscount ;
            $completedcount = $DB->count_records_sql($userscompleted_count, array(1));
            $enrolledcount = $DB->count_records_sql($usersenroled_count, array());
            $sessioncount = $DB->count_records_sql($session_count , array());
            
            if(!empty($data)){
               
                $data['noofenrollments'] = $enrolledcount;
                $data['noofcompletions'] = $completedcount;
                $data['totalsessions'] = $sessioncount;
                $data['progress'] = ($data['noofcompletions'] > 0 && $data['noofenrollments'] > 0) ? round(($data['noofcompletions']/$data['noofenrollments'])*100) : 0 ;
    
                $data['percentofcompletions'] =$data['progress'].'%';
                return array((object)$data);
            }       
           
        }
       
        return $data;
     
    }
}
