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

class skill_repository_form extends moodleform {

    public function definition() {
        global $DB,$USER;
        $mform = $this->_form;
        $costcenterid = $this->_customdata['open_costcenterid'];

        $id = $this->_customdata['id'];
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $context =(new \local_skillrepository\lib\accesslib())::get_module_context();

        $sid = $this->_customdata['id'];
        if($sid > 0)
        {
            $skillcompetency = $DB->get_records_sql("SELECT id FROM {local_comp_skill_mapping} WHERE skillid = $sid");
        }

        if($skillcompetency && is_siteadmin())
        {
            $orgname= $DB->get_field('local_costcenter','fullname',array('id'=>$costcenterid));
            $mform->addElement('static','costcentername', get_string('open_costcenterid', 'local_costcenter'), $orgname);
            $mform->addElement('hidden','open_costcenterid', $costcenterid);
        }else
        {
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1),false, 'local_skillrepository', $context, $multiple = false);
        }

        $mform->addElement('text', 'name', get_string('name', 'local_skillrepository'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name',  get_string('skill_namereq', 'local_skillrepository'), 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_skillrepository'), array());
        $mform->setType('shortname', PARAM_RAW);
        $mform->addRule('shortname', get_string('skill_codereq', 'local_skillrepository'), 'required', null, 'client');

        $mform->addElement('editor', 'description', get_string('description'),NULL, array("autosave"=>false));
        $mform->setType('description', PARAM_RAW);

        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'];
        $name = $data['name'];
        $id = $data['id'];
        $category = $data['category'];
        $path = "/".$data['open_costcenterid'];
        $record = $DB->get_record_sql('SELECT * FROM {local_skill} WHERE shortname = ? AND open_path = ? AND  id <> ?', array($shortname, $path, $id));
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_skillrepository');
        }
        if(strlen($shortname) > 150){
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_skillrepository');
        }
        if(strlen($name) > 150){
            $errors['name'] = get_string('namelengthexceeds', 'local_skillrepository');
        }
        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('validname', 'local_skillrepository');
        }
        if (empty(trim($data['shortname']))) {
            $errors['shortname'] = get_string('validshortname', 'local_skillrepository');
        }

        return $errors;
    }

}
