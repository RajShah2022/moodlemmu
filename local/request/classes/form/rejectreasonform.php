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

namespace local_request\form;
defined('MOODLE_INTERNAL') || die;

use moodle_url;
use context;
use core;
use moodleform;
use context_system;
use stdClass;

require_once("$CFG->libdir/formslib.php");
require_once($CFG->libdir . '/completionlib.php');

class rejectreasonform extends moodleform {
    public function definition() {
        global $CFG;
        $mform = $this->_form;

        $componentname = $this->_customdata['componentname'];
        $component = $this->_customdata['component'];
        $requesteduser = $this->_customdata['requesteduser'];
        $groupdifficultylevel = $this->_customdata['groupdifficultylevel'];
        $coursename = $this->_customdata['coursename'];
        $coursedifficultylevel = $this->_customdata['coursedifficultylevel'];

        $denymsg = new stdClass();
        $denymsg->groupname = $componentname;
        $denymsg->groupdifficultylevel = $groupdifficultylevel;
        $denymsg->coursename = $coursename;
        $denymsg->coursedifficultylevel = $coursedifficultylevel;
        $denymsg->requesteduser = $requesteduser;
        if($component == 'Classes') {
            $mform->addElement('static', 'statictext', '', get_string('groupsconfirmmsgfor_deny', 'local_request', $denymsg));
        } else {
            $mform->addElement('static', 'statictext', '', get_string('confirmmsgfor_deny', 'local_request', $requesteduser));
        }

        $mform->addElement('textarea', 'reason', get_string('reason', 'local_request'), 'wrap="virtual" rows="2" cols="40"');
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');

        $mform->disable_form_change_checker();
    }

    function validation($data, $files) {
        // print_r($data)
        $errors = parent::validation($data, $files);

        if (strlen($data['reason']) < 10) {
            $errors['reason'] = get_string('reasontooshort', 'local_request');
        }
        return $errors;
    }
}
