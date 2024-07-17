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
 * @subpackage local_custom_matrix
 */

use local_custom_matrix\local\lang;
use local_custom_matrix\local\matrix_form_builder;
use moodleform;

defined('MOODLE_INTERNAL') || die;

/**
 * The Performance type class for the matrix Performance type.
 *
 */

require_once "{$CFG->dirroot}/lib/formslib.php";
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_once($CFG->dirroot . '/local/custom_matrix/lib.php');


/**
 * matrix editing form definition. For information about the Moodle forms library,
 * which is based on the HTML Quickform PEAR library
 *
 * @see http://docs.moodle.org/en/Development:lib/formslib.php
 */
class local_custom_matrix_edit_form extends moodleform {

    const DEFAULT_TYPE_ROWS = 3;
    const DEFAULT_TYPE_PARAMETER_ROWS = 2;

    const PERFORMANCE_TYPE = 'performancetype';
    const PERFORMANCE_PARAMETER = 'performanceparameter';
    const MAX_SCORE = 'maxscore';
    const WEIGHTAGE = 'weightage';

    /**
     *
     * @var matrix_form_builder
     */
    public $builder = null;

      /**
     * Build the form definition.
     *
     * This adds all the form fields that the manage categories feature needs.
     * @throws \coding_exception
     */
    public function definition() {

        $mform = &$this->_form;

        $core_component = new core_component();
        $templateid = required_param('temid',PARAM_INT);
        $orgid = required_param('orgid',PARAM_INT);

        $categorycontext = (new \local_custom_matrix\lib\accesslib())::get_module_context();
        $positions_plugin_exists = $core_component::get_plugin_directory('local', 'positions');
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'tempid', $templateid);
        $mform->setType('tempid', PARAM_INT);
        $mform->addElement('hidden', 'orgid', $orgid);
        $mform->setType('orgid', PARAM_INT);  

        $roletype = get_config('local_custom_matrix','performance_matrix_role_type');

        if($roletype == 1){ // For Designations
            $desoptions = get_designations();
            $mform->addElement('select', 'role', get_string('role', 'local_custom_matrix'), $desoptions);

        }else if($roletype == 2){ // For Positions
            if($positions_plugin_exists){

                $posoptions = get_positions();
                $mform->addElement('select', 'role', get_string('role', 'local_custom_matrix'), $posoptions);
             }
        }        

        $this->builder = new matrix_form_builder($mform);
        $builder = $this->builder;      

    }
    /**
     * Override if you need to setup the form depending on current values.
     * This method is called after definition(), data submission and set_data().
     * All form setup that is dependent on form values should go in here.
     *
     * @return void
     * @throws coding_exception
     */
    public function definition_after_data(): void {
        $builder = $this->builder;         
        $this->append_matrix();
    }
    public function append_matrix():void{
        $mform = $this->_form;
        $builder = $this->builder; 
        $matrix = [];
        $matrixgroup = $builder->create_group('matrix',null, $matrix, '', false);

        $this->_form->addElement($matrixgroup);
    }  

  
}
