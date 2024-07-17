<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2017
 */

use block_learnerscript\local\reportbase;

class report_orgclassrooms extends reportbase {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
      
        $this->parent = true;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->columns = array('orgclassrooms' => array(get_string('open_costcenterid', 'local_costcenter'),'totalclassrooms','newcount','activecount','holdcount','completedcount','cancelledcount','usercount'));        
        $this->filters = ['organization'];
        $this->defaultcolumn = 'lc.id';
        $this->userid = isset($report->userid) ? $report->userid : 0;
        $this->reportid = isset($report->reportid) ? $report->reportid : 0;
        $this->scheduleflag = isset($report->scheduling) ? true : false;

    }
    function count() {
        $this->sql = " SELECT COUNT(lc.id) ";
    }

    function select() {
        $this->sql = " SELECT lc.fullname, lc.id ";
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {local_costcenter} lc ";
    }

    function joins() {      
        parent::joins();
    }

    function where() {
        global $CFG;
        $this->sql .= " WHERE lc.depth = 1 "; 

        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.path', null, 'lowerandsamepath');

        require_once($CFG->dirroot . "/blocks/learnerscript/lib.php");
        if (is_siteadmin()) {
            $this->sql .= "";
        } else if ($this->scheduleflag && $this->reportid!=0 && $this->userid != 0 ) {             
            $usercostcenterpathconcatsql = scheduled_report( $this->reportid,$this->scheduleflag,$this->userid,'lc.path','');
            $this->sql .= $usercostcenterpathconcatsql;     
        }else{
            $this->sql .= $costcenterpathconcatsql;    
        } 
        parent::where();
    }


    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("lc.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        if (!empty($this->params['filter_organization'])  && $this->params['filter_organization'] > 0) {
            $organization = $this->params['filter_organization'];
            $filter_organization[] = " lc.id = $organization";
            $this->sql .= " AND (".implode(' OR ', $filter_organization)." ) ";
        }

    }

    public function get_rows($data) {      
        return $data;
    }

}
