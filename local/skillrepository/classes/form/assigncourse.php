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

class assigncourse extends moodleform {

    public function definition() {
        global $USER,$DB;
		$contextid = optional_param('contextid', 1, PARAM_INT);
        $mform = & $this->_form;
        $id = $this->_customdata['id'];
		$costcenterid = $this->_customdata['costcenterid'];
		$competencyid = $this->_customdata['competencyid'];
		$skillid = $this->_customdata['skillid'];
		$positionid = $this->_customdata['positionid'];
		$levelid = $this->_customdata['levelid'];
    		if(is_siteadmin())
    		{
				$path = "/".$costcenterid;
    		}else
    		{
    			$path = explode("/",$USER->open_path);
    			$path = "/".$path[1];
    		}
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
    	$systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
    	$courseParams = ['placeholder' => get_string('selectcourse',  'local_courses'),
    		'multiple' => true];
    		$sql = "SELECT id,fullname FROM {course} WHERE id > 1 AND visible = 1 AND open_coursetype = 0 AND open_path LIKE '".$path."%'";
    	$courses = [null => get_string('selectcourse',  'local_courses')] + $DB->get_records_sql_menu($sql);	
		$mform->addElement('autocomplete', 'course', get_string('selectcourse', 'local_courses'), $courses, $courseParams);
    	$mform->addRule('course', null, 'required', null, 'client');
		$mform->setType('course', PARAM_INT);

		$mform->addElement('hidden', 'costcenterid');
		$mform->setType('costcenterid', PARAM_TEXT);
		$mform->setDefault('costcenterid', $costcenterid);

		$mform->addElement('hidden', 'skill_categoryid');
		$mform->setType('skill_categoryid', PARAM_TEXT);
		$mform->setDefault('skill_categoryid', $competencyid);

		$mform->addElement('hidden', 'skillid');
		$mform->setType('skillid', PARAM_TEXT);
		$mform->setDefault('skillid', $skillid);

		$mform->addElement('hidden', 'levelid');
		$mform->setType('levelid', PARAM_TEXT);
		$mform->setDefault('levelid', $levelid);
		
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
        $errors = parent::validation($data, $files);
        
        if(isset($data['course']) && count($data['course']) == 0)
        {
            $errors['course'] = get_string("selectcourse","local_courses");
        }
 
        return $errors;
    }
}
