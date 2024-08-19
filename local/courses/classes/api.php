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
require_once($CFG->dirroot . '/local/groups/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

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
    // public static function insert_topicsconfig_instance($data) {
    //     global $DB, $USER;

    //     try {
    //         $courseid = $data->courseid;
    //         $existingtopics = $DB->get_records(self::COURSETOPICS_TABLE, ['courseid' => $courseid]);

    //         // Prepare an array to track existing topic IDs.
    //         $existingtopicids = [];
    //         foreach ($existingtopics as $topic) {
    //             $existingtopicids[$topic->topic] = $topic->id;
    //         }

    //         // Insert or update checked topics.
    //         foreach ($data as $key => $value) {
    //             if (is_numeric($key) && $value == 1) {
    //                 if (!isset($existingtopicids[$key])) {
    //                     // Insert new record for checked topic.
    //                     $record = new stdClass();
    //                     $record->courseid = $courseid;
    //                     $record->topic = $key;
    //                     $record->open_path = $USER->open_path;
    //                     $record->timecreated = time();
    //                     $record->usercreated = $USER->id;
    //                     $DB->insert_record(self::COURSETOPICS_TABLE, $record);
    //                 }
    //                 // Remove processed topic from the existing list.
    //                 unset($existingtopicids[$key]);
    //             }
    //         }

    //         // Delete unchecked topics that were previously checked.
    //         foreach ($existingtopicids as $topicid) {
    //             $DB->delete_records(self::COURSETOPICS_TABLE, ['id' => $topicid]);
    //         }
    //         self::course_content_accessrestrictions($courseid);
    //     } catch (\Exception $e) {
    //         throw new \moodle_exception("Error in topics configuring");
    //     }
    // }

    public static function insert_topicsconfig_instance($data) {
        global $DB, $USER;

        try {
            $courseid = $data->courseid;
            $isadmin = is_siteadmin() || api::is_administrator();

            // Load all existing topics for the course.
            $alltopics = $DB->get_records(self::COURSETOPICS_TABLE, ['courseid' => $courseid]);

            // Prepare arrays to track topics by their open paths.
            $existingtopicids = [];
            foreach ($alltopics as $topic) {
                if (!isset($existingtopicids[$topic->topic])) {
                    $existingtopicids[$topic->topic] = [];
                }
                $existingtopicids[$topic->topic][$topic->open_path] = $topic->id;
            }

            // Track topics that are selected in the current form submission.
            $selectedtopics = [];

            // Insert or update checked topics.
            foreach ($data as $key => $value) {
                if (is_numeric($key) && $value == 1) {
                    $selectedtopics[$key] = true;

                    if (isset($existingtopicids[$key][$USER->open_path])) {
                        // Update existing record if it already exists for this path.
                        $record = new stdClass();
                        $record->id = $existingtopicids[$key][$USER->open_path];
                        $record->timecreated = time();
                        $DB->update_record(self::COURSETOPICS_TABLE, $record);

                        // Remove the topic from the list to be deleted.
                        unset($existingtopicids[$key][$USER->open_path]);
                    } else {
                        // Insert new record for a newly checked topic.
                        $record = new stdClass();
                        $record->courseid = $courseid;
                        $record->topic = $key;
                        $record->open_path = $USER->open_path;
                        $record->timecreated = time();
                        $record->usercreated = $USER->id;
                        $DB->insert_record(self::COURSETOPICS_TABLE, $record);
                    }
                }
            }

            if ($isadmin) {
                // Admin-specific: Delete unchecked topics across all open paths.
                foreach ($existingtopicids as $topicid => $paths) {
                    if (!isset($selectedtopics[$topicid])) {
                        // Delete all records for this topic if it is not selected by the admin.
                        foreach ($paths as $pathid) {
                            $DB->delete_records(self::COURSETOPICS_TABLE, ['id' => $pathid]);
                        }
                    }
                }
            } else {
                // Non-admins should only delete their unchecked topics for their path.
                foreach ($existingtopicids as $topicid => $paths) {
                    if (!isset($selectedtopics[$topicid]) && isset($paths[$USER->open_path])) {
                        $DB->delete_records(self::COURSETOPICS_TABLE, ['id' => $paths[$USER->open_path]]);
                    }
                }
            }

            self::course_content_accessrestrictions($courseid);
        } catch (\Exception $e) {
            throw new \moodle_exception("Error in topics configuring: " . $e->getMessage());
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
            $isadmin = is_siteadmin() || api::is_administrator();

            // Load all existing topics for the course.
            $allgroups = $DB->get_records(self::COURSEGROUPS_TABLE, ['courseid' => $courseid]);

            // Prepare arrays to track topics by their open paths.
            $existinggroupids = [];
            foreach ($allgroups as $group) {
                if (!isset($existinggroupids[$group->groupid])) {
                    $existinggroupids[$group->groupid] = [];
                }
                $existinggroupids[$group->groupid][$group->open_path] = $group->id;
            }

            // Track topics that are selected in the current form submission.
            $selectedgroups = [];

            // Insert or update checked topics.
            foreach ($data as $key => $value) {
                if (is_numeric($key) && $value == 1) {
                    $selectedgroups[$key] = true;

                    if (isset($existinggroupids[$key][$USER->open_path])) {
                        // Update existing record if it already exists for this path.
                        $record = new stdClass();
                        $record->id = $existinggroupids[$key][$USER->open_path];
                        $record->timecreated = time();
                        $DB->update_record(self::COURSEGROUPS_TABLE, $record);

                        // Remove the topic from the list to be deleted.
                        unset($existinggroupids[$key][$USER->open_path]);
                    } else {
                        // Insert new record for a newly checked topic.
                        $record = new stdClass();
                        $record->courseid = $courseid;
                        $record->groupid = $key;
                        $record->open_path = $USER->open_path;
                        $record->timecreated = time();
                        $record->usercreated = $USER->id;
                        $DB->insert_record(self::COURSEGROUPS_TABLE, $record);
                    }
                }
            }

            if ($isadmin) {
                // Admin-specific: Delete unchecked topics across all open paths.
                foreach ($existinggroupids as $groupid => $paths) {
                    if (!isset($selectedgroups[$groupid])) {
                        // Delete all records for this topic if it is not selected by the admin.
                        foreach ($paths as $pathid) {
                            $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $pathid]);
                            $sql = "componentid = :componentid AND courseid = :courseid AND status = :pending";
                            $params = [
                                'componentid' => $groupid,
                                'courseid' => $courseid,
                                'pending' => 'PENDING',
                            ];
                            // Delete the records with matching componentid, courseid, and status.
                            $DB->delete_records_select(self::ENROLLMENT_REQUESTS_TABLE, $sql, $params);
                        }
                        self::unenrollgroups($groupid, $courseid);
                        self::remove_hod_from_group($groupid);
                    }
                }
            } else {
                // Non-admins should only delete their unchecked topics for their path.
                foreach ($existinggroupids as $groupid => $paths) {
                    if (!isset($selectedgroups[$groupid]) && isset($paths[$USER->open_path])) {
                        $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $paths[$USER->open_path]]);
                            $sql = "componentid = :componentid AND courseid = :courseid AND status = :pending";
                            $params = [
                                'componentid' => $groupid,
                                'courseid' => $courseid,
                                'pending' => 'PENDING',
                                'createdbyid' => $USER->id,
                            ];
                            // Delete the records with matching componentid, courseid, and status.
                            $DB->delete_records_select(self::ENROLLMENT_REQUESTS_TABLE, $sql, $params);
                            self::unenrollgroups($groupid, $courseid);
                            self::remove_hod_from_group($groupid);
                    }
                }
            }

            self::course_content_accessrestrictions($courseid);
        } catch (\Exception $e) {
            throw new \moodle_exception("Error in classes enrollment: " . $e->getMessage());
        }
    }

    // public static function insert_groupsconfig_instance($data) {
    //     global $DB, $USER;

    //     try {
    //         $courseid = $data->courseid;
    //         $existinggroups = $DB->get_records(self::COURSEGROUPS_TABLE, ['courseid' => $courseid]);

    //         // Prepare an array to track existing group IDs.
    //         $existinggroupids = [];
    //         foreach ($existinggroups as $group) {
    //             $existinggroupids[$group->groupid] = $group->id;
    //         }

    //         // Insert or update checked groups.
    //         foreach ($data as $key => $value) {
    //             if (is_numeric($key) && $value == 1) {
    //                 if (!isset($existinggroupids[$key])) {
    //                     // Insert new record for checked groups.
    //                     $record = new stdClass();
    //                     $record->courseid = $courseid;
    //                     $record->groupid = $key;
    //                     $record->open_path = $USER->open_path;
    //                     $record->timecreated = time();
    //                     $record->usercreated = $USER->id;
    //                     $DB->insert_record(self::COURSEGROUPS_TABLE, $record);

    //                     // // Handle group enrollment request logic.
    //                     // $selectedGroups = [$key];
    //                     // self::groupenrollmentrequest($courseid, $selectedGroups);
    //                 }
    //                 // Remove processed group from the existing list.
    //                 unset($existinggroupids[$key]);
    //             }
    //         }
    //         // Delete unchecked groups that were previously checked.
    //         foreach ($existinggroupids as $groupid => $recordid) {
    //             $sql = "componentid = :componentid AND courseid = :courseid AND status = :pending";
    //             $params = [
    //                 'componentid' => $groupid,
    //                 'courseid' => $courseid,
    //                 'pending' => 'PENDING',
    //             ];
    //             // Delete the records with matching componentid, courseid, and status.
    //             $DB->delete_records_select(self::ENROLLMENT_REQUESTS_TABLE, $sql, $params);
    //             self::unenrollgroups($groupid, $courseid);
    //             self::remove_hod_from_group($groupid);

    //             // Also delete the unchecked group from COURSEGROUPS_TABLE.
    //             $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $recordid]);
    //         }

    //         self::course_content_accessrestrictions($courseid);
    //     } catch (\Exception $e) {
    //         throw new \moodle_exception("Error in class configuration");
    //     }
    // }


    // public static function insert_groupsconfig_instance($data) {
    //     global $DB, $USER;
    //     try {
    //         $courseid = $data->courseid;
    //         $existinggroups = $DB->get_records(self::COURSEGROUPS_TABLE, ['courseid' => $courseid]);

    //         // Prepare an array to track existing groups IDs.
    //         $existinggroupids = [];
    //         foreach ($existinggroups as $group) {
    //             $existinggroupids[$group->groupid] = $group->id;
    //         }

    //         // Insert or update checked groups.
    //         foreach ($data as $key => $value) {
    //             if (is_numeric($key) && $value == 1) {
    //                 if (!isset($existinggroupids[$key])) {
    //                     // Insert new record for checked groups.
    //                     $record = new stdClass();
    //                     $record->courseid = $courseid;
    //                     $record->groupid = $key;
    //                     $record->open_path = $USER->open_path;
    //                     $record->timecreated = time();
    //                     $record->usercreated = $USER->id;
    //                     $DB->insert_record(self::COURSEGROUPS_TABLE, $record);
    //                 }
    //                 // Remove processed topic from the existing list.
    //                 unset($existinggroupids[$key]);
    //             }
    //         }
    //         // Delete unchecked groups that were previously checked.
    //         foreach ($existinggroupids as $groupid => $recordid) {
    //             $sql = "componentid = :componentid AND courseid = :courseid AND status = :pending";
    //             $params = [
    //                 'componentid' => $groupid,
    //                 'courseid' => $courseid,
    //                 'pending' => 'PENDING',
    //             ];
    //             // Delete the records with matching componentid, courseid, and status.
    //             $DB->delete_records_select(self::ENROLLMENT_REQUESTS_TABLE, $sql, $params);
    //             self::unenrollgroups($groupid, $courseid);
    //             self::remove_hod_from_group($groupid);
                
    //             // Also delete the unchecked group from COURSEGROUPS_TABLE
    //             $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $recordid]);
    //         }
    //         self::course_content_accessrestrictions($courseid);
    //     } catch (\Exception $e) {
    //         throw new \moodle_exception("Error in classes enrollment");
    //     }
    // }

    // public static function course_content_accessrestrictions($courseid) {
    //     global $DB, $PAGE;

    //     try {
    //         $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    //         // Get all sections from the COURSESECTIONS_TABLE for the given course
    //         $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

    //         // Get all topics linked to course sections in the COURSETOPICS_TABLE
    //         $linkedTopics = $DB->get_records_menu(self::COURSETOPICS_TABLE, null, '', 'topic, id');

    //         // Query to get cohort sync list
    //         $cohortsync = "SELECT DISTINCT e.id, e.customint1, e.customint2
    //                        FROM {" . self::ENROLL_TABLE . "} e
    //                        JOIN {" . self::COURSEGROUPS_TABLE . "} cg
    //                          ON e.customint1 = cg.groupid
    //                        WHERE e.courseid = :courseid AND e.enrol = :enrol";
    //         $cohortparams = array('courseid' => $courseid, 'enrol' => 'cohort');
    //         $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);
    //         // Collect all group IDs (customint2) from cohort sync list
    //         $groupIds = array_map(function($cohort) {
    //             return $cohort->customint2;
    //         }, $cohortsynclist);

    //         if (!empty($groupIds)) {
    //             $newConditions = array_values(array_map(function($groupId) {
    //                 return array("type" => "group", "id" => (int)$groupId);
    //             }, $groupIds));

    //             foreach ($allSections as $section) {
    //                 if ($section->section > 0) {
    //                     $sectiondata = new stdClass();
    //                     $sectiondata->id = $section->id;

    //                     if (array_key_exists($section->id, $linkedTopics)) {
    //                         $existingAvailability = json_decode($section->availability, true);
    //                         $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];
    //                         $allConditions = array_merge($existingConditions, $newConditions);
    //                         $uniqueConditions = array_values(array_unique($allConditions, SORT_REGULAR));
    //                         $updatedAvailability = array(
    //                             "op" => "|",
    //                             "c" => $uniqueConditions,
    //                             "show" => false
    //                         );
    //                         $availabilityJson = json_encode($updatedAvailability);

    //                         if ($section->availability !== $availabilityJson) {
    //                             $sectiondata->availability = $availabilityJson;
    //                             $sectiondata->visible = 1;
    //                             course_update_section($course, $section, $sectiondata);
    //                         }
    //                     } else {
    //                         $sectiondata->visible = 0;
    //                         if ($section->visible != 0) {
    //                             course_update_section($course, $section, $sectiondata);
    //                         }
    //                     }
    //                 }
    //             }
    //         }
    //     } catch (Exception $e) {
    //         debugging('Error updating course content access restrictions: ' . $e->getMessage(), DEBUG_DEVELOPER);
    //     }
    // }

    public static function course_content_accessrestrictions($courseid) {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        // Get all sections from the COURSESECTIONS_TABLE for the given course
        $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

        // Get all topics linked to course sections in the COURSETOPICS_TABLE
        $linkedTopics = $DB->get_records(self::COURSETOPICS_TABLE, ['courseid' => $courseid]);

        // Query to get cohort sync list with open_path
        $cohortsync = "SELECT DISTINCT e.customint1, e.customint2 AS groupid, cg.open_path
                       FROM {" . self::ENROLL_TABLE . "} e
                       JOIN {" . self::COURSEGROUPS_TABLE . "} cg
                         ON e.customint1 = cg.groupid
                       WHERE e.courseid = :courseid AND e.enrol = :enrol";
        $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort'];
        $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);

        // Create a map of group IDs and their corresponding open_path
        $groupPaths = [];
        foreach ($cohortsynclist as $cohort) {
            $groupPaths[$cohort->groupid] = $cohort->open_path;
        }

        // Create an associative array to map section IDs to topic IDs
        $sectionsByTopic = [];
        foreach ($linkedTopics as $topicRecord) {
            $sectionsByTopic[$topicRecord->topic] = $topicRecord->topic;
        }
        // print_r($sectionsByTopic);
        // Iterate over all sections
        foreach ($allSections as $section) {
            if ($section->section > 0) {
                $sectiondata = new stdClass();
                $sectiondata->id = $section->id;

                // Determine if this section is linked to a topic
                if (isset($sectionsByTopic[$section->id])) {
                    $topicId = $sectionsByTopic[$section->id];
                    $groupIdsForTopic = [];

                    // Get the topic record to check its open_path
                    $topicRecord = $DB->get_record(self::COURSETOPICS_TABLE, ['topic' => $topicId]);
                    
                    // Admin topics (no open_path) should be visible to all groups
                    if (is_null($topicRecord->open_path)) {
                        // $groupIdsForTopic = array_keys($groupPaths);
                        // Admin topics (no open_path) should be visible to all groups
                        $groupIdsForTopic = array_merge($groupIdsForTopic, array_keys($groupPaths));
                    } else {
                        // HOD topics should be visible only to specific groups
                        foreach ($groupPaths as $groupId => $path) {
                            if ($topicRecord->open_path === $path) {
                                $groupIdsForTopic[] = (int)$groupId;
                            }
                        }
                    }

                    // Apply restrictions if there are valid group IDs for the topic
                    if (!empty($groupIdsForTopic)) {
                        // Create new access restrictions with the group IDs
                        // $newConditions = array_map(function($groupId) {
                        //     return ["type" => "group", "id" => $groupId];
                        // }, $groupIdsForTopic);

                        $newConditions = array_map(function($groupId) {
                            return ["type" => "group", "id" => $groupId];
                        }, array_unique($groupIdsForTopic)); // Ensure group IDs are unique

                        // Decode the existing availability JSON
                        $existingAvailability = json_decode($section->availability, true);

                        // Initialize existing conditions
                        $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];

                        // Merge new conditions with existing conditions
                        $allConditions = array_merge($existingConditions, $newConditions);

                        // Remove duplicate conditions
                        $uniqueConditions = array_values(array_unique($allConditions, SORT_REGULAR));

                        // Create a new availability structure
                        $updatedAvailability = [
                            "op" => "|",
                            "c" => $uniqueConditions,
                            "show" => false
                        ];

                        // Convert to JSON
                        $availabilityJson = json_encode($updatedAvailability);

                        $sectiondata->availability = $availabilityJson;
                        $sectiondata->visible = 1;
                    }
                } else {
                    // For sections not linked to any topic, ensure no restrictions are added
                    // $sectiondata->availability = null;
                    // $sectiondata->visible = 1;
                }

                // Update section with new availability and visibility settings
                course_update_section($course, $section, $sectiondata);
            }
        }
    }



    // public static function course_content_accessrestrictions($courseid) {
    //     global $DB;

    //     $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    //     // Get all sections from the COURSESECTIONS_TABLE for the given course
    //     $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

    //     // Get all topics linked to course sections in the COURSETOPICS_TABLE
    //     $linkedTopics = $DB->get_records(self::COURSETOPICS_TABLE, ['courseid' => $courseid]);

    //     // Query to get cohort sync list with group IDs and open_path
    //     $cohortsync = "SELECT DISTINCT e.customint1 AS groupid, cg.open_path
    //                    FROM {" . self::ENROLL_TABLE . "} e
    //                    JOIN {" . self::COURSEGROUPS_TABLE . "} cg
    //                      ON e.customint1 = cg.groupid
    //                    WHERE e.courseid = :courseid AND e.enrol = :enrol";
    //     $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort'];
    //     $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);

    //     // Create a map of open_path to group IDs
    //     $pathToGroups = [];
    //     foreach ($cohortsynclist as $cohort) {
    //         if (!isset($pathToGroups[$cohort->open_path])) {
    //             $pathToGroups[$cohort->open_path] = [];
    //         }
    //         $pathToGroups[$cohort->open_path][] = $cohort->groupid;
    //     }

    //     // Iterate over all sections
    //     foreach ($allSections as $section) {
    //         if ($section->section > 0) {
    //             $sectiondata = new stdClass();
    //             $sectiondata->id = $section->id;

    //             // Check if the section is linked to a topic
    //             if (isset($linkedTopics[$section->id])) {
    //                 $topic = $linkedTopics[$section->id];
    //                 $groupIdsForTopic = [];

    //                 // Admin topics (no open_path) should be visible to all groups
    //                 if (is_null($topic->open_path)) {
    //                     // Collect all groups from pathToGroups
    //                     foreach ($pathToGroups as $groups) {
    //                         $groupIdsForTopic = array_merge($groupIdsForTopic, $groups);
    //                     }
    //                 } else {
    //                     // HOD topics should be visible only to specific groups
    //                     if (isset($pathToGroups[$topic->open_path])) {
    //                         $groupIdsForTopic = $pathToGroups[$topic->open_path];
    //                     }
    //                 }

    //                 // Apply restrictions if there are valid group IDs for the topic
    //                 if (!empty($groupIdsForTopic)) {
    //                     // Create new access restrictions with the group IDs
    //                     $newConditions = array_map(function($groupId) {
    //                         return ["type" => "group", "id" => (int)$groupId];
    //                     }, $groupIdsForTopic);

    //                     // Decode the existing availability JSON
    //                     $existingAvailability = json_decode($section->availability, true);

    //                     // Initialize existing conditions
    //                     $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];

    //                     // Merge new conditions with existing conditions
    //                     $allConditions = array_merge($existingConditions, $newConditions);

    //                     // Remove duplicate conditions
    //                     $uniqueConditions = array_values(array_unique($allConditions, SORT_REGULAR));

    //                     // Create a new availability structure
    //                     $updatedAvailability = [
    //                         "op" => "|",
    //                         "c" => $uniqueConditions,
    //                         "show" => false
    //                     ];

    //                     // Convert to JSON
    //                     $availabilityJson = json_encode($updatedAvailability);

    //                     $sectiondata->availability = $availabilityJson;
    //                     $sectiondata->visible = 1;
    //                 } else {
    //                     // No valid group IDs, hide section
    //                     $sectiondata->availability = null;
    //                     $sectiondata->visible = 0;
    //                 }
    //             } else {
    //                 // For sections not linked to any topic, ensure no restrictions are added
    //                 $sectiondata->availability = null;
    //                 $sectiondata->visible = 1;
    //             }

    //             // Update section with new availability and visibility settings
    //             course_update_section($course, $section, $sectiondata);
    //         }
    //     }
    // }



    // public static function course_content_accessrestrictions_working_previously($courseid) {
    //     global $DB, $PAGE;
    //     $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    //     // Get all sections from the COURSESECTIONS_TABLE for the given course
    //     $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

    //     // Get all topics linked to course sections in the COURSETOPICS_TABLE
    //     $linkedTopics = $DB->get_records_menu(self::COURSETOPICS_TABLE, null, '', 'topic, id');

    //     // Query to get cohort sync list
    //     $cohortsync = "SELECT DISTINCT e.id, e.customint1, e.customint2
    //                    FROM {" . self::ENROLL_TABLE . "} e
    //                    JOIN {" . self::COURSEGROUPS_TABLE . "} cg
    //                      ON e.customint1 = cg.groupid
    //                    WHERE e.courseid = :courseid AND e.enrol = :enrol";
    //     $cohortparams = array('courseid' => $courseid, 'enrol' => 'cohort');
    //     $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);

    //     // Collect all group IDs (customint2) from cohort sync list
    //     $groupIds = array_map(function($cohort) {
    //         return $cohort->customint2;
    //     }, $cohortsynclist);

    //     // Check if there are any group IDs
    //     if (!empty($groupIds)) {
    //         // Create new conditions with the group IDs
    //         $newConditions = array_values(array_map(function($groupId) {
    //             return array("type" => "group", "id" => (int)$groupId);
    //         }, $groupIds));

    //         // Iterate over all sections
    //         foreach ($allSections as $section) {
                
    //             if ($section->section > 0) {
    //                 $sectiondata = new stdClass();
    //                 $sectiondata->id = $section->id;

    //                 // If the section is not linked to any topic, add restrictions
    //                 if (array_key_exists($section->id, $linkedTopics)) {

    //                     // Decode the existing availability JSON
    //                     $existingAvailability = json_decode($section->availability, true);

    //                     // Initialize existing conditions
    //                     $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];

    //                     // Merge new conditions with existing conditions
    //                     $allConditions = array_merge($existingConditions, $newConditions);

    //                     // Remove duplicate conditions
    //                     $uniqueConditions = array_values(array_unique($allConditions, SORT_REGULAR));
    //                     // Create a new availability structure
    //                     $updatedAvailability = array(
    //                         "op" => "|",
    //                         "c" => $uniqueConditions,
    //                         "show" => false
    //                     );

    //                     // Convert to JSON
    //                     $availabilityJson = json_encode($updatedAvailability);

    //                     $sectiondata->availability = $availabilityJson;
    //                     $sectiondata->visible = 1;
    //                 } else {
    //                     // $sectiondata->visible = 0;
    //                 }
                    
    //                 course_update_section($course, $section, $sectiondata);
    //             }
    //         }
    //     }
    // }
    public static function get_administrator_roleid() {
        global $DB;
        return $DB->get_field('role', 'id', array('shortname' => 'administrator'));
    }
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
    public static function is_administrator() {
        global $USER;
        $current_roleid = $USER->useraccess['currentroleinfo']['roleid'];
        
        if(!empty($current_roleid) && $current_roleid == self::get_administrator_roleid()) {
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

    // public static function unenrollgroups($groupid, $courseid, $fields = null) {
    //     global $DB;
    //     $instances = enrol_get_instances($courseid, false);
    //     $chohortplugin = enrol_get_plugin('cohort');

    //     $params = [
    //         'customint1' => $groupid,
    //         'courseid' => $courseid,
    //         'enrol' => 'cohort',
    //     ];
    //     $coursecohortslist = $DB->get_records('enrol', $params);
    //     if(!empty($coursecohortslist)) {
    //         foreach($coursecohortslist as $coursecohort) {
    //             $chohortplugin->update_communication($coursecohort->id, 'remove', $courseid);
    //             $instance = $instances[$coursecohort->id];
    //             $chohortplugin->delete_instance($instance);
    //             groups_delete_group($coursecohort->customint2);
    //         }  
    //     }        
    // }

    public static function unenrollgroups($groupid, $courseid, $fields = null) {
        global $DB;
        $instances = enrol_get_instances($courseid, false);
        $cohortplugin = enrol_get_plugin('cohort');

        $params = [
            'customint1' => $groupid,
            'courseid' => $courseid,
            'enrol' => 'cohort',
        ];
        $coursecohortslist = $DB->get_records('enrol', $params);

        if (!empty($coursecohortslist)) {
            foreach ($coursecohortslist as $coursecohort) {
                $instance = $instances[$coursecohort->id];
                // Remove cohort from course
                $cohortplugin->delete_instance($instance);

                // Delete associated group only if it is not linked to any other course
                if (!$DB->record_exists('enrol', ['customint2' => $coursecohort->customint2])) {
                    groups_delete_group($coursecohort->customint2);
                }
            }
        }        
    }

    
    public static function get_costcenter_rolelist($costcenterpath = null, $roleid = false) {
        global $DB, $USER;
        $costcenterpath = $costcenterpath ? $costcenterpath : $USER->open_path;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context($costcenterpath);
        // $condition = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        if($roleid) {
            $roleid = $roleid;
        } else {
            $roleid = self::get_hod_roleid();
        }
        $sql = "SELECT ra.id, ra.roleid, u.id as userid, u.firstname, u.lastname, u.email, u.open_employeeid, ra.contextid
        FROM {role_assignments} AS ra
        JOIN {user} AS u ON u.id = ra.userid
        WHERE ra.contextid = :contextid AND ra.roleid = :roleid";

        $params = array('contextid' => $context->id, 'roleid' => $roleid);

        $rolelist = $DB->get_records_sql($sql, $params);
        $data = [
            'rolelist' => $rolelist,
            'rolecount' => count($rolelist),
            'contextid' => $context->id
        ];
        return $data; 
    }

    public static function add_hod_to_group($groupId) {
        global $CFG, $DB;
        $departmenth = $DB->get_field('local_groups', 'open_path', array('id' => $groupId));
        $hodlist = self::get_costcenter_rolelist($departmenth);
        if(!empty($hodlist)) {
            foreach($hodlist['rolelist'] as $hodlist) {
                local_groups_add_member($groupId, $hodlist->userid);
            }
        } 
    }

    public static function remove_hod_from_group($groupId) {
        global $CFG, $DB;
        $departmenth = $DB->get_field('local_groups', 'open_path', array('id' => $groupId));
        $hodlist = self::get_costcenter_rolelist($departmenth);
        if(!empty($hodlist)) {
            foreach($hodlist['rolelist'] as $hodlist) {
                local_groups_remove_member($groupId, $hodlist->userid);
            }
        } 
    }
}
