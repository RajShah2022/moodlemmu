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
 * Callback implementations for shinkan
 *
 * @package    quizaccess_shinkan
 * @copyright  2023 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 function api_call($api_url,$params){
    global $USER;
    
    $token_id = base64_encode(get_config('quizaccess_shinkan', 'token_id'));      
    $owner_id = $USER->id;
    $event_id = $params['quizid'];

    $curl = curl_init();   

    $curl_url = '' . $api_url . '?' . 'token_id=' . $token_id . '&owner_id=' . $owner_id . '&event_id=' . $event_id . '';
    curl_setopt_array($curl, array(
        CURLOPT_URL => $curl_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => 'POST',

    ));

    $response = curl_exec($curl);
 
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