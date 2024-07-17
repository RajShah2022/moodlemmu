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
 * @package Bizlms 
 * @subpackage local_custom_matrix
 */

namespace local_custom_matrix;
use stdClass;
require_once($CFG->dirroot . '/local/custom_matrix/lib.php');
class lib{
    const TABLE_CUSTOM_MATRIX = 'local_custom_category';
    const TABLE_PERFORMANCE_TEMPLATE = 'local_performance_template';
    const TABLE_PERFORMANCE_MATRIX = 'local_performance_matrix';
    const TABLE_PERFORMANCE_LOGS = 'local_performance_logs';
    const TABLE_PERFORMANCE_OVERALL = 'local_performance_overall';

    function __construct()
    {
        global $DB, $CFG, $OUTPUT,  $USER, $PAGE;
        $this->db = $DB;
        $this->user = $USER;
        $this->querylib = new \local_custom_matrix\querylib();
    } 
    /**
     * @param object $formdata
     * @return id
     * @throws dml_exception
    */  
    public function custom_matrix_operations($formdata){
       
        $data = new \stdClass();
        $costcenter = explode('/', $this->user->open_path);
       
        $data->costcenterid = $formdata->open_costcenterid ? $formdata->open_costcenterid: $costcenter[1];
        $data->fullname = $formdata->name;
        $data->shortname = $formdata->shortname;
      
        $data->parentid = $formdata->parentid ? $formdata->parentid:0;
        $data->visible = 1;
       if ($formdata->parentid == 0) {
            $data->depth = $formdata->depth = 1;
            $data->type = 0;        
        } else {          
            $parent = $this->querylib->matrix_record(array('id' => $formdata->parentid));
            $data->depth = $parent->depth + 1;
            $data->type = 0;
        }
        $statesid = new stdClass();
        if($formdata->id){
            $data->id           = $formdata->id;
            $data->timemodified = time();
            $data->usermodified = $this->user->id;           
            $parentpath = $this->querylib->get_matrixfield('path', array('id'=>$formdata->parentid));
            $path = $parentpath.'/'.$formdata->id;
            $data->path = $path;
            $statesid->id = $this->db->update_record(self::TABLE_CUSTOM_MATRIX, $data,$returnid = true);
        }else{
            $data->timecreated  = time();
            $data->usercreated  = $this->user->id;

            $statesid->id = $this->db->insert_record(self::TABLE_CUSTOM_MATRIX, $data,$returnid = true);

            if($statesid->id) {              
                $parentpath = $this->querylib->get_matrixfield('path', array('id'=>$formdata->parentid));
                $path = $parentpath.'/'.$statesid->id;
                $datarecord = new \stdClass();
                $datarecord->id = $statesid->id;
                $datarecord->path = $path;
                $this->db->update_record(self::TABLE_CUSTOM_MATRIX,  $datarecord);
            }
        }
        return $statesid->id;
    }
    /**
     * @param object $formdata
     * @return id
     * @throws dml_exception
    */ 
    public function template_operations($formdata){
        $data = $formdata; 
        $costcenter = explode('/', $this->user->open_path);       
        $data->costcenterid = $formdata->open_costcenterid ? $formdata->open_costcenterid: $costcenter[1];       
        $templateid = new stdClass();
        if($formdata->id){
            $data->id           = $formdata->id;
            $data->timemodified = time();
            $data->usermodified = $this->user->id; 
            if($data->active == 1){
                $this->check_organisation_templates($data->costcenterid,$data->id);
            }            
            $templateid->id = $this->db->update_record(self::TABLE_PERFORMANCE_TEMPLATE, $data,$returnid = true);
        }else{
            $data->timecreated  = time();
            $data->usercreated  = $this->user->id;
            if($data->active == 1){
                $this->check_organisation_templates($data->costcenterid);
            }

            $templateid->id = $this->db->insert_record(self::TABLE_PERFORMANCE_TEMPLATE, $data,$returnid = true);
        }
        return $templateid->id;
    }
    public function check_organisation_templates($orgid,$tempid=0){       
        $update = "update mdl_local_performance_template set active = 0 where costcenterid = " . $orgid; 
        if($tempid != 0){
            $update .= " AND id !=".$tempid;
        }
        $this->db->execute($update);      

    }
    /**
     * @param object $matrix
     * @return object
     * @throws dml_exception
    */
    public function insert_matrix(object $matrix): object {      
        $month = date('m', time());
        $year = date("Y", time());

        $data = clone $matrix;
        $data->timecreated = time();
        $data->usercreated = $this->user->id;
        $data->month = $month;
        $data->year = $year;

        $newid = $this->db->insert_record(self::TABLE_PERFORMANCE_MATRIX, $data);
        $data->id = $newid;
        $matrix->id = $newid;
        return $matrix;
    }
    /**
     * @param object $matrix
     * @return object
     * @throws dml_exception
     */
    public function upadte_matrix(object $matrix): object {      
        $month = date('m', time());
        $year = date("Y", time());

        $data = clone $matrix;
        $data->timemodified = time();
        $data->usermodified = $this->user->id;
        $data->month = $month;
        $data->year = $year;
        $newid = $this->db->update_record(self::TABLE_PERFORMANCE_MATRIX, $data);
        $data->id = $newid;
        $matrix->id = $newid;
        return $matrix;
    }
    /**
     *
     * @param object $matrix
     * @return object
     * @throws dml_exception
     */
    public function insert_update_log_matrix(object $matrix){       
        $periodtype = get_config('local_custom_matrix','performance_period_type');
        $current_month = date('M',time());
        $period = get_current_period($periodtype,$current_month);

        $data = new stdClass();
        $data->userid = $matrix->userid;
        $data->performancetype = $matrix->performancetype;
        $data->performancecatid = $matrix->performancecatid;        
        $data->maxpoints = ($matrix->maxpoints== '')?0:$matrix->maxpoints;
        $data->pointsachieved = ($matrix->totalpoints== '')?0:$matrix->totalpoints;
        $data->weightage = ($matrix->weightage== '')?0:$matrix->weightage; 
             
        $data->parentid = $matrix->parentid;
        $data->type = $matrix->type;        
        $data->role = $matrix->role; 
        
        if($matrix->logid != 0 && $matrix->logid != 'undefined'){            
            $data->id = $matrix->logid;
            $data->timemodified = $matrix->timemodified;
            $data->usermodified = $matrix->usermodified;
            $newid = $this->db->update_record(self::TABLE_PERFORMANCE_LOGS, $data);
        }else{

            $data->month = $matrix->month;
            $data->year = $matrix->year;
            $data->period = $matrix->period;
            $data->templateid = $matrix->templateid;
            $data->usercreated = $matrix->usercreated;
            $data->timecreated = $matrix->timecreated;             
            $newid = $this->db->insert_record(self::TABLE_PERFORMANCE_LOGS, $data);
        }

        return $newid;
    }
    /**
     *
     * @param object $matrix
     * @return object
     * @throws dml_exception
     */
    public function insert_overall_matrix(object $matrix): object {  
        $month = date('F', time());
        $year = date("Y", time());
        $nextyear = date('Y', strtotime('+1 year'));
        $periodtype = get_config('local_custom_matrix','performance_period_type');
        $current_month = date('M',time());
        $period = get_current_period($periodtype,$current_month);
        $data = clone $matrix;
        $data->timecreated = time();
        $data->usercreated = $this->user->id;
        $data->month = $month;
        $data->year = $year;
        $data->financialyear = $year.'-'.$nextyear;
        $data->period = $period;         
        $logid = $this->insert_update_log_matrix($data);
        $newid = $this->db->insert_record(self::TABLE_PERFORMANCE_OVERALL, $data);
        $data->id = $newid;
        $matrix->id = $newid;
        $matrix->id = $logid;

        return $matrix;
    }
    /**
     *
     * @param object $matrix
     * @return object
     * @throws dml_exception
     */
    public function upadte_overall_matrix(object $matrix): object {    
        $month = date('F', time());
        $year = date("Y", time());
        $nextyear = date('Y', strtotime('+1 year'));
        $data = clone $matrix;
        $data->timemodified = time();
        $data->usermodified = $this->user->id;       
        $data->financialyear = $year.'-'.$nextyear;
        $logid = $this->insert_update_log_matrix($data);               
        $newid = $this->db->update_record(self::TABLE_PERFORMANCE_OVERALL, $data);
        $data->id = $newid;
        $matrix->id = $newid;
        return $matrix;
    }
    

}
