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
 * Implementaton of the quizaccess_shinkan plugin.
 *
 * @package   quizaccess_shinkan
 * @copyright 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


class quizaccess_shinkan extends quiz_access_rule_base
{

    public function is_preflight_check_required($attemptid)
    {
        if (!empty($attemptid)) {
           $this->notify_preflight_check_passed($attemptid);
        } else{
            return empty($attemptid);
        }        
    } 
  

    public function add_preflight_check_form_fields(mod_quiz_preflight_check_form $quizform, MoodleQuickForm $mform,$attemptid) {

        $mform->addElement(
            'static',
            'proctoringcheckmessage',
            '',
            get_string('proctoringcheckstatement', 'quizaccess_shinkan')
        );
        $mform->addElement(
            'checkbox',
            'proctoringcheck',
            '',
            get_string('proctoringchecklabel', 'quizaccess_shinkan')
        ); 
        
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid)
    {
        if (empty($data['proctoringcheck'])) {
            $errors['proctoringcheck'] = get_string('pleaseselect', 'quizaccess_shinkan');
        } 
        return $errors;
    }

    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     *
     * @param quiz $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *      time limits by the mod/quiz:ignoretimelimits capability.
     * @return quiz_access_rule_base|null the rule, if applicable, else null.
     */
    public static function make(quiz $quizobj, $timenow, $canignoretimelimits)
    {
        if (empty($quizobj->get_quiz()->proctoringrequired)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }


    /**
     * Add any fields that this rule requires to the quiz settings form. This
     * method is called from {@link mod_quiz_mod_form::definition()}, while the
     * security section is being built.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform)
    {
        $mform->addElement(
            'select',
            'proctoringrequired',
            get_string('proctoringrequired', 'quizaccess_shinkan'),
            [
                0 => get_string('notrequired', 'quizaccess_shinkan'),
                1 => get_string('proctoringrequiredoption', 'quizaccess_shinkan'),
            ]
        );
        $mform->addHelpButton('proctoringrequired', 'proctoringrequired', 'quizaccess_shinkan');
    }



    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from {@link quiz_after_add_or_update()} in lib.php.
     *
     * @param object $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz)
    {
        global $DB;
        if (empty($quiz->proctoringrequired)) {
            $DB->delete_records('quizaccess_shinkan', array('quizid' => $quiz->id));
        } else {
            if (!$DB->record_exists('quizaccess_shinkan', array('quizid' => $quiz->id))) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->proctoringrequired = 1;
                $DB->insert_record('quizaccess_shinkan', $record);
            }
        }
    }

    /**
     * Delete any rule-specific settings when the quiz is deleted. This is called
     * from {@link quiz_delete_instance()} in lib.php.
     *
     * @param object $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     */
    public static function delete_settings($quiz)
    {
        global $DB;
        $DB->delete_records('quizaccess_shinkan', array('quizid' => $quiz->id));
    }


    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query. The easiest way to understand what you need to do
     * here is probalby to read the code of {@link quiz_access_manager::load_settings()}.
     *
     * If you have some settings that cannot be loaded in this way, then you can
     * use the {@link get_extra_settings()} method instead, but that has
     * performance implications.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid)
    {
        return array(
            'shinkan.proctoringrequired',
            'LEFT JOIN {quizaccess_shinkan} shinkan ON shinkan.quizid = quiz.id',
            array()
        );
    }

 /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule.
     *
     * @param moodle_page $page the page object to initialise
     *
     * @throws coding_exception
     * @throws dml_exception
     */
  /*   public function setup_attempt_page($page) {
        
        $cmid = optional_param('cmid', '', PARAM_INT);
        $attemptid = optional_param('attempt', '', PARAM_INT);

        $page->set_title($this->quizobj->get_course()->shortname . ': ' . $page->title);
        $page->set_popup_notification_allowed(false); // Prevent message notifications.
        $page->set_heading($page->title);

        if ($cmid) {
            $attemptobj = quiz_create_attempt_handling_errors($attemptid, $cmid);
            $quiz = $attemptobj->get_quiz();
            $userid = $attemptobj->get_userid();
            $context = context_module::instance($quiz->cmid);
          
            $record = new stdClass();
            $record->courseid = $this->quizobj->get_course()->id;
            $record->quizid = $this->quiz->id;
            $record->userid =  $userid;           
            $record->token_id = base64_encode(get_config('quizaccess_shinkan', 'token_id'));
            $page->requires->js_call_amd('quizaccess_shinkan/proctoring', 'setup', [$record]);
        }
    } */

    /**
     * This is called on quiz start attempt
     * Send the start recording api url to api_call() and tracking the response to logs 
     *
     * @param 
     */
    public function notify_preflight_check_passed($attemptid)
    {
        $api_url = get_config('quizaccess_shinkan', 'startrecord_url');  
        $response = $this->api_call($api_url);
        $this->track_api_repsonse('start', $response); 
        return $response;
    } 

    /**
     * This is called when the current attempt at the quiz is finished.
     * Send the stoprecording api url to api_call() and tracking the response to logs
     */
    public function current_attempt_finished()
    {
        $api_url = get_config('quizaccess_shinkan', 'stoprecord_url');   
        $response = $this->api_call($api_url);
        $this->track_api_repsonse('end', $response);   
    }

    /**
     *  
     *
     * @param 
     */    

     public function api_call($api_url){
        global $USER;
        
        $token_id = base64_encode(get_config('quizaccess_shinkan', 'token_id'));      
        $owner_id = $USER->id;
        $event_id = $this->quiz->id;

        $curl = curl_init();   

        $curl_url = '' . $api_url . '?' . 'token_id=' . $token_id . '&owner_id=' . $owner_id . '&event_id=' . $event_id . '';
        curl_setopt_array($curl, array(
            CURLOPT_URL => $curl_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',

        ));

        $response = curl_exec($curl);
        echo $response;die;
        if($response === false)  
        {
            $responseJson = new stdClass(); 
            $responseJson->status =  "FAILED";
            $responseJson->message = 'Send error: ' . curl_error($curl);
            throw new Exception(curl_error($curl)); 
        }
        else{          
            curl_close($curl);
            return $response;
        }
   
    }


    /**
     *  Tracking the api response and saving to logs
     *
     * @param 
     */ 
    public function track_api_repsonse($type , $api_response){
        
        global $USER, $COURSE, $DB;
        $record = new stdClass();
        $record->quizid = $this->quiz->id;
        $record->userid = $USER->id;
        $record->courseid = $COURSE->id;
        // $record->status = json_encode($response);
        $record->type = $type;
        $record->response = $api_response;
        $record->timemodified = time();
        $DB->insert_record('quizaccess_shinkan_logs', $record);
    }
}
