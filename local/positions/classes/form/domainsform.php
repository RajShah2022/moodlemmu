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
use costcenter;
require_once(dirname(__FILE__) . '/../../../../config.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
class domainsform extends \moodleform {
	public function definition() {
		global $USER, $CFG, $DB, $PAGE;
		$mform = $this->_form;
		$id = $this->_customdata['id'];
        $costcenterid = $this->_customdata['open_costcenterid'];
		// $context = context_system::instance();
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        if($id && is_siteadmin()){
            $orgname= $DB->get_field('local_costcenter','fullname',array('id'=>$costcenterid));
            $mform->addElement('static','costcentername', get_string('open_costcenterid', 'local_costcenter'), $orgname);
            $mform->addElement('hidden','open_costcenterid');
        }else{
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1, 1), false, 'local_skillrepository', $context, $multiple = false);
        }
        /*if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {

            $sql="select id,fullname from {local_costcenter} where visible =1 AND parentid = 0";
            $costcenters = $DB->get_records_sql($sql);
            $organizationlist=array(0=>get_string('selectorg','local_positions'));
            foreach ($costcenters as $scl) {
                $organizationlist[$scl->id]=$scl->fullname;
            }
            $mform->addElement('autocomplete', 'costcenter', get_string('open_costcenterid', 'local_costcenter'), $organizationlist);
            $mform->addRule('costcenter', null, 'required', null, 'client');
            $mform->setType('costcenter', PARAM_INT);
        } else {
            $user_dept = $DB->get_field('user','open_costcenterid', array('id'=>$USER->id));
            $mform->addElement('hidden', 'costcenter', null);
            $mform->setType('costcenter', PARAM_INT);
            $mform->setConstant('costcenter', $user_dept);
        }*/
        // local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), true, 'local_positions', $categorycontext, $multiple = false);

        $mform->addElement('text',  'name',  get_string('domainname','local_positions'));
		$mform->addRule('name', get_string('domainnamereq', 'local_positions'), 'required', null, 'client');
		$mform->setType('name', PARAM_TEXT);

		$mform->addElement('text',  'code',  get_string('domaincode',  'local_positions'));
		$mform->addRule('code', get_string('domaincodereq', 'local_positions'), 'required', null, 'client');
		$mform->setType('code', PARAM_RAW);

		$mform->addElement('hidden',  'id', $id);
		$mform->setType('id', PARAM_INT);
        $mform->disable_form_change_checker();
		$this->add_action_buttons();
	}
	public function validation($data, $files) {
        $errors = array();
        global $DB, $CFG;
        if (isset($data['costcenter'])){
            if($data['costcenter'] == 0){
                $errors['costcenter'] = get_string('requiredopen_costcenterid', 'local_costcenter');
            }
        }
        // if(empty($data['name'])){
        //     $errors['name'] = get_string('domainnamereq', 'local_positions');
        // }
        // if(empty($data['code'])){
        //     $errors['code'] = get_string('domaincodereq', 'local_positions');
        // }
        // if ($levelid = $DB->get_field('local_domains', 'id', array('code' => $data['code'], 'costcenter'=> $data['costcenter']))) {
        //     if (empty($data['id']) || $levelid != $data['id']) {
        //         $errors['code'] = get_string('domaincodeexists', 'local_positions');
        //     }
        // }
        $record = $DB->get_record_sql('SELECT * FROM {local_domains} WHERE code = ? AND costcenter = ? AND id <> ?', array($data['code'], $data['open_costcenterid'], $data['id']));
        if (!empty($record)) {
            $errors['code'] = get_string('domaincodeexists', 'local_positions');
        }
        return $errors;
    }
}
