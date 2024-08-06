<?php 
namespace local_courses\form;

use core_form\dynamic_form;
use moodle_url;
use context;
use stdClass;
use context_system;
use local_courses\api;
require_once($CFG->libdir . '/formslib.php');
/**
 * Defines Courses Topics Settings form
 *
 * @module     local_courses
 * @copyright  2024 Moodle India Information Solutions Pvt Ltd
 * @author     Rizwana Madhire (rizwana.madire@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class groupsconfiguration extends dynamic_form {

    /**
     * Define the form
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        $this->_form->_attributes['class'] = 'groupsconfiguration_form';

        $id = $this->optional_param('id', 0, PARAM_INT);
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'g.open_path');
        $grouplist = $DB->get_records_sql("SELECT c.id, c.name as fullname, g.open_difficultylevel FROM {local_groups} g, {cohort} c WHERE c.visible = :visible AND c.id = g.cohortid  $costcenterpathconcatsql ",
            array('visible' => 1));
        if(!empty($grouplist)) {
            // Add checkboxes to the form.
            foreach ($grouplist as $id => $name) {
                $requeststatus = $DB->get_field('local_request_records', 'status', array('componentid' => $id, 'courseid' => $courseid));
                $mform->addElement('advcheckbox', $id, '', $name->fullname . ' (' . get_string('difficultylevels', 'local_courses', $name->open_difficultylevel) . ')' . get_string('classenrollmentrequeststatus', 'local_courses', $requeststatus), ['class' => 'custom_switch']);
            }  
        } else {
            $mform->addElement('static', 'nogroups', '', get_string('nogroups', 'local_courses'));
        }
    }


    /**
     * Perform some moodle validation.
     *
     * @param array $datas
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        // $context = \context_system::instance();
        return $errors;
    }

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $contextid = $this->optional_param('contextid', \context_system::instance()->id, PARAM_INT);
        return \context::instance_by_id($contextid);
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
        
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     */
    public function process_dynamic_submission() {
        // global $DB;
        // $data = $this->get_data();
        // if ($data->id == 0) {
        //     api::insert_instance($data);
        // } else {
        //     api::update_instance($data);
        // }
        $data = $this->get_data();
        api::insert_groupsconfig_instance($data);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        if ($courseid) {
            $existinggroups = $DB->get_records('local_coursegroups', ['courseid' => $courseid], '', 'groupid');

            $data = new stdClass();
            if(!empty($existinggroups)) {
                foreach ($existinggroups as $group => $record) {
                    $data->{$group} = 1;
                }
                $this->set_data($data);
            }
            
        }
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new moodle_url('/local/courses/courses.php',
            ['action' => 'groupsconfiguration', 'id' => $id]);
    }
}
