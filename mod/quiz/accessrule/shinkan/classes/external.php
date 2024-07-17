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
 * Extrarnal for the quizaccess_shinkan plugin.
 *
 * @package   quizaccess_shinkan
 * @copyright 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/externallib.php');

class quizaccess_shinkan_external extends external_api {

    /**
     * Set the cam shots parameters.
     *
     * @return external_function_parameters
     */
    public static function shinkan_api_call_parameters () {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'camshot course id'),
                'quizid' => new external_value(PARAM_INT, 'camshot quiz id'),
                'userid' => new external_value(PARAM_INT, 'camshot user id')
            )
        );
    }

    /**
     * Get the cam shots as service.
     *
     * @param mixed $courseid course id.
     * @param mixed $quizid context/quiz id.
     * @param mixed $userid user id.
     *
     * @return array
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public static function shinkan_api_call($courseid, $quizid, $userid) {
        global $CFG, $USER;
        require_once($CFG->dirroot.'/mod/quiz/accessrule/shinkan/lib.php');
        $params = array(
            'courseid' => $courseid,
            'quizid' => $quizid,
            'userid' => $userid
        );

        // Validate the params.
        self::validate_parameters(self::shinkan_api_call_parameters(), $params);

        $context = context_module::instance($params['quizid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        $api_url = get_config('quizaccess_shinkan', 'startrecord_url');  
        $response = api_call($api_url,$params);
        print_R($response);die;
        
        return $response;
    }

    /**
     * Cam shot return parameters.
     *
     * @return external_single_structure
     */
    public static function shinkan_api_call_returns() {
        return new external_value(PARAM_RAW, 'response');
    }


}
