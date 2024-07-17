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
 * @subpackage local_custom_matrix
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/local/custom_matrix/edit_matrix_form.php');
class local_custom_matrix_external extends external_api {

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_custom_matrix_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
            )
        );
    }

    public static function submit_custom_matrix_form($contextid, $jsonformdata){
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/local/custom_matrix/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_custom_matrix_form_parameters(), ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);


        $context =(new \local_custom_matrix\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $data = array();
        parse_str($params['jsonformdata'], $data);
        $warnings = array();

        $mform = new local_custom_matrix\form\custom_matrix_form(null, array(), 'post', '', null, true, $data);

        $repositoryinsert  = new local_custom_matrix\lib();
        $valdata = $mform->get_data();

         if($valdata){
            $repositoryinsert->custom_matrix_operations($valdata);
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
    }


    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_custom_matrix_form_returns() {
        return new external_value(PARAM_INT, 'repository id');
    }


    public static function managecustom_matrix_parameters() {
        return new external_function_parameters([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum number of results to return', VALUE_DEFAULT, 0),
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function managecustom_matrix(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $USER,$PAGE;
        require_once($CFG->dirroot . '/local/custom_matrix/lib.php');
        require_login();
        $PAGE->set_url('/local/custom_matrix/matrix.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::managecustom_matrix_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);
        $stable = new \stdClass();
        $stable->thead = true;
        $stable->start = $offset;
        $stable->length = $limit;
        $parentcatid = $decodedata->parentcatid;
        $stable->parentcatid = $parentcatid;
        $result_custom_matrix = custom_matrix_details($stable,$filtervalues);
        $totalcount = $result_custom_matrix['count'];
        $data=$result_custom_matrix['data'];
        return [
            'is_admin' => is_siteadmin(),
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'parentcatid' => $parentcatid,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  managecustom_matrix_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of custom_matrix in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'is_admin' => new external_value(PARAM_BOOL, 'Is user an admin flag'),
            'parentcatid' => new external_value(PARAM_INT, 'Is categoried parent matrix flag'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'visible' => new external_value(PARAM_INT, 'visible skill', VALUE_OPTIONAL),
                        'custom_matrix_id' => new external_value(PARAM_RAW, 'id in custom_matrix', VALUE_OPTIONAL),
                        'organisationname' => new external_value(PARAM_RAW, 'organisationname of custom_matrix', VALUE_OPTIONAL),
                        'custom_matrix_name' => new external_value(PARAM_RAW, 'custom_matrix', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_RAW, 'shortname of custom_matrix', VALUE_OPTIONAL),
                        'parent' => new external_value(PARAM_RAW, 'matrix name in custom_matrix', VALUE_OPTIONAL),
                        'type' => new external_value(PARAM_RAW, 'type of category', VALUE_OPTIONAL),
                        'matrixexist' => new external_value(PARAM_RAW, 'matrixexist in custom_matrix', VALUE_OPTIONAL),
                        'childs' => new external_value(PARAM_RAW, 'childs in custom_matrix'),
                        'childcount' => new external_value(PARAM_INT, 'childcount in custom_matrix'),
                    )
                )
            )
        ]);
    }

    public static function matrix_view_parameters() {
        return new external_function_parameters(
            array(
                'costcenterid' => new external_value(PARAM_INT, 'The costcenter id'), 
                'role' => new external_value(PARAM_RAW, 'The role'),              
                'templateid' => new external_value(PARAM_INT, 'The role'),              
            )
        );
    }

    public static function matrix_view($costcenterid,$role,$templateid){
        global $CFG;
        
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::matrix_view_parameters(), ['costcenterid' => $costcenterid,'role' => $role, 'templateid' => $templateid]);

        $context =(new \local_custom_matrix\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);

        require_once($CFG->dirroot . '/local/custom_matrix/lib.php');       
        $matrixes = custom_matrix_data(array('costcenter'=>$costcenterid,'role' => $role,'templateid' => $templateid));           
       return [
            'records' =>$matrixes['records'],
            'pm_records_count' =>$matrixes['pm_records_count'],
        ];        
    }

    public static function  matrix_view_returns() {
        return new external_single_structure([
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'id in custom_matrix', VALUE_OPTIONAL), 
                        'fullname' => new external_value(PARAM_RAW, 'custom_matrix', VALUE_OPTIONAL),
                        'parentid' => new external_value(PARAM_INT, 'parentid in custom_matrix', VALUE_OPTIONAL),
                        'pmid' => new external_value(PARAM_INT, 'performance id in performance_matrix', VALUE_OPTIONAL),
                        'maxscore' => new external_value(PARAM_INT, 'maxscore in performance_matrix', VALUE_OPTIONAL),
                        'weightage' => new external_value(PARAM_INT, 'weightage in performance_matrix', VALUE_OPTIONAL),
                        'role' => new external_value(PARAM_RAW, 'role in performance_matrix', VALUE_OPTIONAL),
                        'type' => new external_value(PARAM_INT, 'performance id in performance_matrix', VALUE_OPTIONAL),
                        'path' => new external_value(PARAM_RAW, 'path in performance_matrix', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }

    public static function users_matrix_save_parameters() {
        return new external_function_parameters(
            array(
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
            )
        );
    }

    public static function users_matrix_save($jsonformdata){
        global $PAGE, $CFG;
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::users_matrix_save_parameters(), ['jsonformdata' => $jsonformdata]);

        $context =(new \local_custom_matrix\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $savedata  = new local_custom_matrix\lib();        
        $matrixarr = json_decode($params['jsonformdata']);  
        $resp = '';
       
        foreach($matrixarr as $matrix){  
            if(isset($matrix->totalpoints)){ 
                if($matrix->id != 0){                                      
                    $result = $savedata->upadte_overall_matrix($matrix);
                    $resp = get_string('update_msg', 'local_custom_matrix');
                }else{                
                    $result = $savedata->insert_overall_matrix($matrix);
                    $resp = get_string('save_msg', 'local_custom_matrix');
                } 
            }  
        }
        
        if($result){
           return [
            'message' =>$resp
            ];
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }

        
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function users_matrix_save_returns() {
          return new external_single_structure([
            'message' => new external_value(PARAM_RAW, 'message', VALUE_OPTIONAL)
        ]);
    }

    public static function user_matrix_view_parameters() {
        return new external_function_parameters(
            array(
                'orgid' => new external_value(PARAM_INT, 'The costcenter id'), 
                'role' => new external_value(PARAM_RAW, 'The role'),              
                'period' => new external_value(PARAM_RAW, 'The period'),              
                'year' => new external_value(PARAM_INT, 'The year'),              
                'userid' => new external_value(PARAM_INT, 'The userid'),              
                'month' => new external_value(PARAM_RAW, 'The month'),              
                'tempid' => new external_value(PARAM_INT, 'The template id'),              
            )
        );
    }

    public static function user_matrix_view($orgid,$role,$period,$year,$userid,$month,$tempid){
        global $CFG;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::user_matrix_view_parameters(), ['orgid' => $orgid,'role' => $role,'period' => $period,'year' => $year,'userid' => $userid,'month' => $month,'tempid' => $tempid]);

        $context =(new \local_custom_matrix\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        
        require_once($CFG->dirroot . '/local/custom_matrix/lib.php');       
        $matrixes = user_matrix_data(array('costcenter'=>$orgid,'role' => $role,'period' => $period,'year' => $year,'userid' => $userid,'month' => $month,'templateid' => $tempid));      
          
        return [
            'records' =>$matrixes
        ];
    }

    public static function  user_matrix_view_returns() {
        return new external_single_structure([
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'id in custom_matrix', VALUE_OPTIONAL), 
                        'fullname' => new external_value(PARAM_RAW, 'custom_matrix', VALUE_OPTIONAL),
                        'parentid' => new external_value(PARAM_INT, 'parentid in custom_matrix', VALUE_OPTIONAL),
                        'poid' => new external_value(PARAM_INT, 'performance overall id in performance_matrix', VALUE_OPTIONAL),
                        'maxscore' => new external_value(PARAM_INT, 'maxscore in performance_matrix', VALUE_OPTIONAL),
                        'weightage' => new external_value(PARAM_INT, 'weightage in performance_matrix', VALUE_OPTIONAL),
                        'role' => new external_value(PARAM_RAW, 'role in performance_matrix', VALUE_OPTIONAL),
                        'type' => new external_value(PARAM_INT, 'performance id in performance_matrix', VALUE_OPTIONAL),
                        'userscore' => new external_value(PARAM_INT, 'total score in performance_matrix_overall', VALUE_OPTIONAL),
                        'templateid' => new external_value(PARAM_INT, 'performance template id', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }

     public static function matrix_save_parameters() {
        return new external_function_parameters(
            array(
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
            )
        );
    }

    public static function matrix_save($jsonformdata){
        global $PAGE, $CFG; 
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::matrix_save_parameters(), ['jsonformdata' => $jsonformdata]);
        $context =(new \local_custom_matrix\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
       
        $savedata  = new local_custom_matrix\lib();
        $matrixarr = json_decode($params['jsonformdata']);  
        $resp = '';       
        foreach($matrixarr as $matrix){ 
            if($matrix->id != 0){                                      
                $result = $savedata->upadte_matrix($matrix);
                $resp = get_string('update_msg', 'local_custom_matrix');
            }else{
                $result = $savedata->insert_matrix($matrix);
                $resp = get_string('save_msg', 'local_custom_matrix');
            } 
        }        
        if($result){
           return [
            'message' =>$resp
            ];
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function matrix_save_returns() {
          return new external_single_structure([
            'message' => new external_value(PARAM_RAW, 'message', VALUE_OPTIONAL)
        ]);
    }


}
