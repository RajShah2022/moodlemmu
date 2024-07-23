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
 * @subpackage local_custom_category
 */

namespace local_custom_category\form;

use moodleform;
use core_component;

require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/costcenter/lib.php');
class custom_category_form extends moodleform {

    public function definition() {
        global $USER;
        $mform = $this->_form;
        $fid = $this->_customdata['id'];
        $parentid = $this->_customdata['parentid'];
        $parentcatid = $this->_customdata['parentcatid'];
        $costcenterid = $this->_customdata['open_costcenterid'];
        $id = optional_param('id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $context = (new \local_custom_category\lib\accesslib())::get_module_context();
        $categoryquerylib = new \local_custom_category\querylib();
        $costcenterquerylib = new \local_costcenter\querylib();
        if ($parentcatid > 0 && is_siteadmin()) {
            $costcenterid = $categoryquerylib->category_field('costcenterid', array('id' => $parentcatid));
            $orgname = $costcenterquerylib->costcenter_field('fullname', array('id' => $costcenterid));
            $mform->addElement('static', 'costcentername', get_string('open_costcenterid', 'local_costcenter'), $orgname);
            $mform->addElement('hidden', 'open_costcenterid', $costcenterid);
        } else {
            if ($fid && is_siteadmin()) {
                $orgname = $costcenterquerylib->costcenter_field('fullname', array('id' => $costcenterid));
                $mform->addElement('static', 'costcentername', get_string('open_costcenterid', 'local_costcenter'), $orgname);
                $mform->addElement('hidden', 'open_costcenterid');
            } else {
                local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata, range(1, 1), false, 'local_custom_category', $context, $multiple = false);
            }
        }

        if ($parentcatid > 0) {
            $parentname = $categoryquerylib->category_field('fullname', array('id' => $parentcatid));
            $mform->addElement('static', 'parentname', get_string('parent', 'local_costcenter'), $parentname);
            $mform->addElement('hidden', 'parentid', $parentcatid);
        } else {
            $parentname = 'Top';
            $mform->addElement('static', 'parentname', get_string('parent', 'local_costcenter'), $parentname);
            $mform->addElement('hidden', 'parentid', 0);
        }
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name', 'local_custom_category'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_custom_category'), array());
        $mform->setType('shortname', PARAM_RAW);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $corecomponent = new core_component();
        $pluginexists = $corecomponent::get_plugin_directory('local', 'custom_matrix');
        if ($pluginexists) {
            $matrixpluginstatus = get_config('local_custom_matrix', 'performance_matrix_status');
            if ($matrixpluginstatus) {
                if ($parentcatid  == 0) {
                    $choices = array(
                        null => get_string('selecttype', 'local_custom_category'),
                        0 => get_string('internalcat', 'local_custom_category'),
                        1 => get_string('externalcat', 'local_custom_category'),
                    );
                    $mform->addElement('select', 'type', get_string('type', 'local_custom_category'), $choices);
                    $mform->addHelpButton('type', 'type', 'local_custom_category');
                }
            }
        }
        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'];
        $id = $data['id'];
        $categoryquerylib = new \local_custom_category\querylib();
        $record = $categoryquerylib->category_shortname(array($shortname, $id));
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_custom_category');
        }
        if (strlen($shortname) > 150) {
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_custom_category');
        }
        return $errors;
    }
}
