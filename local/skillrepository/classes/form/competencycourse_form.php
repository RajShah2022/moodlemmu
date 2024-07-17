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
namespace local_skillrepository\form;
use moodleform;
use context_system;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/costcenter/lib.php');

class competencycourse_form extends moodleform {

    public function definition() {
        global $DB,$USER;
        $mform = $this->_form;
        $costcenterid = $this->_customdata['costcenterid'];
        $courseid = $this->_customdata['courseid'];

        $id = $this->_customdata['id'];
        $mform->addElement('hidden', 'id', $id);
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->addElement('hidden', 'costcenterid', $costcenterid);
        $mform->setType('id', PARAM_INT);

        $context =(new \local_skillrepository\lib\accesslib())::get_module_context();


        $compsql = "SELECT id,name AS fullname FROM {local_skill_categories} WHERE open_path =:open_path";
        $comps = array(0 => get_string('selectcompetency', 'local_skillrepository'))+$DB->get_records_sql_menu($compsql, array('open_path'=>$costcenterid));
       
        $compattribute = array(
            'data-action' => 'competency_selector_action',
            'onchange' => '(function(e){ require("local_costcenter/newcostcenter").changeElement(event) })(event)',
            'multiple' => false,
        );
        $select = $mform->addElement('autocomplete', 'competencyid', get_string('competency', 'local_skillrepository'),$comps,$compattribute);
        $mform->addRule('competencyid', null, 'required', null, 'client');
        $mform->setType('competencyid', PARAM_INT);
        $select->setMultiple(false);

        $skilloptions = array(
            'ajax' => 'local_positions/form-options-selector',
            'data-contextid' => $systemcontext->id,
            'data-selectstring' => get_string('selectskill', 'local_skillrepository'),
            'data-action' => 'skill_selector_action',
            'data-options' => json_encode(array('parentid' => $costcenterid, 'enableallfield' => false)),
            'class' => 'skillselector',
            'data-class' => 'skillselector',
            'onchange' => '(function(e){ require("local_costcenter/newcostcenter").changeElement(event) })(event)',
            'multiple' => false,
        );
        $skillsql = "SELECT id,name AS fullname FROM {local_skill} WHERE open_path =:open_path";
        $skills = array(0 => get_string('selectskill', 'local_skillrepository'))+$DB->get_records_sql_menu($skillsql, array('open_path'=>$costcenterid));
       

        $select = $mform->addElement('autocomplete', 'skillid', get_string('skill', 'local_skillrepository'),$skills, $skilloptions);
        $mform->addRule('skillid', null, 'required', null, 'client');
        $mform->setType('skillid', PARAM_INT);
        $select->setMultiple(false);

        $leveloptions = array(
            'ajax' => 'local_positions/form-options-selector',
            'data-contextid' => $systemcontext->id,
            'data-selectstring' => get_string('selectlevel', 'local_skillrepository'),
            'data-action' => 'level_selector_action',
            'data-options' => json_encode(array('parentid' => $costcenterid, 'enableallfield' => false)),
            'class' => 'levelselector',
            'data-class' => 'levelselector',
            'multiple' => false,
        );
        $levelsql = "SELECT id,name AS fullname FROM {local_course_levels} WHERE open_path =:open_path";
        $levels = array(0 => get_string('selectlevel', 'local_skillrepository'))+$DB->get_records_sql_menu($levelsql, array('open_path'=>$costcenterid));
       

        $select = $mform->addElement('autocomplete', 'levelid', get_string('level', 'local_skillrepository'),$levels, $leveloptions);
        $mform->addRule('levelid', null, 'required', null, 'client');
        $mform->setType('levelid', PARAM_INT);
        $select->setMultiple(false);

        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        if($data['competencyid'] == 0)
        {
            $errors['competencyid'] = get_string('errcompetency', 'local_skillrepository'); 
        }
        if($data['skillid'] == 0)
        {
            $errors['skillid'] = get_string('err_courses', 'local_skillrepository'); 
        }
        if($data['levelid'] == 0)
        {
            $errors['levelid'] = get_string('err_level', 'local_skillrepository'); 
        }

        return $errors;
    }

}
