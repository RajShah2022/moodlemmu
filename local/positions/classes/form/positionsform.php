<?php
namespace local_positions\form;
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
use moodleform;
use context_system;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
class positionsform extends \moodleform {
    public function definition() {
        global $USER, $CFG, $DB, $PAGE;
        $mform = $this->_form;
        $id = $this->_customdata['id'];
        $costcenterid = $this->_customdata['open_costcenterid'];
        // $formdata = data_submitted();
        // print_object($formdata);
        if($id > 0) {
            $position_rec = $DB->get_record_sql("SELECT * FROM {local_positions} where id=$id");
        }
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        if($id && is_siteadmin()){
            $orgname= $DB->get_field('local_costcenter','fullname',array('id'=>$costcenterid));
            $mform->addElement('static','costcentername', get_string('open_costcenterid', 'local_costcenter'), $orgname);
            $mform->addElement('hidden','costcenter', $costcenterid);
        }else{
            if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
                $sql="select id,fullname from {local_costcenter} where visible =1 AND parentid = 0";
                if($id > 0 && !empty($position_rec)) {
                    $sql.=" AND id=$position_rec->costcenter";
                } else {
                    $organizationlist=array(0=>get_string('selectopen_costcenterid', 'local_costcenter'));
                }
                $costcenters = $DB->get_records_sql($sql);
                foreach ($costcenters as $scl) {
                    $organizationlist[$scl->id]=$scl->fullname;
                }
                $costcenterOptions = [
                    'class' => 'organizationselect',
                    'data-class' => 'organizationselect',
                    'data-action' => 'costcenter_category_selector',
                    'onchange' => '(function(e){ require("local_costcenter/newcostcenter").changeElement(event) })(event)',
                ];
                $mform->addElement('autocomplete', 'costcenter', get_string('open_costcenterid', 'local_costcenter'), $organizationlist, $costcenterOptions);
                $mform->addRule('costcenter', get_string('requiredopen_costcenterid', 'local_costcenter'), 'required', null, 'client');
                $mform->setType('costcenter', PARAM_INT);
            } else {
                $user_dept = $DB->get_field('user','open_path', array('id'=>$USER->id));
                $usercostcenter = substr($user_dept,1,1);

                $mform->addElement('hidden', 'costcenter', null);
                $mform->setType('costcenter', PARAM_INT);
                $mform->setConstant('costcenter', $usercostcenter);
            }
        }
        $domain_sql = "SELECT id,name FROM {local_domains} where 1=1";
        if(is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
            if($id > 0 && !empty($position_rec)) {
                $domain_sql .= " AND id=$position_rec->domain";
                $domains = $DB->get_records_sql_menu($domain_sql);
            } else {
                $domains = array(0=>get_string('selectdomain', 'local_positions')) + $DB->get_records_sql_menu($domain_sql);
            }
        } else {
            $user_dept = $DB->get_field('user','open_path', array('id'=>$USER->id));
            $usercostcenter = substr($user_dept,1,1);
            $domain_sql .= " AND costcenter={$usercostcenter}";
            if($id > 0 && !empty($position_rec)) {
                $domain_sql .= " AND id=$position_rec->domain";
                $domains =  $DB->get_records_sql_menu($domain_sql);
            }
            $domains = array(0=>get_string('selectdomain', 'local_positions')) + $DB->get_records_sql_menu($domain_sql);
        }
        if($costcenterid)
        {
            $arr = array('id' => $id,
                    'organizationselect' => 'organizationselect','userorganization' => $costcenterid,
                    'costcenterid' => $costcenterid);
        }
        elseif(!is_siteadmin())
        {
            $costarray = explode("/",$USER->open_path);
            $usercost = $costarray[1];
            $arr = array('id' => $id,
                    'organizationselect' => 'organizationselect','userorganization' => $usercost,
                    'costcenterid' => $usercost);
        }
        else
        {
            $arr = array('id' => $id,
                    'organizationselect' => 'organizationselect','userorganization' => $USER->open_costcenterid);
        }

        $options = array(
            'ajax' => 'local_positions/form-options-selector',
            'data-contextid' => $systemcontext->id,
            'data-selectstring' => get_string('selectdomain', 'local_positions'),
            'data-action' => 'position_domain_selector',
            'data-options' => json_encode($arr),
            'class' => 'domainselect',
            'data-class' => 'domainselect',
            'id' => 'domainselect',
            'onchange' => '(function(e){ require("local_costcenter/newcostcenter").changeElement(event) })(event)',
            'multiple' => false,
        );

        $domainss = $DB->get_records_sql_menu("SELECT id, name FROM {local_domains} WHERE 1 = 1");
        $domainss = [0 => get_string('selectdomain', 'local_positions')] + $domainss;

