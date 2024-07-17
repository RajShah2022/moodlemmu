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
 * Autocomplete profile field
 *
 * @package    profilefield_autocomplete
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_autocomplete extends profile_field_base {
    /** @var array $options */
    public $options;

    /** @var int $datakey */
    public $datakey;

    /** @var bool $multiple */
    public $multiple;

    /**
     * Constructor method.
     *
     * Pulls out the options for the menu from the database and sets the the corresponding key for the data if it exists.
     *
     * @param int $fieldid
     * @param int $userid
     * @param object $fielddata
     * @throws coding_exception
     * @throws dml_exception
     */
    public function __construct($fieldid = 0, $userid = 0, $fielddata = null) {
        // First call parent constructor.
        parent::__construct($fieldid, $userid, $fielddata);

        // Param 1 for menu type is the options.
        if (isset($this->field->param1)) {
            $options = explode("\n", $this->field->param1);
        } else {
            $options = array();
        }
        $this->options = array();
        if (!empty($this->field->required)) {
            $this->options[''] = get_string('choose') . '...';
        }
        foreach ($options as $key => $option) {
            // Multilang formatting with filters.
            $this->options[$option] = format_string($option, true, ['context' => context_system::instance()]);
        }

        if (isset($this->field->param2)) {
            $this->multiple = $this->field->param2 == 1;
        }

        // Set the data key.
        if ($this->data !== null) {
            $this->datakey = explode(', ', $this->data);
        }
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     *
     * @param moodleform $mform Moodle form instance
     */
    public function edit_field_add($mform) {
        $autocomplete = $mform->addElement('autocomplete', $this->inputname, format_string($this->field->name), $this->options, [
            'multiple' => $this->multiple
        ]);
        $autocomplete->setValue('');
    }

    /**
     * Set the default value for this field instance
     * Overwrites the base class method.
     *
     * @param moodleform $mform Moodle form instance
     */
    public function edit_field_set_default($mform) {
        $key = $this->field->defaultdata;

        if (isset($this->options[$key]) || ($key = array_search($key, $this->options)) !== false) {
            $defaultkey = $key;
        } else {
            $defaultkey = '';
        }
        $mform->setDefault($this->inputname, $defaultkey);
    }

    /**
     * The data from the form returns the key.
     *
     * This should be converted to the respective option string to be saved in database
     * Overwrites base class accessor method.
     *
     * @param mixed $data The key returned from the select input in the form
     * @param stdClass $datarecord The object that will be used to save the record
     * @return mixed Data or null
     */
    public function edit_save_data_preprocess($data, $datarecord) {

        // If the field is not multiple, create an array with the unique value.
        if (!is_array($data)) {
            $data = array($data);
        }

        // Check if all options are valid.
        foreach ($data as $option) {
            if (!isset($this->options[$option])) {
                return null;
            }
        }

        // Convert values into string to store it in database.
        return implode(', ', $data);
    }

    /**
     * When passing the user object to the form class for the edit profile page
     * we should load the key for the saved data
     *
     * Overwrites the base class method.
     *
     * @param stdClass $user User object.
     */
    public function edit_load_user_data($user) {
        $user->{$this->inputname} = $this->datakey;
    }

    /**
     * HardFreeze the field if locked.
     *
     * @param moodleform $mform instance of the moodleform class
     * @throws coding_exception
     * @throws dml_exception
     */
    public function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }

        if ($this->is_locked() and !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
            $mform->setConstant($this->inputname, format_string($this->datakey));
        }
    }

    /**
     * Convert external data (csv file) from value to key for processing later by edit_save_data_preprocess
     *
     * @param string|array $value one of the values in menu options.
     * @return int options key for the menu
     */
    public function convert_external_data($value) {

        if (is_array($value)) {
            $retval = [];
            foreach ($value as $item) {
                if (isset($this->options[$item])) {
                    $retval[] = $item;
                } else if ($itemsearch = array_search($item, $this->options)) {
                    $retval[] = $itemsearch;
                }
            }
            $retval = !empty($retval) ? $retval : false;
        } else {
            if (isset($this->options[$value])) {
                $retval = $value;
            } else {
                $retval = array_search($value, $this->options);
            }
        }

        // If value is not found in options then return null, so that it can be handled
        // later by edit_save_data_preprocess.
        if ($retval === false) {
            $retval = null;
        }
        return $retval;
    }

    /**
     * Return the field type and null properties.
     * This will be used for validating the data submitted by a user.
     *
     * @return array the param type and null property
     * @since Moodle 3.2
     */
    public function get_field_properties() {
        return array(PARAM_TEXT, NULL_NOT_ALLOWED);
    }
}
