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
 * @subpackage local_skillrepository
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/user/selector/lib.php');
class local_skillrepository_external extends external_api {

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_skill_repository_form_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
            )
        );
    }

    public static function submit_skill_repository_form_form($contextid, $jsonformdata){
        global $PAGE, $CFG;
        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_skill_repository_form_form_parameters(), ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);


        $context =(new \local_skillrepository\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $data = array();
        parse_str($params['jsonformdata'], $data);
        $warnings = array();
        $mform = new local_skillrepository\form\skill_repository_form(null, array(), 'post', '', null, true, $data);

        $repositoryinsert  = new local_skillrepository\event\insertrepository();
        $valdata = $mform->get_data();
        $valdata->description=$valdata->description['text'] ? $valdata->description['text'] : '';
        if($valdata){
            if($valdata->id>0){
                local_costcenter_get_costcenter_path($valdata);
                $repositoryinsert->skillrepository_opertaions('local_skill', 'update', $valdata,'','');
            } else {
                local_costcenter_get_costcenter_path($valdata);
                $repositoryinsert->skillrepository_opertaions('local_skill','insert', $valdata,'','');
            }
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
    public static function submit_skill_repository_form_form_returns() {
        return new external_value(PARAM_INT, 'repository id');
    }

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_skill_category_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the skill category'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    public static function submit_skill_category($contextid, $jsonformdata){
        global $PAGE, $CFG;
        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_skill_category_parameters(), ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);


        $context =(new \local_skillrepository\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);

        $data = array();

        parse_str($params['jsonformdata'], $data);
        $warnings = array();
        $mform = new local_skillrepository\form\skill_category_form(null, array(), 'post', '', null, true, $data);

        $repositoryinsert  = new local_skillrepository\event\insertcategory();

        $valdata = $mform->get_data();

        if($valdata){
            local_costcenter_get_costcenter_path($valdata);
            $repositoryinsert->create_skill_category($valdata);
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
    public static function submit_skill_category_returns() {
        return new external_value(PARAM_INT, 'category id');
    }

    public static function repository_selector_parameters() {
        $query = new external_value(
            PARAM_RAW,
            'Query string'
        );
        $organisation = new external_value(
            PARAM_INT,
            'Organisation id information',
            VALUE_DEFAULT,
            0
        );

        return new external_function_parameters(array(
            'query' => $query,
            'context' => self::get_context_parameters(),
            'organisation' => $organisation,

        ));
    }

    public static function repository_selector($query, $context, $organisation = 0) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::repository_selector_parameters(), array(
            'query' => $query,
            'context' => $context,
            'organisation' => $organisation,
        ));
        $query = $params['query'];
        $organisation = $params['organisation'];
        $context = self::get_context_from_params($params['context']);
        self::validate_context($context);
        $repos = array();

        $repositorysql = "SELECT id, name
            FROM {local_skill_categories}
            WHERE open_path LIKE  '/".$organisation."%'";
        if ($query) {
            $repositorysql .= " AND name LIKE '%$query%' ";
        }
        $repos = $DB->get_records_sql($repositorysql);
        return array('repos' => $repos);
    }

    public static function repository_selector_returns() {
        return new external_single_structure(array(
            'repos' => new external_multiple_structure(
                new external_single_structure(array(
                    'id' => new external_value(PARAM_INT, 'ID of the repository'),
                    'name' => new external_value(PARAM_RAW, 'repository name'),
                ))
            ),
        ));
    }

    //Levels related functions

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_level_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }


    public static function submit_level_form($contextid, $jsonformdata){
        global $PAGE, $CFG;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_level_form_parameters(), ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);

        $context =(new \local_skillrepository\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);

        $data = array();

        parse_str($params['jsonformdata'], $data);
        $warnings = array();
        $mform = new \local_skillrepository\form\levelsform(null, array(), 'post', '', null, true, $data);
        $querylib  = new \local_skillrepository\local\querylib();
        $valdata = $mform->get_data();

        if($valdata){
            local_costcenter_get_costcenter_path($valdata);
            $levelid = $querylib->insert_update_level($valdata);
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
        return $levelid;
    }


    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_level_form_returns() {
        return new external_value(PARAM_INT, 'level id');
    }

    public static function delete_skill_parameters(){
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'userid', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false)
            )
        );
    }
    public static function delete_skill($id,$contextid){
        global $DB;
        $return = $DB->delete_records('local_skill',  array('id' => $id));
        return $return;
    }
    public static function delete_skill_returns(){
        return new external_value(PARAM_BOOL, 'return');
    }


    //////For displaying on index page//////////
    public static function manageskillsview_parameters() {
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
    public static function manageskillsview(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        require_login();
        $PAGE->set_url('/local/skillrepository/index.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageskillsview_parameters(),
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
        $result_skill = skill_details($stable,$filtervalues);
        $totalcount = $result_skill['count'];
        $data=$result_skill['data'];
        $advance=$result_skill['advance'];
        return [
            'is_admin' => is_siteadmin(),
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'advance' => $advance,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  manageskillsview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'is_admin' => new external_value(PARAM_BOOL, 'Is user an admin flag'),
            'advance' => new external_value(PARAM_BOOL, 'Is user an admin flag'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'visible' => new external_value(PARAM_INT, 'visible skill', VALUE_OPTIONAL),
                        'skill_id' => new external_value(PARAM_RAW, 'id in skill', VALUE_OPTIONAL),
                        // 'levelsCount' => new external_value(PARAM_INT, 'skill level count'),
                        'organisationname' => new external_value(PARAM_RAW, 'organisationname of skill', VALUE_OPTIONAL),
                        'organisationid' => new external_value(PARAM_RAW, 'id in organisationid', VALUE_OPTIONAL),
                        'skilname' => new external_value(PARAM_RAW, 'skill', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_RAW, 'shortname of skill', VALUE_OPTIONAL),
                        'skill_catname' => new external_value(PARAM_RAW, 'category name in skill', VALUE_OPTIONAL),
                        'achieved_users' => new external_value(PARAM_RAW, 'achieved users in skill', VALUE_OPTIONAL),
                        'skillmaped' => new external_value(PARAM_RAW, 'skill maped to course', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }

    //////For displaying on level page//////////
      public static function manageskillslevelview_parameters() {
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
    public static function manageskillslevelview(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        require_login();
        $PAGE->set_url('/local/skillrepository/level.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageskillslevelview_parameters(),
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
        $result_skill = skills_level_details($stable,$filtervalues);
        $totalcount = $result_skill['count'];
        $data=$result_skill['data'];
        return [
            'is_admin' => is_siteadmin(),
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  manageskillslevelview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'is_admin' => new external_value(PARAM_BOOL, 'Is user an admin flag'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'visible' => new external_value(PARAM_INT, 'visible skill', VALUE_OPTIONAL),
                        'skillslevel_id' => new external_value(PARAM_RAW, 'id in skillslevel', VALUE_OPTIONAL),
                        'organisationname' => new external_value(PARAM_RAW, 'organisationname of skill', VALUE_OPTIONAL),
                        'skillslevelname' => new external_value(PARAM_RAW, 'skillslevel', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_RAW, 'shortname of skill', VALUE_OPTIONAL),
                        'skill_catname' => new external_value(PARAM_RAW, 'category name in skill', VALUE_OPTIONAL),
                        'achieved_users' => new external_value(PARAM_RAW, 'achieved users in skill', VALUE_OPTIONAL),
                        'code' => new external_value(PARAM_RAW, 'code in skillslevel', VALUE_OPTIONAL),
                        'username' => new external_value(PARAM_RAW, 'username in skillslevel', VALUE_OPTIONAL),
                        'dellevel' => new external_value(PARAM_RAW, 'level in course', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }

    //////For displaying on level page//////////
      public static function manageskillscategoryview_parameters() {
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
    public static function manageskillscategoryview(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        require_login();
        $PAGE->set_url('/local/skillrepository/skill_category.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageskillscategoryview_parameters(),
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
        $result_skill = skills_category_details($stable,$filtervalues);

        $totalcount = $result_skill['count'];
        $data=$result_skill['data'];
        return [
            'is_admin' => is_siteadmin(),
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  manageskillscategoryview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'is_admin' => new external_value(PARAM_BOOL, 'Is user an admin flag'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'visible' => new external_value(PARAM_INT, 'visible skill', VALUE_OPTIONAL),
                        'skillscategory_id' => new external_value(PARAM_RAW, 'id in skillslevel', VALUE_OPTIONAL),
                        'organisationname' => new external_value(PARAM_RAW, 'organisationname of skill', VALUE_OPTIONAL),
                        'skillscategoryname' => new external_value(PARAM_RAW, 'skillscategory', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_RAW, 'shortname of skill', VALUE_OPTIONAL),
                        'skill_catname' => new external_value(PARAM_RAW, 'category name in skill', VALUE_OPTIONAL),
                        'achieved_users' => new external_value(PARAM_RAW, 'achieved users in skill', VALUE_OPTIONAL),
                        'code' => new external_value(PARAM_RAW, 'code in skillslevel', VALUE_OPTIONAL),
                        'username' => new external_value(PARAM_RAW, 'username in skillslevel', VALUE_OPTIONAL),

                        'delete_cat' => new external_value(PARAM_RAW, 'delete in skillscategory', VALUE_OPTIONAL),

                    )
                )
            )
        ]);
    }



    /**
     * Describes the parameters for submit_skil interested_form webservice.
    * @return external_function_parameters
    */
    public static function submit_skills_interested_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the skills interested'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the skills interested form, encoded as a json array')
            )
        );
    }

    /**
     * Submit the Skills Interested form.
    *
    * @param int $contextid The context id .
    * @param string $jsonformdata The data from the form, encoded as a json array.
    * @return int new skill interested id.
    */
    public static function submit_skills_interested_form($contextid, $jsonformdata) {
        global $DB, $USER;
      
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_skills_interested_form_parameters(), ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        $data = array();
        parse_str($serialiseddata, $data);
       
        // The last param is the ajax submitted data.
        $mform = new local_skillrepository\form\skills_interested_form(null, array('contextid'=>$contextid), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();

        if ($validateddata) {
            $data = new stdClass();
            $data->interested_skill_ids = implode(',',$validateddata->skills);
            $data->id = $validateddata->id;
            $data->open_costcenterid =  $USER->open_costcenterid;

            if($validateddata->id>0){
                $data->usermodified = $USER->id;
                $data->timemodified = time();
                $intskillsupdate = $DB->update_record('local_interested_skills',$data);
            } else{
                $data->usercreated = $USER->id;
                $data->timecreated = time();
                $intskillsinsert = $DB->insert_record('local_interested_skills',$data);
                
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in submission');
        }
    }
    /**filter_starttime_assignment
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_skills_interested_form_returns() {
        return new external_value(PARAM_INT, 'skills interested id');
    }
        /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_assignskill_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'costcenterid' => new external_value(PARAM_INT, 'The costcenterid for the level'),
                'competencyid' => new external_value(PARAM_INT, 'The competencyid for the level'),
                'complevelid' => new external_value(PARAM_INT, 'The complevelid for the competency level table id'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    /**
     * form submission of role name and returns instance of this object
     *
     * @param int $contextid
     * @param [string] $jsonformdata
     * @return assignrole form submits
     */
    public static function submit_assignskill_form($contextid, $costcenterid, $competencyid, $complevelid, $jsonformdata){
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_assignskill_form_parameters(),
                                    ['contextid' => $contextid, 'costcenterid'=>$costcenterid, 'competencyid'=>$competencyid,'complevelid'=>$complevelid,'jsonformdata' => $jsonformdata]);
        // $context = $params['contextid'];
        $context = (new \local_skillrepository\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        // throw new moodle_exception('Error in creation');
        // die;
        $data = array();

          if (!empty($jsonformdata) && strlen($jsonformdata) > 2) {
                $serialiseddata = json_decode($params['jsonformdata']);
                if(is_object($serialiseddata)){
                    $serialiseddata = serialize($serialiseddata);
                }
                parse_str($serialiseddata, $data);
            }

        $warnings = array();
            
     //$data['skillid'] = empty($data['skillid']) ? 0 : implode(',',($data['skillid']));
        
        $mform = new local_skillrepository\form\assignskill(null, array('costcenterid'=>$costcenterid, 'competencyid'=>$competencyid,'complevelid'=>$complevelid), 'post', '', null, true, $data);
        $querylib  = new \local_skillrepository\local\querylib();
        
        $valdata = $mform->get_data();
        if($valdata){
            // $valdata->skillid=implode(',',$data['skillid']);
            $valdata->skillid=$data['skillid'];
           // $valdata->skilllevelid=$data['skilllevelid'];
            $levelid = $querylib->insert_update_competencylevel_skill($valdata);
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
    public static function submit_assignskill_form_returns() {
        return new external_value(PARAM_INT, 'levelid');
    }
    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_assigncourse_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'costcenterid' => new external_value(PARAM_INT, 'The costcenterid for the level'),
                'skillid' => new external_value(PARAM_INT, 'The skillid for the level'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    /**
     * form submission of role name and returns instance of this object
     *
     * @param int $contextid
     * @param [string] $jsonformdata
     * @return assignrole form submits
     */
    public static function submit_assigncourse_form($contextid, $costcenterid, $skillid, $jsonformdata){
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_assigncourse_form_parameters(),
                                    ['contextid' => $contextid, 'costcenterid'=>$costcenterid, 'skillid'=>$skillid,'jsonformdata' => $jsonformdata]);
        // $context = $params['contextid'];
        $context = (new \local_skillrepository\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        // throw new moodle_exception('Error in creation');
        // die;
        $data = array();

        parse_str($serialiseddata, $data);
        $warnings = array();
        $mform = new local_skillrepository\form\assigncourse(null, array('costcenterid'=>$costcenterid, 'skillid'=>$skillid), 'post', '', null, true, $data);
        $querylib  = new \local_skillrepository\local\querylib();
        
        $valdata = $mform->get_data();
        if($valdata){
            $levelid = $querylib->insert_competencycourse($valdata);
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
    public static function submit_assigncourse_form_returns() {
        return new external_value(PARAM_INT, 'levelid');
    }
     /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_assignlevel_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'costcenterid' => new external_value(PARAM_INT, 'The costcenterid for the level'),
                'competencyid' => new external_value(PARAM_INT, 'The competencyid for the level'),
                'skillid' => new external_value(PARAM_INT, 'The skillid for the level'),
                // 'positionid' => new external_value(PARAM_INT, 'The positionid for the level'),
                // 'levelid' => new external_value(PARAM_INT, 'The levelid for the level'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    /**
     * form submission of role name and returns instance of this object
     *
     * @param int $contextid
     * @param [string] $jsonformdata
     * @return assignrole form submits
     */
    public static function submit_assignlevel_form($contextid, $costcenterid, $skillid, $competencyid/*, $positionid, $levelid*/, $jsonformdata){
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_assignlevel_form_parameters(),
                                    ['contextid' => $contextid, 'costcenterid'=>$costcenterid, 'skillid'=>$skillid, 'competencyid'=>$competencyid/*, 'positionid'=>$positionid, 'levelid'=>$levelid*/,'jsonformdata' => $jsonformdata]);
        // $context = $params['contextid'];
        $context = (new \local_skillrepository\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        // throw new moodle_exception('Error in creation');
        // die;
        $data = array();

        parse_str($serialiseddata, $data);
        $warnings = array();
         $mform = new local_skillrepository\form\assignlevel(null, array('costcenterid'=>$costcenterid, 'skillid'=>$skillid/*, 'competencyid'=>$competencyid, 'positionid'=>$positionid, 'levelid'=>$levelid*/), 'post', '', null, true, $data);
        $querylib  = new \local_skillrepository\local\querylib();
        
        $valdata = $mform->get_data();

        if($valdata){
            $valdata->levelid=$data['levelid'];
            $levelid = $querylib->insert_update_skilllevel($valdata/*, $costcenterid, $competencyid, $skillid, $positionid, $levelid,$valdata->contextid*/);
            // $levelid = $querylib->insert_update_skillmatrix($valdata, $costcenterid, $competencyid, $skillid, $positionid, $levelid,$valdata->contextid);
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
    public static function submit_assignlevel_form_returns() {
        return new external_value(PARAM_INT, 'levelid');
    }

    public static function get_org_domains_parameters() {
        return new external_function_parameters(
            array(
                'orgid' => new external_value(PARAM_INT, 'The id for the costcenter / organization'),
            )
        );
    }
    public static function purge_level_skill_parameters(){
        return new external_function_parameters(
            array(
                'costcenterid' => new external_value(PARAM_INT, 'The costcenterid for the level',VALUE_DEFAULT,0),
                'competencyid' => new external_value(PARAM_INT, 'The competencyid for the level'),
                'levelid' => new external_value(PARAM_INT, 'The levelid for the competency'),
                'skillid' => new external_value(PARAM_INT, 'The skillid for the level'),
            )
        );
    }
    public static function purge_level_skill($costcenterid, $competencyid, $levelid, $skillid){
        global $DB;
        $params = self::validate_parameters(self::purge_level_skill_parameters(),
                ['costcenterid' => $costcenterid, 'competencyid' => $competencyid, 'levelid' => $levelid, 'skillid' => $skillid]);
        $conditions = array('costcenterid' => $costcenterid, 'competencyid' => $competencyid, 'skillid' => $skillid);
        $exist = $DB->record_exists('local_comp_skill_mapping', $conditions);
        if($exist){
            $DB->delete_records('local_skillmatrix', array('costcenterid' => $costcenterid, 'skill_categoryid' => $competencyid, 'levelid' => $levelid, 'skillid' => $skillid));
            $DB->delete_records('local_skill_levels', $conditions);
            $DB->delete_records('local_comp_skill_mapping', $conditions);
            return $DB->delete_records('local_comp_course_mapping', array('competencyid'=>$competencyid, 'skillid'=>$skillid));
        }else{
            return false;
        }
    }
    public static function purge_level_skill_returns(){
        return new external_value(PARAM_BOOL, 'Status');
    }
     /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function skill_level_confirmation_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'costcenterid' => new external_value(PARAM_INT, 'The costcenterid for the level'),
                'competencyid' => new external_value(PARAM_INT, 'The competencyid for the level'),
                'skillid' => new external_value(PARAM_INT, 'The competencyid for the level'),
                'positionid' => new external_value(PARAM_INT, 'The positionid for the level'),
                'levelid' => new external_value(PARAM_INT, 'The competencyid for the level'),
                'skilllevel' => new external_value(PARAM_INT, 'The levelid for the skill'),
            )
        );
    }

    /**
     * form submission of role name and returns instance of this object
     *
     * @param int $contextid
     * @param [string] $jsonformdata
     * @return assignrole form submits
     */
    public static function skill_level_confirmation($contextid, $costcenterid, $competencyid, $skillid, $positionid,$levelid, $skilllevel){
        global $PAGE, $CFG;
        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::skill_level_confirmation_parameters(),
                                    ['contextid' => $contextid, 'costcenterid'=>$costcenterid, 'competencyid'=>$competencyid,'skillid'=>$skillid, 'positionid'=>$positionid,'levelid'=>$levelid, 'skilllevel' => $skilllevel]);
        // $context = $params['contextid'];
        $context = context_system::instance();
        $querylib  = new \local_skillrepository\local\querylib();
        if($costcenterid > 0 && $competencyid > 0 && $skillid > 0 && $positionid > 0 ){
            $levelid = $querylib->insert_update_skillmatrix($costcenterid, $competencyid, $skillid, $positionid, $levelid, $skilllevel);
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
    public static function skill_level_confirmation_returns() {
        return new external_value(PARAM_INT, 'levelid');
    }
    public static function purge_skill_level_parameters(){
        return new external_function_parameters(
            array(
                'costcenterid' => new external_value(PARAM_INT, 'The costcenterid for the level'),
                'skillid' => new external_value(PARAM_INT, 'Skill id'),
                'levelid' => new external_value(PARAM_INT, 'The levelid for the skill'),
                'competencyid' => new external_value(PARAM_INT, 'The competencyid for the skill'),
            )
        );
    }
    public static function purge_skill_level($costcenterid, $skillid, $levelid, $competencyid){
        global $DB;
        $params = self::validate_parameters(self::purge_skill_level_parameters(),
                ['costcenterid' => $costcenterid, 'skillid' => $skillid, 'levelid' => $levelid, 'competencyid' => $competencyid]);
        $conditions = array('costcenterid' => $costcenterid, 'skillid' => $skillid, 'levelid' => $levelid, 'competencyid' => $competencyid);
        $exist = $DB->record_exists('local_skill_levels', $conditions);
        if($exist){
            $DB->delete_records('local_skillmatrix', array('costcenterid' => $costcenterid, 'skillid' => $skillid, 'skilllevel' => $levelid));
            $DB->delete_records('local_skill_levels', $conditions);
            return $DB->delete_records('local_comp_course_mapping', array('competencyid'=> $competencyid, 'skillid'=> $skillid, 'levelid'=>$levelid));
        }else{
            return false;
        }
    }
    public static function purge_skill_level_returns(){
        return new external_value(PARAM_BOOL, 'Status');
    }
    public static function purge_competency_level_parameters(){
        return new external_function_parameters(
            array(
                'costcenterid' => new external_value(PARAM_INT, 'The costcenterid for the level'),
                'competencyid' => new external_value(PARAM_INT, 'The competencyid for the level'),
                'levelid' => new external_value(PARAM_INT, 'The levelid for the competency'),
            )
        );
    }
    public static function purge_competency_level($costcenterid, $competencyid, $levelid){
        global $DB;
        $params = self::validate_parameters(self::purge_competency_level_parameters(),
                ['costcenterid' => $costcenterid, 'competencyid' => $competencyid, 'levelid' => $levelid]);
        $conditions = array('costcenterid' => $costcenterid, 'competencyid' => $competencyid, 'levelid' => $levelid);
        $exist = $DB->record_exists('local_competency_levels', $conditions);
        if($exist){
            $DB->delete_records('local_skillmatrix', array('costcenterid' => $costcenterid, 'skill_categoryid' => $competencyid, 'levelid' => $levelid));
            $DB->delete_records('local_comp_skill_mapping', array('costcenterid' => $costcenterid, 'competencyid' => $competencyid, 'competencylevelid' => $levelid));
            return $DB->delete_records('local_competency_levels', $conditions);
        }else{
            return false;
        }
    }
    public static function purge_competency_level_returns(){
        return new external_value(PARAM_BOOL, 'Status');
    }

    public static function competency_view_parameters() {
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
    public static function competency_view(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        require_login();
        $PAGE->set_url('/local/skillrepository/competency_view.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::competency_view_parameters(),
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

        $result_competency = custom_competency_details($stable,$filtervalues);
        $totalcount = $result_competency['count'];
        $data=$result_competency['data'];

        return [
            'wwwroot' => $CFG->wwwroot,
            'is_admin' => is_siteadmin(),
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  competency_view_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of custom_category in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'wwwroot' => new external_value(PARAM_RAW, 'The wwwroot for the service'),
            'is_admin' => new external_value(PARAM_BOOL, 'Is user an admin flag'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'id', VALUE_OPTIONAL),
                        'name' => new external_value(PARAM_RAW, 'competency name', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_RAW, 'competency shortname', VALUE_OPTIONAL),
                        'organisationname' => new external_value(PARAM_RAW, 'organisationname', VALUE_OPTIONAL),
                        'description' => new external_value(PARAM_RAW, 'description', VALUE_OPTIONAL),
                        'skillcount' => new external_value(PARAM_INT, 'skillcount', VALUE_OPTIONAL),
                        'levelcount' => new external_value(PARAM_INT, 'levelcount', VALUE_OPTIONAL),
                        'edit' => new external_value(PARAM_RAW, 'actions', VALUE_OPTIONAL),
                        'delete' => new external_value(PARAM_RAW, 'actions', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_competencycourse_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the skill category'),
                // 'costcenterid' => new external_value(PARAM_INT, 'The costcenterid for the level'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    public static function submit_competencycourse_form($contextid, $jsonformdata){
        global $PAGE, $CFG;
        require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_competencycourse_form_parameters(), ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);


        $context =(new \local_skillrepository\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);

        $data = array();

        parse_str($params['jsonformdata'], $data);

        $warnings = array();
        $customdata = array(
        'costcenterid' => $data['costcenterid'], 'courseid' => $data['courseid']);
        $mform = new local_skillrepository\form\competencycourse_form(null, $customdata, 'post', '', null, true, $data);
        $querylib  = new \local_skillrepository\local\querylib();
        
        $valdata = $mform->get_data();
        if($valdata){
            if($valdata->courseid)
            {
                $valdata->course = array($valdata->courseid);
            }
            $querylib->insert_competencycourse($valdata);
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
    public static function submit_competencycourse_form_returns() {
        return new external_value(PARAM_INT, 'category id');
    }

}

