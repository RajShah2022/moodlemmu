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
class skillmatrixform extends \moodleform {
	public function definition() {
		global $USER, $CFG, $DB, $PAGE;
		$mform = $this->_form;
		$id = $this->_customdata['id'];

		$context = (new \local_skillrepository\lib\accesslib())::get_module_context();
        if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
            $sql="select id,fullname from {local_costcenter} where visible =1 AND parentid = 0";
            $costcenters = $DB->get_records_sql($sql);
            $organizationlist=array(0=>'--Select Organization--');
            foreach ($costcenters as $scl) {
                $organizationlist[$scl->id]=$scl->fullname;
            }
            $mform->addElement('autocomplete', 'costcenterid', get_string('open_costcenterid', 'local_costcenter'), $organizationlist);
            $mform->addRule('costcenterid', null, 'required', null, 'client');
            $mform->setType('costcenterid', PARAM_INT);
        } else {
            $user_dept = $DB->get_field('user','open_costcenterid', array('id'=>$USER->id));
            $mform->addElement('hidden', 'costcenterid', null);
            $mform->setType('costcenterid', PARAM_INT);
            $mform->setConstant('costcenterid', $user_dept);
        }
        
        if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
            $domains = array(0=>'--Select Domain--') + $DB->get_records_sql_menu("SELECT id,name FROM {local_domains} where 1=1");
        } else {
            $user_dept = $DB->get_field('user','open_costcenterid', array('id'=>$USER->id));
            $domains = array(0=>'--Select Domain--') + $DB->get_records_sql_menu("SELECT id,name FROM {local_domains} where costcenter=$user_dept");
        }
        $mform->addElement('autocomplete', 'domain', get_string('domain', 'local_positions'), $domains, $options);
        $mform->addRule('domain', get_string('domainreq', 'local_positions'), 'required', null, 'client');
        $mform->setType('domain', PARAM_INT);

		$this->add_action_buttons();
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
