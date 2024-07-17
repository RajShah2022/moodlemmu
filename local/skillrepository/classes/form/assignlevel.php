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
 * @author Maheshchandra  <maheshchandra@eabyas.in>
 */
/**
 * Assign roles to users.
 * @package    local
 * @subpackage assignroles
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_skillrepository\form;
use moodleform;
use context_system;
require_once("{$CFG->libdir}/formslib.php");
require_once($CFG->dirroot . '/local/assignroles/lib.php');

class assignlevel extends moodleform {

    public function definition() {
        global $USER,$DB;
		$contextid = optional_param('contextid', 1, PARAM_INT);
        $mform = & $this->_form;
        $id = $this->_customdata['id'];
		$costcenterid = $this->_customdata['costcenterid'];
		$competencyid = $this->_customdata['competencyid'];
		$skillid = $this->_customdata['skillid'];
		$competencyid = $this->_customdata['competencyid'];
		$positionid = $this->_customdata['positionid'];
		$levelid = $this->_customdata['levelid'];
		$path = "/".$costcenterid;

    	$systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
		$concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    	$leveltype = array(null=>'--Select--');

		$levelselect = array(0 => get_string('selectlevel', 'local_skillrepository'));
			$levelSqls = "SELECT id, name FROM {local_course_levels} WHERE 1=1 AND open_path = '". $path ."'";
        	$levels = $DB->get_records_sql_menu($levelSqls, $params);
        if(!empty($levels)){
            $levelselect = $levelselect+$levels;
        }
        $select = $mform->addElement('autocomplete',  'levelid', get_string('level','local_skillrepository'), $levelselect);
        $mform->addRule('levelid', get_string('levelreq','local_skillrepository'), 'required', null, 'client');
        $mform->setType('levelid', PARAM_RAW);
	    $select->setMultiple(true);

        if($levelid > 0) {
        	$mform->setDefault('levelid', $levelid);
        }

		$mform->addElement('hidden', 'costcenterid');
		$mform->setType('costcenterid', PARAM_TEXT);
		$mform->setDefault('costcenterid', $costcenterid);

		$mform->addElement('hidden', 'competencyid');
		$mform->setType('competencyid', PARAM_TEXT);
		$mform->setDefault('competencyid', $competencyid);

		$mform->addElement('hidden', 'skillid');
		$mform->setType('skillid', PARAM_TEXT);
		$mform->setDefault('skillid', $skillid);
		
		$mform->addElement('hidden', 'positionid');
		$mform->setType('positionid', PARAM_TEXT);
		$mform->setDefault('positionid', $positionid);
		
		$mform->addElement('hidden',  'id', $id);
		$mform->setType('id', PARAM_INT);
		
		
		if(!$contextid){
			$mform->addElement('text', 'contextid', get_string('contextid', 'local_assignroles'));
			$mform->setType('contextid', PARAM_TEXT);
			$mform->setDefault('contextid', $contextid);
		}else{
			$mform->addElement('hidden', 'contextid');
			$mform->setType('contextid', PARAM_TEXT);
			$mform->setDefault('contextid', $contextid);
		}

        $this->add_action_buttons($cancel = null,get_string('assign', 'local_skillrepository'));
        $mform->disable_form_change_checker();
    }
    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        if(isset($data['levelid']) && count($data['levelid']) == 0){
        	$errors['levelid'] = get_string('err_level', 'local_skillrepository');
        }
        return $errors;
    }
}
