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
 * @subpackage local_positions
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
class local_positions_external extends external_api {
    //Positions related functions

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_position_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
           
            )
        );
    }


    public static function submit_position_form($contextid, $jsonformdata){
        global $PAGE, $CFG;

        // require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_position_form_parameters(),
                                    ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);
        
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        
        
        $data = array();
       
        parse_str($params['jsonformdata'], $data);
        $warnings = array();
         $mform = new \local_positions\form\positionsform(null, array(), 'post', '', null, true, $data);
        
        $querylib  = new \local_positions\local\querylib();
        
        $valdata = $mform->get_data();
        if($valdata){
            $valdata->domain=$data['domain'];
            $valdata->parent=$data['parent'];
            $positionid = $querylib->insert_update_position($valdata);
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
        return $positionid;
    }


    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_position_form_returns() {
        return new external_value(PARAM_INT, 'position id');
    }

    //Domains related functions

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_domain_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
           
            )
        );
    }


    public static function submit_domain_form($contextid, $jsonformdata){
        global $PAGE, $CFG, $DB, $USER;

        // require_once($CFG->dirroot . '/local/skillrepository/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_domain_form_parameters(),
                                    ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);
        
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        
        
        $data = array();
       
        parse_str($params['jsonformdata'], $data);
        if($data['id'])
        {
            $data['open_costcenterid'] = $DB->get_field('local_domains', 'costcenter', array('id' => $data['id']));
        }

        $warnings = array();
         $mform = new \local_positions\form\domainsform(null, array(), 'post', '', null, true, $data);
        
        $querylib  = new \local_positions\local\querylib();
        
        $valdata = $mform->get_data();
        if($valdata){
            if(!is_siteadmin())
            {
                $costid = explode('/',$USER->open_path)[1];
                $valdata->open_costcenterid = $costid;
            }
            $domainid = $querylib->insert_update_domain($valdata);
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
        return $domainid;
    }


    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_domain_form_returns() {
        return new external_value(PARAM_INT, 'domain id');
    }

    public static function organization_positions_parameters() {
        return new external_function_parameters(
            array(
                'orgid' => new external_value(PARAM_INT, 'The id for the costcenter / organization'),
                'domainid' => new external_value(PARAM_INT, 'The id for the domain'),
            )
        );
    }
    public static function organization_positions($orgid, $domainid=false) {
        global $DB;
        $sql = "SELECT id,name FROM {local_positions} where id NOT IN (select parent FROM {local_positions})";            
        if($orgid) {
            $sql .=" AND costcenter={$orgid}";
        } 
        if($domainid !=0){
            $sql .=" AND domain={$domainid}";
        }
     $parents = $DB->get_records_sql_menu($sql);
     $domains = $DB->get_records_menu('local_domains',array('costcenter' => $orgid),'name','id,name');
     $data = array('parents'=>json_encode($parents), 'domains'=>json_encode($domains));
          return $data;
    }
    public static function organization_positions_returns() {
        // return new external_value(PARAM_RAW, 'data');
        return new external_function_parameters(
          array(
            'parents' => new external_value(PARAM_RAW, 'parents '),
            'domains' => new external_value(PARAM_RAW, 'domains'),
          )
        );
    }
    public static function positions_form_option_selector_parameters(){
        $query = new external_value(
            PARAM_RAW,
            'Query string'
        );
        $action = new external_value(
            PARAM_RAW,
            'Action for the program form selector'
        );
        $options = new external_value(
            PARAM_RAW,
            'Action for the program form selector'
        );

        return new external_function_parameters(array(
            'query' => $query,
            'context' => self::get_context_parameters(),
            'action' => $action,
            'options' => $options
        ));
    }
    public static function positions_form_option_selector($query, $context, $action, $options){
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::positions_form_option_selector_parameters(), array(
            'query' => $query,
            'context' => $context,
            'action' => $action,
            'options' => $options
        ));
        $query = trim($params['query']);
        $action = $params['action'];
        $context = self::get_context_from_params($params['context']);
        $options = $params['options'];
        if (!empty($options)) {
            $formoptions = json_decode($options);
        }
        self::validate_context($context);
        if ($action) {
            $return = array();
            switch($action){
                case 'position_domain_selector':
                    $domainSql = "SELECT id,name as fullname FROM {local_domains} WHERE costcenter = :costcenter ";
                    $params = ['costcenter' => $formoptions->costcenterid];
                    if (!empty($query)) {
                        $domainSql .= " AND name LIKE :query ";
                        $params['query'] = '%' . $query . '%';
                    }
                    $return = array(0 => array('id' => 0,'fullname' => get_string('selectdomain', 'local_positions'))) + $DB->get_records_sql($domainSql, $params);
                break;
                case 'position_parent_selector':
                    if($formoptions->id){
                        $parentSql = "SELECT id,name as fullname FROM {local_positions} where costcenter = :costcenter AND domain = :domain AND id != :id";
                    } else {
                        $parentSql = "SELECT id,name as fullname FROM {local_positions} where costcenter = :costcenter AND domain = :domain ";
                    }
                    // id NOT IN (select parent FROM {local_positions})
                    $params = ['costcenter' => $formoptions->costcenterid, 'domain' => $formoptions->domain, 'id' => $formoptions->id];
                    if (!empty($query)) {
                        $parentSql .= " AND name LIKE :query ";
                        $params['query'] = '%' . $query . '%';
                    }
                    $return = array(0 => array('id' => 0,'fullname' => get_string('selectparent', 'local_positions'))) + $DB->get_records_sql($parentSql, $params);
                break;
                case 'skill_selector_action':
                    if($formoptions->competencyid > 0)
                    {
                        $skillsql = "SELECT ls.id,ls.name AS fullname FROM {local_skill} AS ls, {local_comp_skill_mapping} AS lcs WHERE ls.id = lcs.skillid AND lcs.competencyid =:competencyid AND ls.open_path =:open_path";
                        $skills = array(0 => get_string('selectskill', 'local_skillrepository'))+$DB->get_records_sql($skillsql, array('competencyid'=>$formoptions->competencyid, 'open_path'=>$formoptions->parentid));

                        $return = $skills;
                    }
                break;
                case 'level_selector_action':
                    if($formoptions->skillid > 0)
                    {
                        $levelsql = "SELECT lcl.id, lcl.name AS fullname FROM {local_course_levels} AS lcl, {local_skill_levels} AS lsl WHERE lcl.id = lsl.levelid AND lsl.skillid =:skillid AND lcl.open_path =:open_path AND lsl.competencyid =:competencyid";
                        $levels = array(0 => get_string('selectlevel', 'local_skillrepository'))+$DB->get_records_sql($levelsql, array('skillid'=>$formoptions->skillid, 'open_path'=>$formoptions->parentid, 'competencyid'=>$formoptions->competencyid));

                        $return = $levels;
                    }
                break;
            }
            return json_encode($return);
        }
    }
    public static function positions_form_option_selector_returns(){
        return new external_value(PARAM_RAW, 'data');
    }
}
