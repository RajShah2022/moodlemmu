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

class report_departmentwiseclassrooms extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('departmentwiseclassrooms' => array('organization','completed','scheduled'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization','departments', 'subdepartments', 'level4department','startendtime');
        $this->sqlorder['column'] = 'organization';
        $this->sqlorder['dir'] = 'desc';
        $this->orderable = array();
        $this->defaultcolumn = 'c.id';
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT( distinct c.id)";
    }
    function select() { 
        $timesql = $this->timesql();
        $this->sql  = " select concat(c.fullname, '/', c.shortname) as organization,
            (SELECT count(id)
                FROM {local_classroom} lc
                WHERE  lc.status = 4 AND lc.costcenter = c.id $timesql
                ) as completed,
                (SELECT count(id) 
                FROM {local_classroom} lc 
                WHERE lc.status != 3  AND lc.costcenter = c.id $timesql
                ) as scheduled
        ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_costcenter} c ";
    }

    function joins() {
        parent::joins();
    }

    function where(){
        global $CFG;
        $this->sql .= " WHERE lc.status <> 0 "; //Not considering the new classrooms.
        $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.path'); 

        require_once($CFG->dirroot . "/blocks/learnerscript/lib.php");
        if (is_siteadmin()) {
            $this->sql .= "";
        } else if ($this->scheduleflag && $this->reportid!=0 && $this->userid != 0 ) {             
            $usercostcenterpathconcatsql = scheduled_report( $this->reportid,$this->scheduleflag,$this->userid,'c.path' ,'');
            $this->sql .= $usercostcenterpathconcatsql;     
        }else{
            $this->sql .= $costcenterpathconcatsql;
        }
        parent::where();
    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(c.fullname,' ',c.shortname)");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters(){
        if (!empty($this->params['filter_organization'])) {
            $orgids = $this->params['filter_organization'];
            $this->sql .= " AND c.id = :orgid ";
            $this->params['orgid'] = $orgids;
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(c.path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        } 
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(c.path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(c.path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }
    }
    /**
     * [get_rows description]
     * @param  array  $trainermandays [description]
     * @return [type]        [description]
     **/
    public function get_rows($data = array()) {
        return $data;
    }

    public function timesql() {
        $sql = '';
        if(!empty($this->params['filter_starttime']) AND $this->params['filter_starttime']['enabled'] == 1){
            $filter_starttime = $this->params['filter_starttime'];
            $start_year=$filter_starttime['year'];
            $start_month=$filter_starttime['month'];
            $start_day=$filter_starttime['day'];
            $start_hour=$filter_starttime['hour'];
            $start_minute=$filter_starttime['minute'];
            $start_second=0;
            $filter_starttime_con=mktime($start_hour, $start_minute, 0, $start_month, $start_day, $start_year);
            $sql.=" AND lc.startdate >= '$filter_starttime_con' ";
        }
        if(!empty($this->params['filter_endtime']) AND $this->params['filter_endtime']['enabled'] == 1){
            $filter_endtime = $this->params['filter_endtime'];
            $end_year=$filter_endtime['year'];
            $end_month=$filter_endtime['month'];
            $end_day=$filter_endtime['day'];
            $end_hour=$filter_endtime['hour'];
            $end_minute=$filter_endtime['minute'];
            $end_second=0;
            $filter_endtime_con=mktime($end_hour, $end_minute, 0, $end_month, $end_day, $end_year);
            $sql.=" AND lc.enddate <= '$filter_endtime_con' ";
        }
        return $sql;
    }
}
