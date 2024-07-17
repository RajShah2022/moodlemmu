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

class report_myonlinetests extends reportbase implements report
{

    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties)
    {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns', 'permissions','filters');
        $this->columns =['onlinetestfield'=>['onlinetestfield'],'myonlinetests' => ['onlinetestname','achievedgrade','completionstatus','completiondate']];
        $this->filters = ['myonlinetestscolumns'];
        $this->orderable = array('onlinetestname');
        $this->defaultcolumn = 'ue.id';
    }
    function init()
    {
        parent::init();
    }
    function count()
    {
        $this->sql = "SELECT count(ue.id)";
    }
    function select()
    {
        $this->sql = "SELECT ue.id, ue.userid as userid, c.id as courseid, c.open_categoryid,c.fullname as onlinetestname ,
                        cmc.timemodified as completiondate, cmc.completionstate as completionstatus,c.open_path ";

        parent::select();
    }
    function from()
    {
        $this->sql .= " FROM {user_enrolments} as ue";
    }
    function joins()   
    {
        $this->sql .= " JOIN {enrol} as e ON e.id = ue.enrolid AND e.enrol IN ('auto','self','manual')
                JOIN {role_assignments} as ra ON ra.userid = ue.userid
                JOIN {context} AS cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                                        AND cxt.instanceid = e.courseid
                JOIN {role} as r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')
                JOIN {course} as c ON c.id = e.courseid 
                JOIN {course_modules} as cm ON cm.course = c.id 
                LEFT JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id  AND cmc.userid = ue.userid ";
        parent::joins();
    }
    function where()
    {
        global $USER;
        $this->sql .=  " WHERE ue.userid = $USER->id AND c.visible = 1 AND c.open_coursetype = :type AND open_module = :module ";
        $this->params['type'] = 1; 
        $this->params['module'] = 'online_exams';
        parent::where();
    }
    function search()
    {
        if (isset($this->search) && $this->search) {
            $fields = array("c.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters()
    {
        if (!empty($this->params['filter_myonlinetestscolumns'])) {
            $this->sql .= " AND c.id = :coursename";
            $this->params['coursename'] = $this->params['filter_myonlinetestscolumns'];
        }

        if($this->ls_startdate > 0 && $this->ls_enddate > 0){
            $this->sql .= " AND cmc.timemodified  > :report_startdate ";
            $this->params['report_startdate'] = $this->ls_startdate;

            $this->sql .= " AND cmc.timemodified  < :report_enddate ";
            $this->params['report_enddate'] = $this->ls_enddate;
        }
    }
    function get_rows($mycourses)
    {
        return $mycourses;
    }
}
