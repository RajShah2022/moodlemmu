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

require_once($CFG->dirroot . '/course/lib.php');

class api {
    public const COURSETOPICS_TABLE = 'local_coursetopics';
    public const COURSEGROUPS_TABLE = 'local_coursegroups';
    public const ENROLLMENT_REQUESTS_TABLE = 'local_request_records';
    public const ENROLL_TABLE = 'enrol';
    public const COURSESECTIONS_TABLE = 'course_sections';
     /**
     * Insert or update course configured topics instance
     *
     * @param stdClass $data
     */
    public static function insert_topicsconfig_instance($data) {
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
            self::course_content_accessrestrictions($courseid);
        } catch (\Exception $e) {
            throw new \moodle_exception("Error in topics configuring");
        }
    }
    /**
     * Insert or update course configured groups instance
     *
     * @param stdClass $data
     */
    public static function insert_groupsconfig_instance($data) {
        global $DB, $USER;
        try {
            $courseid = $data->courseid;
            $existinggroups = $DB->get_records(self::COURSEGROUPS_TABLE, ['courseid' => $courseid]);

            // Prepare an array to track existing groups IDs.
            $existinggroupids = [];
            foreach ($existinggroups as $group) {
                $existinggroupids[$group->groupid] = $group->id;
            }

            // Insert or update checked groups.
            foreach ($data as $key => $value) {
                if (is_numeric($key) && $value == 1) {
                    if (!isset($existinggroupids[$key])) {
                        // Insert new record for checked groups.
                        $record = new stdClass();
                        $record->courseid = $courseid;
                        $record->groupid = $key;
                        $record->timecreated = time();
                        $record->usercreated = $USER->id;
                        $DB->insert_record(self::COURSEGROUPS_TABLE, $record);
                    }
                    // Remove processed topic from the existing list.
                    unset($existinggroupids[$key]);
                }
            }
            // Delete unchecked groups that were previously checked.
            foreach ($existinggroupids as $groupid => $recordid) {
                $sql = "componentid = :componentid AND courseid = :courseid AND status = :pending";
                $params = [
                    'componentid' => $groupid,
                    'courseid' => $courseid,
                    'pending' => 'PENDING',
                ];
                // Delete the records with matching componentid, courseid, and status.
                $DB->delete_records_select(self::ENROLLMENT_REQUESTS_TABLE, $sql, $params);
                self::unenrollgroups($groupid, $courseid);
                // Also delete the unchecked group from COURSEGROUPS_TABLE
                $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $recordid]);
            }
            self::course_content_accessrestrictions($courseid);
        } catch (\Exception $e) {
            throw new \moodle_exception("Error in classes enrollment");
        }
    }

    public static function course_content_accessrestrictions($courseid) {
        global $DB, $PAGE;
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Get all sections from the COURSESECTIONS_TABLE for the given course
        $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

        // Get all topics linked to course sections in the COURSETOPICS_TABLE
        $linkedTopics = $DB->get_records_menu(self::COURSETOPICS_TABLE, null, '', 'topic, id');

        // Query to get cohort sync list
        $cohortsync = "SELECT DISTINCT e.id, e.customint1, e.customint2
                       FROM {" . self::ENROLL_TABLE . "} e
                       JOIN {" . self::COURSEGROUPS_TABLE . "} cg
                         ON e.customint1 = cg.groupid
                       WHERE e.courseid = :courseid AND e.enrol = :enrol";
        $cohortparams = array('courseid' => $courseid, 'enrol' => 'cohort');
        $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);

        // Collect all group IDs (customint2) from cohort sync list
        $groupIds = array_map(function($cohort) {
            return $cohort->customint2;
        }, $cohortsynclist);

        // Check if there are any group IDs
        if (!empty($groupIds)) {
            // Create new conditions with the group IDs
            $newConditions = array_values(array_map(function($groupId) {
                return array("type" => "group", "id" => $groupId);
            }, $groupIds));

            // Iterate over all sections
            foreach ($allSections as $section) {
                $params = ['id' => $section->id, 'sr' => 0];
                if ($section->section > 0) {
                    $sectiondata = new stdClass();
                    $sectiondata->id = $section->id;

                    // If the section is not linked to any topic, add restrictions
                    if (!array_key_exists($section->id, $linkedTopics)) {
                        // // Decode the existing availability JSON
                        // $existingAvailability = json_decode($section->availability, true);

                        // // Initialize conditions array
                        // $conditions = $newConditions;
                        // if ($existingAvailability && isset($existingAvailability['c'])) {
                        //     // Extract existing conditions
                        //     $existingConditions = $existingAvailability['c'];

                        //     // Check if there are changes to be made
                        //     $mergedConditions = array_merge($existingConditions, $newConditions);

                        //     // Remove duplicate conditions
                        //     $mergedConditions = array_unique($mergedConditions, SORT_REGULAR);

                        //     // Update conditions only if there's a change
                        //     if ($existingConditions != $mergedConditions) {
                        //         $conditions = $mergedConditions;
                        //     } else {
                        //         // No change, skip to next section
                        //         continue;
                        //     }
                        // }

                        // Decode the existing availability JSON
                        $existingAvailability = json_decode($section->availability, true);

                        // Initialize existing conditions
                        $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];

                        // Merge new conditions with existing conditions
                        $allConditions = array_merge($existingConditions, $newConditions);

                        // Remove duplicate conditions
                        $uniqueConditions = array_values(array_unique($allConditions, SORT_REGULAR));
                        // Create a new availability structure
                        $updatedAvailability = array(
                            "op" => "|",
                            "c" => $uniqueConditions,
                            "show" => false
                        );

                        // Convert to JSON
                        $availabilityJson = json_encode($updatedAvailability);

                        $sectiondata->availability = $availabilityJson;
                    } 
                    course_update_section($course, $section, $sectiondata);
                }
            }
        }
    }

    // public static function course_content_accessrestrictions($courseid) {
    //     global $DB;
    //     $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    //     // Query to get access topics list
    //     // $accesstopics = "SELECT DISTINCT cs.*
    //     //                  FROM {" . self::COURSESECTIONS_TABLE . "} cs
    //     //                  JOIN {" . self::COURSETOPICS_TABLE . "} ct
    //     //                    ON cs.id = ct.topic
    //     //                  WHERE cs.course = :courseid";
    //     $accesstopics = "SELECT DISTINCT cs.*
    //                  FROM {" . self::COURSESECTIONS_TABLE . "} cs
    //                  LEFT JOIN {" . self::COURSETOPICS_TABLE . "} ct
    //                    ON cs.id = ct.topic
    //                  WHERE cs.course = :courseid AND ct.topic IS NULL";
    //     $topicsparams = array('courseid' => $courseid);
    //     $accesstopicslist = $DB->get_records_sql($accesstopics, $topicsparams);
    //     // Query to get cohort sync list
    //     $cohortsync = "SELECT DISTINCT e.id, e.customint1, e.customint2
    //                    FROM {" . self::ENROLL_TABLE . "} e
    //                    JOIN {" . self::COURSEGROUPS_TABLE . "} cg
    //                      ON e.customint1 = cg.groupid
    //                    WHERE e.courseid = :courseid AND e.enrol = :enrol";
    //     $cohortparams = array('courseid' => $courseid, 'enrol' => 'cohort');
    //     $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);
        
    //     if (!empty($accesstopicslist) && !empty($cohortsynclist)) {
    //         // Collect all group IDs (customint2) from cohort sync list
    //         $groupIds = array_map(function($cohort) {
    //             return $cohort->customint2;
    //         }, $cohortsynclist);

    //         // Prepare the availability JSON structure
    //         // $availability = array(
    //         //     "op" => "|",
    //         //     "c" => array_map(function($groupId) {
    //         //         return array("type" => "group", "id" => $groupId);
    //         //     }, $groupIds),
    //         //     "show" => false
    //         // );
    //         // Convert to JSON
    //         // $availabilityJson = json_encode($availability);


    //         // Prepare new group conditions
    //         $newConditions = array_map(function($groupId) {
    //             return array("type" => "group", "id" => $groupId);
    //         }, $groupIds);

            

    //         // Update each section with the availability JSON
    //         foreach ($accesstopicslist as $section) {
    //             // $DB->update_record(self::COURSESECTIONS_TABLE, array(
    //             //     'id' => $section->id,
    //             //     'availability' => $availabilityJson
    //             // ));
    //             if($section->section > 0) {
    //                 $sectiondata = new stdClass();
    //                 $sectiondata->id = $section->id;

    //                 // Decode the existing availability JSON
    //                 $existingAvailability = json_decode($section->availability, true);

    //                 if ($existingAvailability && isset($existingAvailability['c'])) {
    //                     // Append new group conditions to existing conditions
    //                     $existingAvailability = array(
    //                         "op" => "|",
    //                         "c" => array_merge($existingAvailability['c'], $newConditions),
    //                         "show" => false
    //                     );
    //                 // $existingAvailability['c'] = array_merge($existingAvailability['c'], $newConditions);
    //                 } else {
    //                     // Create a new availability structure if none exists
    //                     $existingAvailability = array(
    //                         "op" => "|",
    //                         "c" => $newConditions,
    //                         "show" => false
    //                     );
    //                 }
                    
    //                 // Convert back to JSON
    //                 $availabilityJson = json_encode($existingAvailability);

    //                 $sectiondata->availability = $availabilityJson;

    //                 course_update_section($course, $section, $sectiondata); 
    //             }
    //         }
    //     }
    // }

    // /**
    //  * Insert or update course configured groups instance
    //  *
    //  * @param stdClass $data
    //  */
    // public static function insert_groupsconfig_instance($data) {
    //     global $DB, $USER;
    //     try {
    //         $courseid = $data->courseid;
    //         $existinggroups = $DB->get_records(self::COURSEGROUPS_TABLE, ['courseid' => $courseid]);

    //         // Prepare an array to track existing group IDs.
    //         $existinggroupsids = [];
    //         foreach ($existinggroups as $group) {
    //             $existinggroupsids[$group->group] = $group->id;
    //         }

    //         // Insert or update checked groups.
    //         foreach ($data as $key => $value) {
    //             if (is_numeric($key) && $value == 1) {
    //                 if (!isset($existinggroupsids[$key])) {
    //                     // Insert new record for checked group.
    //                     $record = new stdClass();
    //                     $record->courseid = $courseid;
    //                     $record->group = $key;
    //                     $record->timecreated = time();
    //                     $record->usercreated = $USER->id;
    //                     print_r($record);
    //                     $insertid = $DB->insert_record(self::COURSEGROUPS_TABLE, $record);
    //                     print_r($insertid);
    //                 }
    //                 // Remove processed group from the existing list.
    //                 unset($existinggroupsids[$key]);
    //             }
    //         }
    //         // Delete unchecked topics that were previously checked.
    //         foreach ($existinggroupsids as $groupid) {
    //             $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $groupid]);
    //         }
    //     } catch (\Exception $e) {
    //         throw new \moodle_exception("Error in groups configuring");
    //     }
    // }

    public static function get_hod_roleid() {
        global $DB;
        return $DB->get_field('role', 'id', array('shortname' => 'hod'));
    }

    public static function get_hodcourse_roleid() {
        global $DB;
        return $DB->get_field('role', 'id', array('shortname' => 'hod_course'));
    }

    public static function is_hod() {
        global $USER;
        $current_roleid = $USER->useraccess['currentroleinfo']['roleid'];
        
        if(!empty($current_roleid) && $current_roleid == self::get_hod_roleid()) {
             return true;           
        } else {
            return false;
        }
    }

    public static function enrollgroups($course, $fields = null) {
        $chohortplugin = enrol_get_plugin('cohort');
        $enrolid = $chohortplugin->add_instance($course, $fields);
        return $enrolid;
    }

    public static function unenrollgroups($groupid, $courseid, $fields = null) {
        global $DB;
        $instances = enrol_get_instances($courseid, false);
        $chohortplugin = enrol_get_plugin('cohort');

        $params = [
            'customint1' => $groupid,
            'courseid' => $courseid,
            'enrol' => 'cohort',
        ];
        $coursecohortslist = $DB->get_records('enrol', $params);
        if(!empty($coursecohortslist)) {
            foreach($coursecohortslist as $coursecohort) {
                $chohortplugin->update_communication($coursecohort->id, 'remove', $courseid);
                $instance = $instances[$coursecohort->id];
                $chohortplugin->delete_instance($instance);
            }  
        }        
    }
}
