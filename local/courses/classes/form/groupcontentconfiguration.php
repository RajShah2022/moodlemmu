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

class groupcontentconfiguration extends dynamic_form {

    /**
     * Define the form
     */
    public function definition() {
        global $DB, $USER;

        $mform = $this->_form;
        $this->_form->_attributes['class'] = 'groupcontentconfiguration_form';

        $id = $this->optional_param('id', 0, PARAM_INT);
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $groupid = $this->optional_param('groupid', 0, PARAM_INT);

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'groupid', $groupid);
        $mform->setType('groupid', PARAM_INT);

        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        
        $user_can_edit = false;
        if(has_capability('local/courses:canconfiguregroupcontent', $categorycontext)) {
            $user_can_edit = true;
            $params['courseid'] = $courseid;
            $params['open_path'] = $USER->open_path;
            $existingtopics = $DB->get_records('local_coursetopics', $params, '', 'topic');
            foreach($existingtopics as $existingtopic) {
                $sectionnumber = $DB->get_field('course_sections', 'section', array('id' =>  $existingtopic->topic));
                $allsections[] = $format->get_modinfo()->get_section_info($sectionnumber);
            }
        

            if(!empty($allsections)) {
                // Build the checkbox options.
                $checkboxes = $this->build_section_paths($allsections);

                // Add checkboxes to the form.
                foreach ($checkboxes as $value => $label) {
                    $parent = $this->get_parent_id($allsections, $value);
                    $attributes = ['group' => 'checkboxgroup custom_switch'];
                    if (!$user_can_edit) {
                        $attributes['disabled'] = 'disabled';
                    }
                     // Only add data-parent if the section has a parent.
                    if ($parent !== null) {
                        $attributes['data-parent'] = 'section_' . $parent;
                    }
                    $sectionid = 'section_' . $value;
                    $mform->addElement('advcheckbox', $sectionid, '', $label, $attributes);
                    // Add a help button if the checkbox is a child.
                    if ($parent !== null) {
                        $mform->addHelpButton($sectionid, 'childcheckboxhelp', 'local_courses');
                    }
                    $activities = $modinfo->get_cms();
                    foreach ($activities as $cm) {
                        if ($cm->section == $value) {
                            $attributes['data-parent'] = 'section_' . $value;
                            $activityname = $cm->name;
                            $activityid = 'activity_' . $cm->id;
                            $attributes['class'] = 'ml-1';
                            $mform->addElement('advcheckbox', $activityid, '', $activityname, $attributes);
                            $mform->setType($activityid, PARAM_INT);
                        }
                    }
                }    
            } else {
                $mform->addElement('static', 'notopics', '', get_string('notopics', 'local_courses'), array('class' => 'notopicselement'));
            }
        }
    }

    protected function get_parent_id($sections, $sectionid) {
        foreach ($sections as $section) {
            if ($section->id == $sectionid && !empty($section->parent)) {
                // Find the parent section based on the parent ID.
                foreach ($sections as $parentSection) {
                    if ($parentSection->section == $section->parent) {
                        return $parentSection->id;
                    }
                }
            }
        }
        return null;
    }
    protected function build_section_paths($sections) {
        $paths = [];

        // Helper function to recursively build paths.
        $build_path = function($section, $sections, $current_path = '') use (&$build_path, &$paths) {
            $section_name = $section->name ?: "Topic " . $section->section;
            $full_path = $current_path ? $current_path . ' / ' . $section_name : $section_name;

            // Add the current section to paths.
            $paths[$section->id] = $full_path;

            // Check for children and build their paths.
            foreach ($sections as $s) {
                if (isset($s->parent) && $s->parent == $section->section) {
                    $build_path($s, $sections, $full_path);
                }
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
        $data = $this->get_data();
        api::insert_groupcontentconfig_instance($data);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB, $USER;
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $groupid = $this->optional_param('groupid', 0, PARAM_INT);
        if ($courseid && $groupid) {
            $params['courseid'] = $courseid;
            $params['groupid'] = $groupid;
            if(!is_siteadmin() && !api::is_administrator()){
                $params['open_path'] = $USER->open_path;
            }
            $existingtopics = $DB->get_records('local_coursegrouptopics', $params, '', 'topic');

            $data = new stdClass();
            foreach ($existingtopics as $topic => $record) {
                $data->{'section_' . $topic} = 1;
                if($DB->record_exists('local_coursegroupmodules', array('topicid' => $topic))){
                    // Load existing activities for this section.
                    $params['topicid'] = $topic;
                    $existingmodules = $DB->get_records('local_coursegroupmodules', $params, '', 'moduleid');
                    foreach ($existingmodules as $moduleid => $record) {
                        $data->{'activity_' . $moduleid} = 1;
                    }
                }
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
        $id = $this->optional_param('courseid', 0, PARAM_INT);
        return new moodle_url('/course/view.php',
            ['action' => 'groupscontentconfiguration', 'id' => $id]);
    }
}
