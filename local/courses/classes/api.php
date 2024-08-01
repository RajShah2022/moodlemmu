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
 * Class api
 *
 * @package   local_courses
 * @copyright 2024 Moodle India Information Solutions Pvt Ltd
 * @author    2024 Rizwana <rizwana.madire@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courses;

use stdClass;
// use local_courses\persistent\topicsconfig;
// use local_courses\persistent\groupsconfig;

defined('MOODLE_INTERNAL') || die();

class api {
    public const COURSETOPICS_TABLE = 'local_coursetopics';
     /**
     * Insert or update course configured topics instance
     *
     * @param stdClass $data
     */
    public static function insert_instance($data) {
        global $DB, $USER;

        try {
            $courseid = $data->courseid;
            $existingtopics = $DB->get_records(self::COURSETOPICS_TABLE, ['courseid' => $courseid]);

            // Prepare an array to track existing topic IDs.
            $existingtopicids = [];
            foreach ($existingtopics as $topic) {
                $existingtopicids[$topic->topic] = $topic->id;
            }

            // Insert or update checked topics.
            foreach ($data as $key => $value) {
                if (is_numeric($key) && $value == 1) {
                    if (!isset($existingtopicids[$key])) {
                        // Insert new record for checked topic.
                        $record = new stdClass();
                        $record->courseid = $courseid;
                        $record->topic = $key;
                        $record->timecreated = time();
                        $record->usercreated = $USER->id;
                        $DB->insert_record(self::COURSETOPICS_TABLE, $record);
                    }
                    // Remove processed topic from the existing list.
                    unset($existingtopicids[$key]);
                }
            }

            // Delete unchecked topics that were previously checked.
            foreach ($existingtopicids as $topicid) {
                $DB->delete_records(self::COURSETOPICS_TABLE, ['id' => $topicid]);
            }
        } catch (\Exception $e) {
            throw new \moodle_exception("Error in topics configuring");
        }
    }

    // /**
    //  * Insert course configured topics instance
    //  *
    //  * @param  [object] $data
    //  *
    //  * @return [object]
    //  */
    // public static function insert_instance($data) {
    //     global $DB, $USER;
    //     print_r($data);
    //     try {
    //         $data->courseid = $data->courseid;
    //         $data->usercreated = $USER->id;
    //         $data->timecreated = time();
    //         $configuredtopics = new topicsconfig(0, $data);
    //         $configuredtopics->create();
    //         // return true;
    //     } catch (\Exception $e) {
    //         throw new \moodle_exception("Error in topics configuring");
    //     }
    // }

    // /**
    //  * Update ID configured course topics instance
    //  *
    //  * @param  [object] $data
    //  *
    //  * @return [object]
    //  */
    // public static function update_instance($data) {
    //     global $DB;
    //     try {
    //         if ($data->id) {
    //             $configuredtopics = new topicsconfig($data->id);
    //             $configuredtopics->set('courseid', $data->courseid);
    //             $configuredtopics->update();
    //         }
    //     } catch (\Exception $e) {
    //         throw new \moodle_exception("Error in topics configuring update");
    //     }
    // }
}
