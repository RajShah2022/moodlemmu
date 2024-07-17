<?php
namespace local_skillrepository\form;
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
use moodleform;
use context_system;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
class skillmatrix_form extends \moodleform {
	public function definition() {
		global $USER, $CFG, $DB, $PAGE;
		$mform = $this->_form;
		$id = $this->_customdata['id'];
        
		$context = (new \local_skillrepository\lib\accesslib())::get_module_context();
        if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
            $sql="select id,fullname from {local_costcenter} where visible =1 AND parentid = 0";
            $costcenters = $DB->get_records_sql($sql);
            $organizationlist=array(0=>get_string('selectopen_costcenterid', 'local_costcenter'));
            foreach ($costcenters as $scl) {
                $organizationlist[$scl->id]=$scl->fullname;
            }
            $costcenterOptions = [
                'class' => 'organizationselect',
                'data-class' => 'organizationselect',
                'data-action' => 'costcenter_category_selector',
                'onchange' => '(function(e){ require("local_costcenter/newcostcenter").changeElement(event) })(event)',
            ];
            $mform->addElement('autocomplete', 'costcenterid', get_string('open_costcenterid', 'local_costcenter'), $organizationlist, $costcenterOptions);
            $mform->addRule('costcenterid', get_string('requiredopen_costcenterid','local_costcenter'), 'required', null, 'client');
            $mform->setType('costcenterid', PARAM_INT);
        } else {
            $user_dept = $DB->get_field('user','open_path', array('id'=>$USER->id));
            $costarr = explode("/",$user_dept);

            $mform->addElement('hidden', 'costcenterid', null);
            $mform->setType('costcenterid', PARAM_INT);
            $mform->setConstant('costcenterid', $costarr[1]);
        }
        $submited_data = data_submitted();
        if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
            $sql = "SELECT id,name FROM {local_domains} where 1=1";
            if($submited_data->costcenterid) {
                $sql .=" AND costcenter=$submited_data->costcenterid";
            }
           $domains = array(0=>get_string('selectdomain', 'local_skillrepository')) + $DB->get_records_sql_menu($sql);
           // $organization = $USER->open_costcenterid;
        } else {
            $user_dept = $DB->get_field('user','open_path', array('id'=>$USER->id));
            $costarr = explode("/",$user_dept);
            $domains = array(0=>get_string('selectdomain', 'local_skillrepository')) + $DB->get_records_sql_menu("SELECT id,name FROM {local_domains} where costcenter=$costarr[1]");
            $organization = $costarr[1];
        }
        $options = array(
            'ajax' => 'local_positions/form-options-selector',
            'data-contextid' => $systemcontext->id,
            'data-selectstring' => get_string('noselectdomain', 'local_positions'),
            'data-action' => 'position_domain_selector',
            'data-parentclass' => 'organizationselect',
            'data-options' => json_encode(array('id' => $id,
                    'organizationselect' => 'organizationselect','userorganization' => $organization)),
            'class' => 'domainselect',
            'data-class' => 'domainselect',
            'id' => 'domainselect',
            'multiple' => false,
        );
        
        $mform->addElement('autocomplete', 'domain', get_string('domain', 'local_positions'), $domains, $options);
        $mform->addRule('domain', get_string('domainreq', 'local_positions'), 'required', null, 'client');
        $mform->setType('domain', PARAM_INT);
		$this->add_action_buttons(true,get_string('search','local_skillrepository'));
	}
	public function validation($data, $files) {
        $errors = array();
        global $DB, $CFG;

        if (isset($data['costcenterid'])){
            if($data['costcenterid'] == 0){
                $errors['costcenterid'] = get_string('requiredopen_costcenterid', 'local_costcenter');
            }
        }
        if(isset($data['domain'])){
            if($data['domain'] == 0){
                $errors['domain'] = get_string('domainreq', 'local_positions');
            }
        }
        return $errors;
    }
}
