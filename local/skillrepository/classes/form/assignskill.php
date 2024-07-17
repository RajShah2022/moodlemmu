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
require_once("{$CFG->libdir}/formslib.php");
require_once($CFG->dirroot . '/local/assignroles/lib.php');

class assignskill extends moodleform {

    public function definition() {
        global $USER,$DB;
		$contextid = optional_param('contextid', 1, PARAM_INT);
        $mform = & $this->_form;
        $id = $this->_customdata['id'];
		$skillid = $this->_customdata['skillid'];
        $costcenterid = $this->_customdata['costcenterid'];
        $competencyid = $this->_customdata['competencyid'];
		$complevelid = $this->_customdata['complevelid'];
        $path = "/".$costcenterid;
		
		//Course field start's here
		$concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $skillsql = "SELECT id,name AS fullname FROM {local_skill} WHERE open_path = :openpath";

    $skills = array(0 => get_string('selectskill', 'local_skillrepository'))+$DB->get_records_sql_menu($skillsql, array('openpath' => $path));
   

	$select = $mform->addElement('autocomplete', 'skillid', get_string('skill', 'local_skillrepository'),$skills);
    $mform->addRule('skillid', null, 'required', null, 'client');
    $mform->setType('skillid', PARAM_RAW);
    $select->setMultiple(true);


		$mform->addElement('hidden', 'costcenterid');
		$mform->setType('costcenterid', PARAM_INT);
		$mform->setDefault('costcenterid', $costcenterid);

    $mform->addElement('hidden', 'competencyid');
    $mform->setType('competencyid', PARAM_INT);
    $mform->setDefault('competencyid', $competencyid);

    $mform->addElement('hidden', 'complevelid');
    $mform->setType('complevelid', PARAM_INT);
    $mform->setDefault('complevelid', $complevelid);

		$mform->addElement('hidden',  'id', $id);
		$mform->setType('id', PARAM_INT);
        
        $this->add_action_buttons($cancel = null,get_string('assign', 'local_assignroles'));
        $mform->disable_form_change_checker();
    }

    function validation($data, $files) {

        $errors = parent::validation($data, $files);
        

        // $form_data = dataprint_R($data);_submitted();
        if(count($data['skillid']) == 0)
        {
           $errors['skillid'] = get_string('err_courses', 'local_skillrepository');
        }

        return $errors;
    }
}