        $mform->addElement('autocomplete', 'domain', get_string('domain', 'local_positions'), $domainss, $options);
        $mform->addRule('domain', get_string('domainreq', 'local_positions'), 'required', null, 'client');
        $mform->setType('domain', PARAM_INT);
        $parent_positions = array(0=>get_string('selectparent', 'local_positions'));       
        if(is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
            if($id > 0 && !empty($position_rec)) {
                $sql ="SELECT id,name FROM {local_positions} where id = $position_rec->parent";
                $parent_positions += $DB->get_records_sql_menu($sql);//array(0=>'--Select Parent--')+
            } else {
                $sql ="SELECT id,name FROM {local_positions} where 1=1";
                $parent_positions += $DB->get_records_sql_menu($sql);
                // $sql ="SELECT id,name FROM {local_positions} where id NOT IN (select parent FROM {local_positions} where parent !=0)";
                // $parent_positions = $DB->get_records_sql_menu($sql);//array(0=>'--Select Parent--');
                // $parent_positions = array(0=>get_string('selectparent', 'local_positions'));//$DB->get_records_sql_menu($sql);
            }
        } else {
            $user_dept = $DB->get_field('user','open_path', array('id'=>$USER->id));
            $usercostcenter = substr($user_dept,1,1);
            if($id > 0 && !empty($position_rec)) {
                $sql ="SELECT id,name FROM {local_positions} where id = $position_rec->parent AND id != $id";
            } else {
                $sql ="SELECT id,name FROM {local_positions} where 1=1";
                // $sql ="SELECT id,name FROM {local_positions} where id NOT IN (select parent FROM {local_positions} where parent !=0) and costcenter={$user_dept}";
                // $parent_positions = array(0=>'--Select Parent--');//$DB->get_records_sql_menu($sql);
                // $parent_positions[0] = get_string('selectparent', 'local_positions');//$DB->get_records_sql_menu($sql);
            }
                $parent_positions += $DB->get_records_sql_menu($sql);//array(0=>'--Select Parent--');
        }
        if($costcenterid)
        {
            $arr = array('id' => $id,
                    'organizationselect' => 'organizationselect','domainselect' => 'domainselect', 'userorganization' => $USER->open_costcenterid ,'costcenterid' => $costcenterid);
        }
        elseif(!is_siteadmin())
        {   
            $costarray = explode("/",$USER->open_path);
            $usercost = $costarray[1];

            $arr = array('id' => $id,
                    'organizationselect' => 'organizationselect','userorganization' => $USER->open_costcenterid,
                    'costcenterid' => $usercost);
        }
        else
        {
            $arr = array('id' => $id,
                    'organizationselect' => 'organizationselect','domainselect' => 'domainselect', 'userorganization' => $USER->open_costcenterid);
        }

        $parentOptions = array(
            'ajax' => 'local_positions/form-options-selector',
            'data-contextid' => $systemcontext->id,
            'data-action' => 'position_parent_selector',
            'data-selectstring' => get_string('selectparent', 'local_positions'),
            'data-options' => json_encode($arr),
            'class' => 'domainparentclass',
            'data-class' => 'domainparentclass',
            'multiple' => false,
        );
        $parentpositions = $DB->get_records_sql_menu("SELECT id, name FROM {local_positions} WHERE 1 = 1");
        $parentpositions = [0 => get_string('selectdomain', 'local_positions')] + $parentpositions;


        $mform->addElement('autocomplete', 'parent', get_string('parent', 'local_positions'), $parentpositions, $parentOptions);
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('text',  'name',  get_string('positionname','local_positions'));
        $mform->addRule('name', get_string('positionnamereq', 'local_positions'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('text',  'code',  get_string('positioncode',  'local_positions'));
        $mform->addRule('code', get_string('positioncodereq', 'local_positions'), 'required', null, 'client');
        $mform->setType('code', PARAM_RAW);
        
        $mform->addElement('hidden',  'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->disable_form_change_checker();
        $this->add_action_buttons();
    }
    public function validation($data, $files) {
        $errors = array();
        global $DB, $CFG;
        if(isset($data['domain'][0]))
        {
            $data['domain'] = $data['domain'][0];
        }

        if(isset($data['parent'][0]))
        {
            $data['parent'] = $data['parent'][0];
        }

        if (isset($data['costcenter'])){
            if($data['costcenter'] == 0){
                $errors['costcenter'] = get_string('requiredopen_costcenterid', 'local_costcenter');
            }
        }

        // if(empty($data['name'])){
        //     $errors['name'] = get_string('positionnamereq', 'local_positions');
        // }
        // if(empty($data['code'])){
        //     $errors['code'] = get_string('positioncodereq', 'local_positions');
        // }
        if(isset($data['domain'])){
            if($data['domain'] == 0){
                $errors['domain'] = get_string('domainreq', 'local_positions');
            }
        }
        if ($parent = $DB->get_field('local_positions', 'id', array('costcenter'=> $data['costcenter'], 'domain'=> $data['domain'], 'parent'=> $data['parent']))) {
            if($data['parent'] !=0) {
                if (empty($data['id']) || $parent != $data['id']) {
                    $errors['parent'] = get_string('parentexist', 'local_positions');
                }
            }
        }
        if ($levelid = $DB->get_field('local_positions', 'id', array('code' => $data['code'], 'costcenter'=> $data['costcenter'], 'domain'=> $data['domain']))) {
            if (empty($data['id']) || $levelid != $data['id']) {
                $errors['code'] = get_string('positioncodeexists', 'local_positions');
            }
        }
        return $errors;
    }
}
