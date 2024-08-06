<?php 
namespace local_courses\form;

use core_form\dynamic_form;
use moodle_url;
use context;
use stdClass;
use context_system;
use local_courses\api;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/course/format/lib.php');
/**
 * Defines Courses Topics Settings form
 *
 * @module     local_courses
 * @copyright  2024 Moodle India Information Solutions Pvt Ltd
 * @author     Rizwana Madhire (rizwana.madire@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class topicsconfiguration extends dynamic_form {

    /**
     * Define the form
     */
    public function definition() {
    global $DB;

    $mform = $this->_form;
    $this->_form->_attributes['class'] = 'topicsconfiguration_form';

    $id = $this->optional_param('id', 0, PARAM_INT);
    $courseid = $this->optional_param('courseid', 0, PARAM_INT);

    $mform->addElement('hidden', 'id', $id);
    $mform->setType('id', PARAM_INT);

    $mform->addElement('hidden', 'courseid', $courseid);
    $mform->setType('courseid', PARAM_INT);

    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $format = course_get_format($course);

    $allsections = $format->get_modinfo()->get_section_info_all();
    if(count($allsections) > 1) {
        // Build the checkbox options.
        $checkboxes = $this->build_section_paths($allsections);

        // Add checkboxes to the form.
        foreach ($checkboxes as $value => $label) {
            $mform->addElement('advcheckbox', $value, '', $label, ['group' => 'checkboxgroup custom_switch']);
        }    
    } else {
        $mform->addElement('static', 'notopics', '', get_string('notopics', 'local_courses'), array('class' => 'notopicselement'));
    }
}

/**
 * Build paths for each section based on hierarchical data.
 *
 * @param array $sections
 * @return array
 */
protected function build_section_paths($sections) {
    $paths = [];

    // Helper function to recursively build paths.
    $build_path = function($section, $sections, $current_path = '') use (&$build_path, &$paths) {
        $section_name = $section->name ?: "Topic " . $section->section;
        $full_path = $current_path ? $current_path . ' / ' . $section_name : $section_name;

        // Check if the section has any children.
        $has_children = false;
        foreach ($sections as $s) {
            if (isset($s->parent) && $s->parent == $section->section) {
                $has_children = true;
                $build_path($s, $sections, $full_path);
            }
        }
        // Add only if it has no children (leaf node) or has children but we want a checkbox for it too.
        if (!$has_children) {
            $paths[$section->id] = $full_path;
        }
    };

    // Start building paths from the top-level sections.
    foreach ($sections as $section) {
        if (empty($section->parent) && $section->section > 0) { // No parent means it's a top-level section.
            $build_path($section, $sections);
        }
    }
    return $paths;
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
        api::insert_topicsconfig_instance($data);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        if ($courseid) {
            $existingtopics = $DB->get_records('local_coursetopics', ['courseid' => $courseid], '', 'topic');

            $data = new stdClass();
            foreach ($existingtopics as $topic => $record) {
                $data->{$topic} = 1;
            }
            $this->set_data($data);
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
            ['action' => 'topicsconfiguration', 'id' => $id]);
    }
}
