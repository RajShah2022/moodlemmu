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

/** LearnerScript
 * A Moodle block for creating customizable reports
 * @package blocks
 * @subpackage learnerscript
 * @author: Anilkumar Cheguri
 * @date: 2019
 */

use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

global $DB, $CFG;

class report_feedbackcourses extends reportbase implements report
{
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties)
    {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns', 'filters', 'permissions');
        $this->columns = ['coursefield' => ['coursefield'], 'userfield' => ['userfield'], 'feedback_coursescolumns' => ['employeename', 'employeeid', 'managername', 'employeestatus', 'coursename', 'enroldate', 'completiondate', 'completionstatus']];
        $this->filters = array('feedback_courses');
        $this->defaultcolumn = 'u.id';
        // $this->userid = isset($report->userid) ? $report->userid : 0;
        // $this->reportid = isset($report->reportid) ? $report->reportid : 0;
        // $this->scheduleflag = isset($report->scheduling) ? true : false;
    }
    function init()
    {
        parent::init();
    }
    function count()
    {
        //$this->sql = "SELECT COUNT(ra.id)";
    }
    function select()
    {

        // $this->sql = "SELECT ra.id as roleassignmentid, u.id as userid, CONCAT(u.firstname, ' ',u.lastname) AS employeename, u.*,
        //                  u.open_employeeid as employeeid,  IF(u.suspended = 1, 'In-active','Active') as employeestatus,  u.open_supervisorid,
        //                  c.fullname as coursename ,c.id as courseid,FROM_UNIXTIME(ra.timemodified, '%d-%m-%Y') as enroldate,
        //                  FROM_UNIXTIME(cc.timecompleted, '%d-%m-%Y') AS completiondate, cc.timecompleted AS completionstatus, 
        //                  c.*, cat.name AS coursecategory, 
        //                  fc.timemodified AS submissiondate, f.id AS feedbackid, c.id as courseid,
        //                  c.shortname, c.open_categoryid, c.visible, c.open_path as course_open_path  ";
        parent::select();
    }
    function from()
    {
        //$this->sql .= " FROM {feedback} f ";
    }
    function joins()
    {
        // $this->sql .=  " JOIN {course} c ON c.id = f.course 
        //                 JOIN {course_categories} cat ON cat.id = c.category                        
        //                 JOIN {context} AS cxt ON  cxt.contextlevel = 50 AND cxt.instanceid=c.id
        //                 JOIN {role_assignments} as ra ON cxt.id=ra.contextid 
        //                 JOIN {user} u ON u.id = ra.userid 
        //                 JOIN {role} as r ON r.id = ra.roleid AND r.shortname  IN ('employee','student') AND u.id = ra.userid
        //                 LEFT JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid
        //                 LEFT JOIN {feedback_completed} AS fc ON fc.userid = u.id AND fc.feedback = f.id";
        // parent::joins();
    }
    function where()
    {
        // global  $CFG,$USER;
        // $this->sql .= "  WHERE 1=1 ";

        // $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'c.open_path', null, 'lowerandsamepath');
        // require_once($CFG->dirroot . "/blocks/learnerscript/lib.php");
        // if (is_siteadmin()) {
        //     $this->sql .= "";
        // } else if ($this->scheduleflag && $this->reportid!=0 && $this->userid != 0 ) {             
        //     $usercostcenterpathconcatsql = scheduled_report( $this->reportid,$this->scheduleflag,$this->userid,'c.open_path','u.open_path');
        //      $this->sql .= $usercostcenterpathconcatsql;     
        // }else{
        //     $usercostcenterpathconcatsql = get_user_costcenterpath($USER->open_path);
        //     $costcenterpathconcatsql  = $costcenterpathconcatsql  . $usercostcenterpathconcatsql  ; 
        //     $this->sql .= $costcenterpathconcatsql;  
        // }

        // parent::where();
    }
    function search()
    {
        // if (isset($this->search) && $this->search) {
        //     $fields = array('c.name', "CONCAT(u.firstname,' ',u.lastname)", 'u.email', 'u.open_employeeid');
        //     $fields = implode(" LIKE '%$this->search%' OR ", $fields);
        //     $fields .= " LIKE '%$this->search%' ";
        //     $this->sql .= " AND ($fields) ";
        // }
    }
    function filters()
    {
        // if ($this->params['filter_organization'] > 0) {
        //     $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
        //     $this->sql .= " AND concat(c.open_path,'/') like :orgpath ";
        //     $this->params['orgpath'] = $orgpath.'/%';
        // }
        // if ($this->params['filter_departments']  > 0) {
        //     $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
        //     $this->sql .= " AND concat(c.open_path,'/') like :l2dept ";
        //     $this->params['l2dept'] = $l2dept.'/%';
        // }
        // if ($this->params['filter_subdepartments'] > 0) {
        //     $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
        //     $this->sql .= " AND concat(c.open_path,'/') like :l3dept ";
        //     $this->params['l3dept'] = $l3dept.'/%';
        // }

        // if ($this->params['filter_level4department'] > 0) {
        //     $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
        //     $this->sql .= " AND concat(c.open_path,'/') like :l4dept ";
        //     $this->params['l4dept'] = $l4dept.'/%';
        // }

        // if (!empty($this->params['filter_feedbacks'])) {
        //     $this->sql .= " AND f.id = :feedbackid ";
        //     $this->params['feedbackid'] = $this->params['filter_feedbacks'];
        // }

        // if (!empty($this->params['filter_user'])) {
        //     $this->sql .= " AND u.id = :userid ";
        //     $this->params['userid'] = $this->params['filter_user'];
        // }

        // if ($this->ls_startdate > 0 && $this->ls_enddate) {
        //     $this->sql  .= " AND fc.startdate BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        // }
    }

     /**
     * [get_all_elements description]
     * @return [type] [description]
     */
    public function get_all_elements($sqlorder = '', $conditionfinalelements = array()) {
        global $DB, $USER;
        
        $systemcontext = context_system::instance();
        $concatsql = '';
        $params = array();

        if (isset($this->search) && $this->search) {
            $fields = array('CONCAT(u.firstname, " ", u.lastname)');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $concatsql .= " AND ($fields) ";
        }

        
        // $userid = $this->params['filter_users'];

        if (!empty($this->params['filter_courses'])) {
            $courseid = $this->params['filter_courses'];

        
            // $countsql = "SELECT COUNT(u.id) ";

            $selectsql = "SELECT u.id as userid, CONCAT(u.firstname, ' ',u.lastname) AS employeename, u.*,
                         u.open_employeeid as employeeid,  IF(u.suspended = 1, 'In-active','Active') as employeestatus,  u.open_supervisorid,
                         c.fullname as coursename ,c.id as courseid,c.id as courseid,
                         c.shortname, c.open_categoryid, c.visible, c.open_path as course_open_path  ";

            $sql =  " FROM {feedback} f
                    JOIN {course} c ON c.id = f.course
                    JOIN {enrol} en ON en.courseid = f.course
                    JOIN {user_enrolments} ue ON ue.enrolid = en.id AND ue.status = 0
                    JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                    JOIN {role_assignments} as ra ON ra.userid = u.id
                    JOIN {context} AS cxt ON cxt.id=ra.contextid AND cxt.contextlevel = 50 AND cxt.instanceid=c.id
                    JOIN {role} as r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')                   
                    WHERE f.course = :courseid ";

            $params['courseid'] = $courseid;
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
                $sql .= " AND 1=1 ";
            }

            // if($this->ls_startdate > 0 && $this->ls_enddate) {
            //     $sql .= " AND AND f.timeopen BETWEEN $this->ls_startdate AND $this->ls_enddate ";
            // }
              $sql .= " $concatsql group by u.id "; 

            
            try {

                $totalrecords = $DB->get_records_sql($selectsql . $sql, $params);
                $feedback_userscount = count($totalrecords);
            } catch (dml_exception $e) {
                $feedback_userscount = 0;
            }

            try {
                
                $feedback_users = $DB->get_records_sql($selectsql.$sql , $params, $this->start, $this->length);
               
                
            } catch (dml_exception $e) {
                
                $feedback_users = array();
            }
        }

        return array($feedback_users, $feedback_userscount);
    }

   
    /**
     * [get_rows description]
     *
     **/
    public function get_rows($feedback_users = array())
    {
        global $DB, $USER;
        $systemcontext = context_system::instance();
        $courseid = $this->params['filter_courses'];
     
        $data = array();
           
        if ($feedback_users[0]) {
            $organisations = $DB->get_records_menu('local_costcenter', ['parentid' => 0], '', 'id,fullname');
            foreach ($feedback_users[0] as $feedback_user) {
                  $rowdata = $feedback_user;
                  //print_R($rowdata);exit;
                list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $feedback_user->open_path);

                if (isset($organisations[$org])) {
                    $feedback_user->courseorg = $organisations[$org];
                } else {
                    $feedback_user->courseorg = $DB->get_field('local_costcenter', 'fullname', ['id' => $org]);
                }
                $rowdata = $feedback_user;
                if (!empty($feedback_user->completionstatus)) {
                    $rowdata->completionstatus = 'Completed';
                } else {
                    $rowdata->completionstatus = 'Not Completed';
                }

                $rowdata->employeeid = !empty($feedback_user->employeeid) ? $feedback_user->employeeid : '--';


                if (!empty($feedback_user->open_supervisorid)) {
                    $manager = $DB->get_record('user', array('id' => $feedback_user->open_supervisorid), 'id,firstname,lastname');

                    $rowdata->managername = $manager->firstname . ' ' . $manager->lastname;
                } else {
                    $rowdata->managername = '--';
                }


                //$feedbackid = $feedback_user->feedbackid;
                 $sql = "SELECT le.id
                        FROM {feedback} le
                        WHERE le.course = :courseid ";

                if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
                    $sql .= " AND 1=1 ";
                }               

                // if($this->ls_startdate > 0 && $this->ls_enddate) {
                //     $sql .= " AND le.timeopen BETWEEN $this->ls_startdate AND $this->ls_enddate ";
                // }else{
                //     $from = date('Y-m', strtotime('-2 month'));
                //     $fromdate = $from."-01";
                //     $fromtimestamp = strtotime($fromdate);
                //     $now = time();
                //     $sql .= " AND le.timeopen BETWEEN $fromtimestamp AND $now ";
                // }

                $sql .= " GROUP BY le.id ORDER BY le.id ASC ";
              
                $cfeedbacks = $DB->get_fieldset_sql($sql, array('courseid'=>$courseid));
                foreach($cfeedbacks as $feedbackid){

                $sql = "SELECT fv.id, fv.value, fc.userid, fi.id as itemid, fi.name as itemname
                            FROM {feedback_value} fv 
                            JOIN {feedback_item} fi ON fi.id = fv.item
                            JOIN {feedback_completed} fc ON fc.id = fv.completed
                            WHERE fi.feedback = :evalid AND fc.userid = :userid  AND fi.typ != :type ORDER BY fi.position ASC";


                $eval_response = $DB->get_records_sql($sql, array(
                    'evalid' => $feedbackid,
                    'userid' => $feedback_user->userid, 'type' => 'label'
                ));

                if ($eval_response) {
                    $evaldata = $DB->get_record('feedback', array('id' => $feedbackid));
                    $evaluationstructure = new \mod_feedback_structure($evaldata, '');
                    $eval_resp = new \mod_feedback_responses_table($evaluationstructure);

                    foreach ($eval_response as $response) {
                        $valkey = 'val' . $response->itemid;
                        $valobj = new stdClass();
                        $valobj->{$valkey} = $response->value;

                        $formattedval = $eval_resp->other_cols($valkey, $valobj);

                        //$key = 'itemid_' . $response->itemname;
                        $key = 'itemid_' . $response->itemid;
                        //$rowdata->{$key} = $response->value;

                        if ($formattedval === NULL) {
                            $rowdata->{$key} = $response->value;
                        } else {
                            $rowdata->{$key} = $formattedval;
                        }
                    }
                }
                       
                $squery = "SELECT timemodified 
                                FROM {feedback_completed} 
                                WHERE feedback= $feedbackid AND userid = {$feedback_user->userid} ";

                $submissiondate = $DB->get_field_sql($squery);

                $submitteddate = 'submitteddate_'.$feedbackid;
                if($submissiondate){
                    $rowdata->{$submitteddate} = date('d-m-Y', $submissiondate); 
                }else{
                    $rowdata->{$submitteddate} = 'NA';
                }
               $sql = "SELECT ROUND(SUM(ev.value))
                            FROM {feedback_value} ev
                            JOIN {feedback_completed} ec ON ev.completed = ec.id
                            JOIN {feedback_item} ei ON ei.id = ev.item
                            WHERE ei.feedback = $feedbackid AND ec.userid = $feedback_user->userid
                            AND ei.typ = 'multichoicerated' ";

                $userfeedback = $DB->get_field_sql($sql);

                $total = 'cfbtotal_'.$feedbackid;
                $rowdata->{$total} = $userfeedback;
            }

                $data[] = $rowdata;
            }
          
        }

        return $data;
    }
}
