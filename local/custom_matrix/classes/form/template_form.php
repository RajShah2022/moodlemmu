<?php
namespace local_custom_matrix\form;
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
 * @subpackage local_custom_matrix
 */
use moodleform;
use context_system;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/costcenter/lib.php');
class template_form extends moodleform {

    public function definition() {
        global $USER,$DB;
        $categorycontext = (new \local_custom_matrix\lib\accesslib())::get_module_context();

        $mform = $this->_form;
        $fid = $this->_customdata['id'];        
        $id = optional_param('id', 0, PARAM_INT);
        $costcenterid = $this->_customdata['open_costcenterid'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $context = (new \local_custom_matrix\lib\accesslib())::get_module_context();
        $matrixquerylib = new \local_custom_matrix\querylib();
        $costcenterquerylib = new \local_costcenter\querylib();
        if($fid && is_siteadmin()){               
            $orgname = $costcenterquerylib->get_costcenterfield('fullname', array('id'=>$costcenterid));
            $mform->addElement('static','costcentername', get_string('open_costcenterid', 'local_costcenter'), $orgname);
            $mform->addElement('hidden','open_costcenterid');
        }else{
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1, 1), false, 'local_custom_matrix', $context, $multiple = false);
        }

        $mform->addElement('text', 'template_name', get_string('template_name', 'local_custom_matrix'));
        $mform->setType('template_name', PARAM_RAW);
        $mform->addRule('template_name', null, 'required', null, 'client');

        $mform->addElement('text', 'financialyear', get_string('financial_year', 'local_custom_matrix'));
        $mform->setType('financialyear', PARAM_RAW);
        $mform->addRule('financialyear', null, 'required', null, 'client');       

        $checkboxes = array();
        $checkboxes[] = $mform->createElement('advcheckbox', 'active', null, '', array(),array(0,1));
        $mform->addGroup($checkboxes, 'active', get_string('activatetemplate', 'local_custom_matrix'), array(' '), false);
        $mform->addHelpButton('active', 'activatetemplate', 'local_custom_matrix');
       
        $mform->disable_form_change_checker();        
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'];
        $id = $data['id'];
        $costcenterid = $data['open_costcenterid'];
        $matrixquerylib = new \local_custom_matrix\querylib();
        $record = $matrixquerylib->get_matrixshortname(array($shortname, $id, $costcenterid)); 
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_custom_matrix');
        }
        if(strlen($shortname) > 150){
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_custom_matrix');
        }
        if (!empty($costcenterid) && $data['parentcatid'] = 0){
            if($data['type'] != NULL) {
                $errors['type'] = get_string('typerequired', 'local_custom_matrix');
            }
        } 
        return $errors;
    }

}
