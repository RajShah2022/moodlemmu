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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/groups/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class api {
    public const COURSETOPICS_TABLE = 'local_coursetopics';
    public const COURSEGROUPS_TABLE = 'local_coursegroups';
    public const COURSEGROUPTOPICS_TABLE = 'local_coursegrouptopics';
    public const COURSEGROUPMODULES_TABLE = 'local_coursegroupmodules';
    public const LOCAL_GROUPS = 'local_groups';
    public const ENROLLMENT_REQUESTS_TABLE = 'local_request_records';
    public const ENROLL_TABLE = 'enrol';
    public const COURSESECTIONS_TABLE = 'course_sections';
    public const COURSEMODULES_TABLE = 'course_modules';

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
                        $record->timemodified = time();
                        $record->usermodified = $USER->id;
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
                            $DB->delete_records(self::COURSEGROUPTOPICS_TABLE, ['topic' => $topicid]);
                            $DB->delete_records(self::COURSEGROUPMODULES_TABLE, ['topicid' => $topicid]);
                        }
                    }
                }
            } else {
                // Non-admins should only delete their unchecked topics for their path.
                foreach ($existingtopicids as $topicid => $paths) {
                    if (!isset($selectedtopics[$topicid]) && isset($paths[$USER->open_path])) {
                        $DB->delete_records(self::COURSETOPICS_TABLE, ['id' => $paths[$USER->open_path]]);
                        $DB->delete_records(self::COURSEGROUPTOPICS_TABLE, ['topic' => $topicid]);
                        $DB->delete_records(self::COURSEGROUPMODULES_TABLE, ['topicid' => $topicid]);
                    }
                }
            }

            self::course_content_accessrestrictions($courseid);
            self::update_section_availability($courseid);
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
                        $record->timemodified = time();
                        $record->usermodified = $USER->id;
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
                            $DB->delete_records(self::COURSEGROUPTOPICS_TABLE, ['groupid' => $groupid]);
                            $DB->delete_records(self::COURSEGROUPMODULES_TABLE, ['groupid' => $groupid]);
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
                        // self::remove_hod_from_group($groupid);
                    }
                }
            } else {
                // Non-admins should only delete their unchecked topics for their path.
                foreach ($existinggroupids as $groupid => $paths) {
                    if (!isset($selectedgroups[$groupid]) && isset($paths[$USER->open_path])) {
                        $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $paths[$USER->open_path]]);
                        $DB->delete_records(self::COURSEGROUPTOPICS_TABLE, ['groupid' => $groupid]);
                        $DB->delete_records(self::COURSEGROUPMODULES_TABLE, ['groupid' => $groupid]);
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
                            // self::remove_hod_from_group($groupid);
                    }
                }
            }

            self::course_content_accessrestrictions($courseid);
            self::update_section_availability($courseid);
        } catch (\Exception $e) {
            throw new \moodle_exception("Error in classes enrollment: " . $e->getMessage());
        }
    }

    public static function insert_groupcontentconfig_instance($data) {
        global $DB, $USER;

        try {
            $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
            $courseid = $data->courseid;
            $groupid = $data->groupid;

            // Load all existing topics and activities for the course group.
            $alltopics = $DB->get_records(self::COURSEGROUPTOPICS_TABLE, ['courseid' => $courseid, 'groupid' => $groupid]);

            $allactivities = $DB->get_records(self::COURSEGROUPMODULES_TABLE, ['courseid' => $courseid, 'groupid' => $groupid]);

            // Prepare arrays to track existing topic and activity IDs.
            $existingtopicids = [];
            $existingactivityids = [];
            foreach ($alltopics as $topic) {
                $existingtopicids[$topic->topic][$topic->open_path] = $topic->id;
            }
            foreach ($allactivities as $activity) {
                $existingactivityids[$activity->moduleid][$activity->open_path] = $activity->id;
            }

            // Track selected topics and activities.
            $selectedtopics = [];
            $selectedactivities = [];
            // print_r($data);
            // Insert or update checked topics and activities.
            foreach ($data as $key => $value) {
                // print_r($key);
                // print_r($value);
                if (preg_match('/^section_(\d+)$/', $key, $matches) && $value == 1) {
                    $topicid = $matches[1];
                    $selectedtopics[$topicid] = true;

                    if (isset($existingtopicids[$topicid][$USER->open_path])) {
                        // Update existing record if it already exists for this path.
                        $record = new stdClass();
                        $record->id = $existingtopicids[$topicid][$USER->open_path];
                        $record->timemodified = time();
                        $record->usermodified = $USER->id;
                        $DB->update_record(self::COURSEGROUPTOPICS_TABLE, $record);

                        // Remove the topic from the list to be deleted.
                        unset($existingtopicids[$key][$USER->open_path]);
                    } else {

                        // Insert new record for a newly checked topic.
                        $record = new stdClass();
                        $record->courseid = $courseid;
                        $record->groupid = $groupid;
                        $record->topic = $topicid;
                        $record->open_path = $USER->open_path;
                        $record->timecreated = time();
                        $record->usercreated = $USER->id;
                        // print_r($record);
                        $createdid = $DB->insert_record(self::COURSEGROUPTOPICS_TABLE, $record);
                    }
                }

                if (preg_match('/^activity_(\d+)$/', $key, $matches) && $value == 1) {
                    $activityid = $matches[1];
                    $selectedactivities[$activityid] = true;

                    if (isset($existingactivityids[$activityid][$USER->open_path]) && isset($topicid)) {
                        $record = new stdClass();
                        $record->id = $existingactivityids[$activityid][$USER->open_path];
                        $record->timemodified = time();
                        $record->usermodified = $USER->id;
                        $DB->update_record(self::COURSEGROUPMODULES_TABLE, $record);

                        unset($existingactivityids[$activityid][$USER->open_path]);
                    } else {
                        $record = new stdClass();
                        $record->courseid = $courseid;
                        $record->groupid = $groupid;
                        $record->topicid = $topicid;
                        $record->moduleid = $activityid;
                        $record->open_path = $USER->open_path;
                        $record->timecreated = time();
                        $record->usercreated = $USER->id;
                        // print_r($record);
                        $DB->insert_record(self::COURSEGROUPMODULES_TABLE, $record);
                    }
                }
            }
            
            // Delete any unchecked topics and activities.
            foreach ($existingtopicids as $topicid => $paths) {
                if (!isset($selectedtopics[$topicid]) && isset($paths[$USER->open_path])) {
                    self::update_deleted_section_availability($courseid, $topicid, $groupid);
                    $DB->delete_records(self::COURSEGROUPTOPICS_TABLE, ['id' => $paths[$USER->open_path]]);
                }
            }

            foreach ($existingactivityids as $activityid => $paths) {
                if (!isset($selectedactivities[$activityid]) && isset($paths[$USER->open_path])) {
                    self::update_deleted_module_availability($courseid, $activityid, $groupid);
                    $DB->delete_records(self::COURSEGROUPMODULES_TABLE, ['id' => $paths[$USER->open_path]]);
                }
            }

            self::update_section_availability($courseid);
        } catch (\Exception $e) {
            throw new \moodle_exception("Error in class content configuring: " . $e->getMessage());
        }
    }

    // public static function insert_groupcontentconfig_instance($data) {
    //     global $DB, $USER;

    //     try {
    //         $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    //         $courseid = $data->courseid;
    //         $groupid = $data->groupid;

    //         // Load all existing topics for the course of the group.
    //         $alltopics = $DB->get_records(self::COURSEGROUPTOPICS_TABLE, ['courseid' => $courseid, 'groupid' => $groupid]);

    //         // Prepare arrays to track topics by their open paths.
    //         $existingtopicids = [];
    //         foreach ($alltopics as $topic) {
    //             if (!isset($existingtopicids[$topic->topic])) {
    //                 $existingtopicids[$topic->topic] = [];
    //             }
    //             $existingtopicids[$topic->topic][$topic->open_path] = $topic->id;
    //             // $existingtopicids[$topic->topic][$topic->groupid] = $topic->id;
    //         }

    //         // Track topics that are selected in the current form submission.
    //         $selectedtopics = [];

    //         // Insert or update checked topics.
    //         foreach ($data as $key => $value) {
    //             if (is_numeric($key) && $value == 1) {
    //                 $selectedtopics[$key] = true;

    //                 if (isset($existingtopicids[$key][$USER->open_path])) {
    //                     // Update existing record if it already exists for this path.
    //                     $record = new stdClass();
    //                     $record->id = $existingtopicids[$key][$USER->open_path];
    //                     $record->timemodified = time();
    //                     $record->usermodified = $USER->id;
    //                     $updatedid = $DB->update_record(self::COURSEGROUPTOPICS_TABLE, $record);

    //                     // Remove the topic from the list to be deleted.
    //                     unset($existingtopicids[$key][$USER->open_path]);
    //                 } else {
    //                     // Insert new record for a newly checked topic.
    //                     $record = new stdClass();
    //                     $record->courseid = $courseid;
    //                     $record->groupid = $groupid;
    //                     $record->topic = $key;
    //                     $record->open_path = $USER->open_path;
    //                     $record->timecreated = time();
    //                     $record->usercreated = $USER->id;
    //                     $createdid = $DB->insert_record(self::COURSEGROUPTOPICS_TABLE, $record);
    //                 }
    //             }
    //         }
            
    //         // Non-admins should only delete their unchecked topics for their path.
    //         foreach ($existingtopicids as $topicid => $paths) {
    //             if (!isset($selectedtopics[$topicid]) && isset($paths[$USER->open_path])) {
    //                 $DB->delete_records(self::COURSEGROUPTOPICS_TABLE, ['id' => $paths[$USER->open_path]]);
    //             }
    //         }

    //         // self::course_content_accessrestrictions($courseid, $groupid);
    //     } catch (\Exception $e) {
    //         throw new \moodle_exception("Error in class content configuring: " . $e->getMessage());
    //     }
    // }

    public static function course_content_accessrestrictions($courseid, $groupid = false) {
        global $DB;

        // Fetch teacher and employee (student) role IDs as integers
        $teacherRoleId = (int)self::get_teacher_roleid();
        $employeeRoleId = (int)self::get_student_roleid();

        // Get the course record
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Get all sections from the COURSESECTIONS_TABLE for the given course
        $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

        // if($DB->record_exists(self::COURSEGROUPTOPICS_TABLE, ['courseid' => $courseid])){
        //     // Get all topics linked to course sections in the COURSETOPICS_TABLE
            $linkedTopics = $DB->get_records(self::COURSETOPICS_TABLE, ['courseid' => $courseid]);
        // } else {
            // Get all topics linked to course sections in the COURSETOPICS_TABLE
            // $linkedTopics = $DB->get_records(self::COURSEGROUPTOPICS_TABLE, ['courseid' => $courseid]);
        // }
        

        // Query to get cohort sync list with open_path
        $cohortsync = "SELECT DISTINCT e.customint1 AS groupid, e.customint2, cg.open_path
                       FROM {" . self::ENROLL_TABLE . "} e
                       JOIN {" . self::COURSEGROUPS_TABLE . "} cg
                         ON e.customint1 = cg.groupid AND e.courseid = cg.courseid
                       WHERE e.courseid = :courseid AND e.enrol = :enrol";

        $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort'];
        $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);

        // Create a map of customint2 (group information) to open_path
        $groupPaths = [];
        foreach ($cohortsynclist as $cohort) {
            $groupPaths[$cohort->customint2] = $cohort->open_path;
        }

        // Create an associative array to map section IDs to topic IDs
        $sectionsByTopic = [];
        foreach ($linkedTopics as $topicRecord) {
            $sectionsByTopic[$topicRecord->topic] = $topicRecord->topic;
        }
        // Iterate over all sections
        foreach ($allSections as $section) {
            if ($section->section > 0) {
                $sectiondata = new stdClass();
                $sectiondata->id = $section->id;
                $sectiondata->section = $section->section;

                // Initialize the availability conditions
                $allConditions = [];

                // Determine if this section is linked to a topic
                if (isset($sectionsByTopic[$section->id])) {
                    $topicId = $sectionsByTopic[$section->id];
                    
                    // Get all topic records for the specific topic ID
                    $topicRecords = $DB->get_records(self::COURSETOPICS_TABLE, ['topic' => $topicId]);

                    // Iterate through each topic record
                    foreach ($topicRecords as $topicRecord) {
                        if (is_null($topicRecord->open_path)) {
                            // // Admin topics (no open_path) should be visible to all groups
                            // $allConditions = array_merge($allConditions, array_map(function($customint2) {
                            //     return ["type" => "group", "id" => $customint2];
                            // }, array_keys($groupPaths)));

                            // Admin topics (no open_path) should be visible to all groups
                            foreach (array_keys($groupPaths) as $customint2) {
                                $allConditions[] = [
                                    "op" => "&",
                                    "c" => [
                                        ["type" => "group", "id" => $customint2],
                                        ["type" => "role", "id" => $teacherRoleId] // Add role condition for 'teacher'
                                    ]
                                ];
                            }

                        } else {
                            // HOD topics should be visible only to specific groups
                            foreach ($groupPaths as $customint2 => $path) {
                                if ($topicRecord->open_path === $path) {
                                    // $allConditions[] = ["type" => "group", "id" => $customint2];
                                    $allConditions[] = [
                                        "op" => "&",
                                        "c" => [
                                            ["type" => "group", "id" => $customint2],
                                            ["type" => "role", "id" => $teacherRoleId] // Add role condition for 'teacher'
                                        ]
                                    ];
                                }
                            }
                        }
                    }
                }

                // Remove duplicate conditions
                $uniqueConditions = array_values(array_unique($allConditions, SORT_REGULAR));

                // Create a new availability structure if conditions exist, else set null
                if (!empty($uniqueConditions)) {
                    $updatedAvailability = [
                        "op" => "|",
                        "c" => $uniqueConditions,
                        "show" => false
                    ];
                    $availabilityJson = json_encode($updatedAvailability);
                    $sectiondata->availability = $availabilityJson;
                    $sectiondata->visible = 1;
                } else {
                    $sectiondata->availability = null;
                    $sectiondata->visible = 0;
                }
                // Update section with new availability and visibility settings
                course_update_section($course, $section, $sectiondata);

                // Retrieve the list of modules (activities) in this section
                $modules = !empty($section->sequence) ? explode(',', $section->sequence) : array();
                if (!empty($modules)) {
                    // Update each module's visibility to match the section's visibility
                    foreach ($modules as $moduleid) {
                        $DB->set_field('course_modules', 'visible', 0, ['id' => $moduleid]);
                        $DB->set_field('course_modules', 'availability', null, ['id' => $moduleid]);
                    }
                }
            }
        }
    }

    public static function update_section_availability($courseid) {
        global $DB;

        // Get the course record
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Get all sections from the COURSESECTIONS_TABLE for the given course
        $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

        // Get all topics linked to course sections for a group in the COURSEGROUPTOPICS_TABLE
        $linkedTopics = $DB->get_records(self::COURSEGROUPTOPICS_TABLE, ['courseid' => $courseid]);

        // Create an associative array to map section IDs to topic IDs
        $sectionsByTopic = [];
        foreach ($linkedTopics as $topicRecord) {
            $sectionsByTopic[$topicRecord->topic] = $topicRecord->topic;
        }

        // Iterate over all sections
        foreach ($allSections as $section) {
            $sectiondata = new stdClass();
            $sectiondata->id = $section->id;
            $sectiondata->section = $section->section;

            // Initialize the availability conditions
            $allConditions = [];

            // Determine if this section is linked to a topic
            if (isset($sectionsByTopic[$section->id])) {
                $topicId = $sectionsByTopic[$section->id];

                // Get all topic records for the specific topic ID
                $topicRecords = $DB->get_records(self::COURSEGROUPTOPICS_TABLE, ['topic' => $topicId]);

                // Iterate through each topic record and apply group/role restrictions
                foreach ($topicRecords as $topicRecord) {
                    // Query to get cohort sync list with open_path
                    $cohortsync = "SELECT DISTINCT e.customint1 AS groupid, e.customint2, cg.open_path
                                   FROM {" . self::ENROLL_TABLE . "} e
                                   JOIN {" . self::COURSEGROUPTOPICS_TABLE . "} cg
                                     ON e.customint1 = cg.groupid AND e.courseid = cg.courseid
                                   WHERE e.courseid = :courseid AND e.enrol = :enrol AND cg.groupid = :groupid";

                    $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort', 'groupid' => $topicRecord->groupid];
                    $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);

                    // Create a map of customint2 (group information) to open_path
                    $groupPaths = [];
                    foreach ($cohortsynclist as $cohort) {
                        $groupPaths[$cohort->customint2] = $cohort->open_path;
                    }
                    foreach ($groupPaths as $customint2 => $path) {
                        if ($topicRecord->open_path === $path) {
                            $existingAvailability = json_decode($section->availability, true);
                            $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];
                            
                            // Iterate over existing conditions to merge
                            foreach ($existingConditions as &$condition) {
                                if (isset($condition['c'])) {
                                    foreach ($condition['c'] as &$subCondition) {
                                        if ($subCondition['type'] === 'group' && $subCondition['id'] === $customint2) {
                                            // // Update the matching group condition
                                            $subCondition = ["type" => "group", "id" => $customint2];
                                        }
                                    }
                                }
                            }

                            $newCondition = [
                                "op" => "&",
                                "c" => [["type" => "group", "id" => $customint2]]
                            ];
                           
                            $existingConditions[] = $newCondition;

                            // Remove duplicate conditions and merge
                            $uniqueConditions = array_values(array_unique($existingConditions, SORT_REGULAR));

                            // Update the availability
                            if (!empty($uniqueConditions)) {
                                $updatedAvailability = [
                                    "op" => "|",
                                    "c" => $uniqueConditions,
                                    "show" => false
                                ];
                                $availabilityJson = json_encode($updatedAvailability);
                                $sectiondata->availability = $availabilityJson;
                                $sectiondata->visible = 1;
                            }

                            // Apply the changes
                            course_update_section($course, $section, $sectiondata);
                        }
                    }

                    // Fetch the modules linked to this topic from mdl_local_coursegroupmodules
                    $linkedModules = $DB->get_records(self::COURSEGROUPMODULES_TABLE, ['courseid' => $courseid, 'topicid' => $topicId, 'groupid' => $topicRecord->groupid]);
                    // Iterate through each module and apply the same conditions
                    // print_r($groupPaths);
                    // print_r($linkedModules);

                    // Iterate through each module and apply the same conditions
                    foreach ($linkedModules as $module) {
                        $moduleConditions = [];
                        // print_r($groupPaths);

                        // Fetch the current availability for the module
                        $moduleRecord = $DB->get_record('course_modules', ['id' => $module->moduleid], 'id, availability');
                        $existingAvailability = !empty($moduleRecord->availability) ? json_decode($moduleRecord->availability, true) : [];

                        // If there are existing conditions, retrieve them
                        $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];
                        foreach ($groupPaths as $customint2 => $path) {
                            if ($module->open_path === $path) {
                                $newCondition = ["type" => "group", "id" => $customint2];

                                // Check if this group restriction already exists to avoid duplicates
                                $groupExists = false;
                                foreach ($existingConditions as $condition) {
                                    if ($condition['type'] === 'group' && $condition['id'] === $customint2) {
                                        $groupExists = true;
                                        break;
                                    }
                                }

                                // Add the new group condition only if it doesn't already exist
                                if (!$groupExists) {
                                    $moduleConditions[] = $newCondition;
                                }
                            }
                        }
                        // Merge new conditions with the existing ones
                        $mergedConditions = array_merge($existingConditions, $moduleConditions);

                        // Remove duplicate conditions for modules
                        $uniqueModuleConditions = array_values(array_unique($mergedConditions, SORT_REGULAR));
                        // print_r($uniqueModuleConditions);
                        // Update module availability if conditions exist
                        if (!empty($uniqueModuleConditions)) {
                            $moduleAvailability = [
                                "op" => "|",
                                "c" => $uniqueModuleConditions,
                                "show" => false
                            ];
                            $moduleAvailabilityJson = json_encode($moduleAvailability);

                            // Update the availability field in course_modules
                            $DB->set_field('course_modules', 'availability', $moduleAvailabilityJson, ['id' => $module->moduleid]);
                            $DB->set_field('course_modules', 'visible', $sectiondata->visible, ['id' => $module->moduleid]);
                            \course_modinfo::purge_course_module_cache($courseid, $module->moduleid);
                            rebuild_course_cache($course->id, true, true);
                        } 
                        // else {
                        //     $DB->set_field('course_modules', 'visible', $section->visible, ['id' => $module->moduleid]);
                        // }
                    }
                }
            }
        }
    }

    public static function update_deleted_section_availability($courseid, $topicid, $groupid) {
        global $DB;
        // Get all sections from the COURSESECTIONS_TABLE for the given course
        $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

        // Iterate over all sections
        foreach ($allSections as $section) {
            $sectiondata = new stdClass();
            $sectiondata->id = $section->id;
            $sectiondata->section = $section->section;
            if($section->id == $topicid) {
                $topicRecord = $DB->get_record(self::COURSEGROUPTOPICS_TABLE, ['topic' => $topicid, 'groupid' => $groupid]);
                // Query to get cohort sync list with open_path
                $cohortsync = "SELECT DISTINCT e.customint1 AS groupid, e.customint2, cg.open_path
                               FROM {" . self::ENROLL_TABLE . "} e
                               JOIN {" . self::COURSEGROUPTOPICS_TABLE . "} cg
                                 ON e.customint1 = cg.groupid AND e.courseid = cg.courseid
                               WHERE e.courseid = :courseid AND e.enrol = :enrol AND cg.groupid = :groupid";
                $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort', 'groupid' => $topicRecord->groupid];
                $cohortsynclist = $DB->get_record_sql($cohortsync, $cohortparams);
                
                $existingAvailability = json_decode($section->availability, true);
                $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];
                
                // Traverse through each condition and remove the matching group condition only if it's standalone
                foreach ($existingConditions as $key => &$condition) {
                    // Check if the condition has nested conditions (which is typical with "op" => "&")
                    if (isset($condition['c'])) {
                        $subconditions = $condition['c'];
                        
                        // Count conditions within the block
                        $totalConditions = count($subconditions);

                        // Check if the condition block contains only one subcondition with the group ID to remove
                        if ($totalConditions == 1 && isset($subconditions[0]['type']) && $subconditions[0]['type'] === 'group' && $subconditions[0]['id'] == $cohortsynclist->customint2) {
                            // Remove this whole condition as it's a standalone group condition
                            unset($existingConditions[$key]);
                        } 
                    }
                }
               
                $uniqueConditions = array_values(array_unique($existingConditions, SORT_REGULAR));
                // Update the availability
                if (!empty($uniqueConditions)) {
                    $updatedAvailability = [
                        "op" => "|",
                        "c" => $uniqueConditions,
                        "show" => false
                    ];
                    $availabilityJson = json_encode($updatedAvailability);
                    $sectiondata->availability = $availabilityJson;
                     // Apply the changes
                    course_update_section($courseid, $section, $sectiondata);
                }
            }
        }
    }




    public static function update_deleted_module_availability($courseid, $moduleid, $groupid) {
        global $DB;
        $moduleRecord = $DB->get_record(self::COURSEGROUPMODULES_TABLE, ['courseid' => $courseid, 'moduleid' => $moduleid, 'groupid' => $groupid]);

        $cousreModulesRecord = $DB->get_record(self::COURSEMODULES_TABLE, ['course' => $courseid, 'id' => $moduleid, 'section' => $moduleRecord->topicid]);

        $cohortsync = "SELECT DISTINCT e.customint1 AS groupid, e.customint2, cg.open_path
                       FROM {" . self::ENROLL_TABLE . "} e
                       JOIN {" . self::COURSEGROUPMODULES_TABLE . "} cg
                         ON e.customint1 = cg.groupid AND e.courseid = cg.courseid
                       WHERE e.courseid = :courseid AND e.enrol = :enrol AND cg.groupid = :groupid";

        $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort', 'groupid' => $moduleRecord->groupid];
        $cohortsynclist = $DB->get_record_sql($cohortsync, $cohortparams);

        $existingAvailability = json_decode($cousreModulesRecord->availability, true);
        if(!empty($existingAvailability)){
            $DB->set_field('course_modules', 'availability', '', ['id' => $cousreModulesRecord->id]);
            $DB->set_field('course_modules', 'visible', 0, ['id' => $cousreModulesRecord->id]);
            \course_modinfo::purge_course_module_cache($courseid, $cousreModulesRecord->id);
            rebuild_course_cache($courseid, true, true);
        }
    }


    public static function update_section_availability_bkp($courseid, $groupid = false) {
        global $DB;

        // Fetch teacher and employee (student) role IDs as integers
        $teacherRoleId = (int)self::get_teacher_roleid();
        $employeeRoleId = (int)self::get_student_roleid();

        // Get the course record
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Get all sections from the COURSESECTIONS_TABLE for the given course
        $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

        
        // Get all topics linked to course sections for a group in the COURSEGROUPTOPICS_TABLE
        $linkedTopics = $DB->get_records(self::COURSEGROUPTOPICS_TABLE, ['courseid' => $courseid,'groupid' => $groupid]);

        // Query to get cohort sync list with open_path
        $cohortsync = "SELECT DISTINCT e.customint1 AS groupid, e.customint2, cg.open_path
                       FROM {" . self::ENROLL_TABLE . "} e
                       JOIN {" . self::COURSEGROUPTOPICS_TABLE . "} cg
                         ON e.customint1 = cg.groupid AND e.courseid = cg.courseid
                       WHERE e.courseid = :courseid AND e.enrol = :enrol";

        $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort'];
        $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);

        
        // Create a map of customint2 (group information) to open_path
        $groupPaths = [];
        foreach ($cohortsynclist as $cohort) {
            $groupPaths[$cohort->customint2] = $cohort->open_path;
        }

        // Create an associative array to map section IDs to topic IDs
        $sectionsByTopic = [];
        foreach ($linkedTopics as $topicRecord) {
            $sectionsByTopic[$topicRecord->topic] = $topicRecord->topic;
        }

        // Iterate over all sections
        foreach ($allSections as $section) {
            $sectiondata = new stdClass();
            $sectiondata->id = $section->id;
            $sectiondata->section = $section->section;

            // Initialize the availability conditions
            $allConditions = [];

            // Determine if this section is linked to a topic
            if (isset($sectionsByTopic[$section->id])) {
                $topicId = $sectionsByTopic[$section->id];
                
                // Get all topic records for the specific topic ID
                $topicRecords = $DB->get_records(self::COURSETOPICS_TABLE, ['topic' => $topicId]);

                // Iterate through each topic record
                foreach ($topicRecords as $topicRecord) {
                    // Teacher topics should be visible only to specific groups
                    foreach ($groupPaths as $customint2 => $path) {
                        if ($topicRecord->open_path === $path) {
                            // $allConditions[] = ["type" => "group", "id" => $customint2];
                            $allConditions[] = [
                                "op" => "|",
                                "c" => [
                                    ["type" => "group", "id" => $customint2],
                                    ["type" => "role", "id" => $teacherRoleId], // Add role condition for 'teacher'
                                    ["type" => "role", "id" => $employeeRoleId] // Add role condition for 'student'
                                ]
                            ];
                        }
                    }
                }
            }

            // Remove duplicate conditions
            $uniqueConditions = array_values(array_unique($allConditions, SORT_REGULAR));

            // Create a new availability structure if conditions exist, else set null
            if (!empty($uniqueConditions)) {
                $updatedAvailability = [
                    "op" => "|",
                    "c" => $uniqueConditions,
                    "show" => false
                ];
                $availabilityJson = json_encode($updatedAvailability);
                $sectiondata->availability = $availabilityJson;
                $sectiondata->visible = 1;
            }
            // Update section with new availability and visibility settings
            course_update_section($course, $section, $sectiondata);

            // // Retrieve the list of modules (activities) in this section
            // $modules = !empty($section->sequence) ? explode(',', $section->sequence) : array();
            // if (!empty($modules)) {
            //     // Update each module's visibility to match the section's visibility
            //     foreach ($modules as $moduleid) {
            //         $DB->set_field('course_modules', 'visible', $sectiondata->visible, ['id' => $moduleid]);
            //     }
            // }
        }
    }

    public static function course_content_accessrestrictions_bkp($courseid, $groupid = false) {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        // Get all sections from the COURSESECTIONS_TABLE for the given course
        $allSections = $DB->get_records(self::COURSESECTIONS_TABLE, ['course' => $courseid]);

        // Get all topics linked to course sections in the COURSETOPICS_TABLE
        $linkedTopics = $DB->get_records(self::COURSETOPICS_TABLE, ['courseid' => $courseid]);

        // Query to get cohort sync list with open_path
        $cohortsync = "SELECT DISTINCT e.customint1 AS groupid, e.customint2, cg.open_path
                       FROM {" . self::ENROLL_TABLE . "} e
                       JOIN {" . self::COURSEGROUPS_TABLE . "} cg
                         ON e.customint1 = cg.groupid AND e.courseid = cg.courseid
                       WHERE e.courseid = :courseid AND e.enrol = :enrol";

        $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort'];
        $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);
        // Create a map of customint2 (group information) to open_path
        $groupPaths = [];
        foreach ($cohortsynclist as $cohort) {
            $groupPaths[$cohort->customint2] = $cohort->open_path;
        }
        // Create an associative array to map section IDs to topic IDs
        $sectionsByTopic = [];
        foreach ($linkedTopics as $topicRecord) {
            $sectionsByTopic[$topicRecord->topic] = $topicRecord->topic;
        }
        // Iterate over all sections
        foreach ($allSections as $section) {
            if ($section->section > 0) {
                $sectiondata = new stdClass();
                $sectiondata->id = $section->id;
                $sectiondata->section = $section->section;

                // Initialize the availability conditions
                $allConditions = [];

                // Determine if this section is linked to a topic
                if (isset($sectionsByTopic[$section->id])) {
                    $topicId = $sectionsByTopic[$section->id];
                    
                    // Get all topic records for the specific topic ID
                    $topicRecords = $DB->get_records(self::COURSETOPICS_TABLE, ['topic' => $topicId]);
                    // Iterate through each topic record
                    foreach ($topicRecords as $topicRecord) {
                        if (is_null($topicRecord->open_path)) {
                            // Admin topics (no open_path) should be visible to all groups
                            $allConditions = array_merge($allConditions, array_map(function($customint2) {
                                return ["type" => "group", "id" => $customint2];
                            }, array_keys($groupPaths)));
                        } else {
                            // HOD topics should be visible only to specific groups
                            foreach ($groupPaths as $customint2 => $path) {
                                if ($topicRecord->open_path === $path) {
                                    $allConditions[] = ["type" => "group", "id" => $customint2];
                                }
                            }
                        }
                    }
                }

                // Remove duplicate conditions
                $uniqueConditions = array_values(array_unique($allConditions, SORT_REGULAR));

                // Create a new availability structure if conditions exist, else set null
                if (!empty($uniqueConditions)) {
                    $updatedAvailability = [
                        "op" => "|",
                        "c" => $uniqueConditions,
                        "show" => false
                    ];
                    $availabilityJson = json_encode($updatedAvailability);
                    $sectiondata->availability = $availabilityJson;
                    $sectiondata->visible = 1;
                } else {
                    $sectiondata->availability = null;
                    $sectiondata->visible = 0;
                }
                // Update section with new availability and visibility settings
                course_update_section($course, $section, $sectiondata);

                // Retrieve the list of modules (activities) in this section
                $modules = !empty($section->sequence) ? explode(',', $section->sequence) : array();
                if (!empty($modules)) {
                    // Update each module's visibility to match the section's visibility
                    foreach ($modules as $moduleid) {
                        $DB->set_field('course_modules', 'visible', $sectiondata->visible, ['id' => $moduleid]);
                    }
                }



            }
        }
    }

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

    public static function get_teacher_roleid() {
        global $DB;
        return $DB->get_field('role', 'id', array('shortname' => 'teacher'));
    }

    public static function get_student_roleid() {
        global $DB;
        return $DB->get_field('role', 'id', array('shortname' => 'employee'));
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
        $cohortplugin = enrol_get_plugin('cohort');
        $enrolid = $cohortplugin->add_instance($course, $fields);
        return $enrolid;
    }

    public static function delete_cohort_sync($courseid, $coursepaths) {
        global $DB;

        // Split the coursepaths into an array and properly quote each element
        $coursepathsArray = array_map(function($path) {
            return "'" . trim($path) . "'";
        }, explode(',', $coursepaths));

        // Join the quoted paths back into a comma-separated string
        $coursepathsQuoted = implode(',', $coursepathsArray);



        $cohortsync = "SELECT e.customint1, g.open_path
                       FROM {" . self::ENROLL_TABLE . "} e
                       JOIN {" . self::LOCAL_GROUPS . "} g
                         ON e.customint1 = g.cohortid
                       WHERE g.open_path NOT IN ($coursepathsQuoted) AND e.courseid = :courseid AND e.enrol = :enrol";

        $cohortparams = ['courseid' => $courseid, 'enrol' => 'cohort'];
        $cohortsynclist = $DB->get_records_sql($cohortsync, $cohortparams);
        foreach($cohortsynclist as $cohortsynclist) {
            $DB->delete_records(self::COURSEGROUPS_TABLE, ['groupid' => $cohortsynclist->customint1, 'courseid' => $courseid, 'open_path' =>$cohortsynclist->open_path]);
            self::unenrollgroups($cohortsynclist->customint1, $courseid);
        }
    }


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
