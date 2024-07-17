<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Local Users plugin edit form that allow user to edit profile for required custom profile fields
 *
 * @copyright MoodleIndia
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package local_users
 */


namespace local_users\forms;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/lib/formslib.php');

use moodleform;

class edit_user extends moodleform {
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '',
     $attributes = null, $editable = true, $formdata = null) {

        $this->formstatus = array(
            'generaldetails' => get_string('generaldetails', 'local_users'),
            'otherdetails' => get_string('otherdetails', 'local_users'),
            'contactdetails' => get_string('contactdetails', 'local_users'),
            );
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }
    public function definition() {
        global $COURSE;

        $mform = $this->_form;
        
        if (!is_array($this->_customdata)) {
            throw new \coding_exception('invalid custom data for user_edit_form');
        }
        $user = $this->_customdata['user'];
        $userid = $user->id;

        // Accessibility: "Required" is bad legend text.
        $strrequired = get_string('required');

        // Add some extra hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'course', $COURSE->id);
        $mform->setType('course', PARAM_INT);

        // Print the required moodle fields first.
        $mform->addElement('header', 'moodle', $strrequired);

        // Next the customisable profile fields.
        profile_definition($mform, $userid, 0);

        $this->add_action_buttons(true, get_string('updatemyprofile'));

        $this->set_data($user);

    }
    
    /**
     * Extend the form definition after the data has been parsed.
     */
    public function definition_after_data() {
        global $DB;

        $mform = $this->_form;
        $userid = $mform->getElementValue('id');

        // Trim required name fields.
        foreach (useredit_get_required_name_fields() as $field) {
            $mform->applyFilter($field, 'trim');
        }

        if ($user = $DB->get_record('user', array('id' => $userid))) {

            // Disable fields that are locked by auth plugins.
            $fields = get_user_fieldnames();
            $authplugin = get_auth_plugin($user->auth);
            $customfields = $authplugin->get_custom_user_profile_fields();
            $customfieldsdata = profile_user_record($userid, false);
            $fields = array_merge($fields, $customfields);
            foreach ($fields as $field) {
                if ($field === 'description') {
                    // Hard coded hack for description field. See MDL-37704 for details.
                    $formfield = 'description_editor';
                } else {
                    $formfield = $field;
                }
                if (!$mform->elementExists($formfield)) {
                    continue;
                }

                // Get the original value for the field.
                if (in_array($field, $customfields)) {
                    $key = str_replace('profile_field_', '', $field);
                    $value = isset($customfieldsdata->{$key}) ? $customfieldsdata->{$key} : '';
                } else {
                    $value = $user->{$field};
                }

                $configvariable = 'field_lock_' . $field;
                if (isset($authplugin->config->{$configvariable})) {
                    if ($authplugin->config->{$configvariable} === 'locked') {
                        $mform->hardFreeze($formfield);
                        $mform->setConstant($formfield, $value);
                    } else if ($authplugin->config->{$configvariable} === 'unlockedifempty' and $value != '') {
                        $mform->hardFreeze($formfield);
                        $mform->setConstant($formfield, $value);
                    }
                }
            }

            // Next the customisable profile fields.
            profile_definition_after_data($mform, $user->id);

        } else {
            profile_definition_after_data($mform, 0);
        }
    }


    public function validation($usernew, $files) {
     
        $errors = parent::validation($usernew, $files);

        $usernew = (object)$usernew;
       
        // Next the customisable profile fields.
        $errors = profile_validation($usernew, $files);

        return $errors;
    }

       
}

