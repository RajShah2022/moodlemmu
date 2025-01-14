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
 * TODO describe module proctoring
 *
 * @module     quizaccess_shinkan/proctoring
 * @copyright  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
let isCameraAllowed = false;

define(['jquery', 'core/ajax', 'core/notification'],
    function($, Ajax, Notification) {
           
        return {
            async setup(props) {               
                // eslint-disable-next-line promise/catch-or-return
                var wsfunction = 'quizaccess_shinkan_api_call';
                var params = {
                    'courseid': props.courseid,
                    'quizid': props.quizid, 
                    'userid': props.userid,                  
                };
                var request = {
                    methodname: wsfunction,
                    args: params
                };

                Ajax.call([request])[0].done(function(res) {
                    if (res) {
                        // NO
                    } else {
                       
                        Notification.addNotification({
                            message: 'Something went wrong .',
                            type: 'error'
                        });
                       
                    }
                }).fail(Notification.exception);
            } 
        };

        
    });
