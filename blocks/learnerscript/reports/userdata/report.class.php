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

class report_userdata extends reportbase implements report {

    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns', 'filters', 'permissions');
        $this->columns = ['userfield'=>['userfield','fullname','username','firstname','lastname','email']];
        $this->filters = ['organization','departments', 'subdepartments', 'level4department','user'];
        $this->defaultcolumn = 'u.id';
        $this->orderable = array('');
        $this->userid = isset($report->userid) ? $report->userid : 0;
        $this->reportid = isset($report->reportid) ? $report->reportid : 0;
        $this->scheduleflag = isset($report->scheduling) ? true : false;
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT count(u.id)";
    }
    function select() {
        $this->sql = "SELECT u.id as userid,CONCAT(u.firstname, ' ', u.lastname) AS fullname,u.* ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {user} as u ";
    }
    function joins() {
        $this->sql .= " JOIN {local_costcenter} lc ON concat('/',u.open_path,'/') LIKE concat('/',lc.path,'/') ";
        parent::joins();
    }
    function where(){
        global $USER,$CFG ;
        $this->sql .=  " WHERE u.confirmed = 1  ";
        
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        require_once($CFG->dirroot . "/blocks/learnerscript/lib.php");
        if (is_siteadmin()) {
            $this->sql .= "";
        } else if ($this->scheduleflag && $this->reportid!=0 && $this->userid != 0) {             
            $usercostcenterpathconcatsql = scheduled_report( $this->reportid,$this->scheduleflag,$this->userid,'','u.open_path');
            $this->sql .= $usercostcenterpathconcatsql;     
        }else{
            $usercostcenterpathconcatsql = get_user_costcenterpath($USER->open_path);
            $costcenterpathconcatsql  = $costcenterpathconcatsql  . $usercostcenterpathconcatsql  ; 
            $this->sql .= $costcenterpathconcatsql;    
        } 
        parent::where();
    }
    function search(){
      if (isset($this->search) && $this->search) {
        $fields = array("CONCAT(u.firstname, ' ', u.lastname)", "u.email");
        $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
        $fields .= " LIKE '%" . $this->search . "%' ";
        $this->sql .= " AND ($fields) ";
      }
    }  
    function filters(){ 
        if (!empty($this->params['filter_user'])) {
            $userid = $this->params['filter_user'];
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $userid;
        }
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }   
     
   
    }    

    function get_rows($userdata){
      return $userdata;
    }
 }
