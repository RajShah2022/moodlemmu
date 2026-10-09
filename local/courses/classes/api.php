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

                        // Remove the topic from the list to be deleted. Keyed by the
                        // section id, not the raw form key ("section_123").
                        unset($existingtopicids[$topicid][$USER->open_path]);
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
                        // Which classes have content in this section, before it goes.
                        $affected = self::topic_content_by_class($courseid, $topicid);

                        // Delete all records for this topic if it is not selected by the admin.
                        foreach ($paths as $pathid) {
                            $DB->delete_records(self::COURSETOPICS_TABLE, ['id' => $pathid]);
                        }
                        // Scoped by courseid: these two are keyed on the section id, which is
                        // unique per course, but an unscoped delete here would reach into any
                        // course that happened to share the value. Hoisted out of the path loop
                        // because an admin clears every path at once, so running it per path
                        // only repeated the same delete.
                        $DB->delete_records(self::COURSEGROUPTOPICS_TABLE,
                            ['courseid' => $courseid, 'topic' => $topicid]);
                        $DB->delete_records(self::COURSEGROUPMODULES_TABLE,
                            ['courseid' => $courseid, 'topicid' => $topicid]);

                        foreach ($affected as $cohortid => $moduleids) {
                            self::revoke_class_availability($courseid, $cohortid, $moduleids, [$topicid]);
                        }
                    }
                }
            } else {
                // Non-admins should only delete their unchecked topics for their path.
                foreach ($existingtopicids as $topicid => $paths) {
                    if (!isset($selectedtopics[$topicid]) && isset($paths[$USER->open_path])) {
                        $affected = self::topic_content_by_class($courseid, $topicid, $USER->open_path);

                        $DB->delete_records(self::COURSETOPICS_TABLE, ['id' => $paths[$USER->open_path]]);
                        // Scoped to this course and this user's path, to match the
                        // COURSETOPICS_TABLE delete above. Unscoped, a single non-admin
                        // unchecking one topic wiped that section's configuration for every
                        // class and every other path, in every course sharing the section id.
                        $DB->delete_records(self::COURSEGROUPTOPICS_TABLE,
                            ['courseid' => $courseid, 'topic' => $topicid, 'open_path' => $USER->open_path]);
                        $DB->delete_records(self::COURSEGROUPMODULES_TABLE,
                            ['courseid' => $courseid, 'topicid' => $topicid, 'open_path' => $USER->open_path]);

                        foreach ($affected as $cohortid => $moduleids) {
                            self::revoke_class_availability($courseid, $cohortid, $moduleids, [$topicid]);
                        }
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
                        // Record what this class has before the rows go, so the group
                        // conditions they put on the course can be removed afterwards.
                        // Deleting the rows alone is what left content reachable by
                        // students with every teacher toggle reading off.
                        list($oldmodules, $oldtopics) = self::class_content_ids($courseid, $groupid);

                        // Delete all records for this topic if it is not selected by the admin.
                        foreach ($paths as $pathid) {
                            $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $pathid]);
                            // MUST stay scoped by courseid. Without it, unchecking a class in
                            // one course deleted that cohort's content configuration in every
                            // course it was configured for, while leaving the group availability
                            // on course_modules intact - students kept access, every teacher
                            // toggle went off and the grade report reported no enabled content.
                            $DB->delete_records(self::COURSEGROUPTOPICS_TABLE,
                                ['courseid' => $courseid, 'groupid' => $groupid]);
                            $DB->delete_records(self::COURSEGROUPMODULES_TABLE,
                                ['courseid' => $courseid, 'groupid' => $groupid]);
                            $sql = "componentid = :componentid AND courseid = :courseid AND status = :pending";
                            $params = [
                                'componentid' => $groupid,
                                'courseid' => $courseid,
                                'pending' => 'PENDING',
                            ];
                            // Delete the records with matching componentid, courseid, and status.
                            $DB->delete_records_select(self::ENROLLMENT_REQUESTS_TABLE, $sql, $params);
                        }
                        self::revoke_class_availability($courseid, $groupid, $oldmodules, $oldtopics);
                        self::unenrollgroups($groupid, $courseid);
                        // self::remove_hod_from_group($groupid);
                    }
                }
            } else {
                // Non-admins should only delete their unchecked topics for their path.
                foreach ($existinggroupids as $groupid => $paths) {
                    if (!isset($selectedgroups[$groupid]) && isset($paths[$USER->open_path])) {
                        list($oldmodules, $oldtopics) = self::class_content_ids(
                            $courseid, $groupid, $USER->open_path);
                        $DB->delete_records(self::COURSEGROUPS_TABLE, ['id' => $paths[$USER->open_path]]);
                        // Scoped to this course and this user's path, to match the
                        // COURSEGROUPS_TABLE delete above: other paths keep their own
                        // COURSEGROUPS row, so the class is still configured for them and
                        // their content configuration must survive.
                        $DB->delete_records(self::COURSEGROUPTOPICS_TABLE,
                            ['courseid' => $courseid, 'groupid' => $groupid, 'open_path' => $USER->open_path]);
                        $DB->delete_records(self::COURSEGROUPMODULES_TABLE,
                            ['courseid' => $courseid, 'groupid' => $groupid, 'open_path' => $USER->open_path]);
                            $sql = "componentid = :componentid AND courseid = :courseid AND status = :pending";
                            $params = [
                                'componentid' => $groupid,
                                'courseid' => $courseid,
                                'pending' => 'PENDING',
                                'createdbyid' => $USER->id,
                            ];
                            // Delete the records with matching componentid, courseid, and status.
                            $DB->delete_records_select(self::ENROLLMENT_REQUESTS_TABLE, $sql, $params);
                            self::revoke_class_availability($courseid, $groupid, $oldmodules, $oldtopics);
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

            // Every module's real section. The activity branch below used to take its
            // topicid from whichever section_* key the loop happened to have seen last,
            // so an activity processed out of order was filed under a foreign section -
            // which the form then could not load, and which made the availability
            // clean-up on delete silently do nothing.
            $modulesections = $DB->get_records_menu(self::COURSEMODULES_TABLE,
                ['course' => $courseid], '', 'id, section');

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

                    // The module's own section, never a leftover loop variable. An
                    // activity whose module no longer exists is skipped rather than
                    // written with a null topicid.
                    if (!isset($modulesections[$activityid])) {
                        continue;
                    }
                    $activitytopicid = (int)$modulesections[$activityid];

                    $selectedactivities[$activityid] = true;

                    if (isset($existingactivityids[$activityid][$USER->open_path])) {
                        $record = new stdClass();
                        $record->id = $existingactivityids[$activityid][$USER->open_path];
                        // Correct any row already filed under the wrong section.
                        $record->topicid = $activitytopicid;
                        $record->timemodified = time();
                        $record->usermodified = $USER->id;
                        $DB->update_record(self::COURSEGROUPMODULES_TABLE, $record);

                        unset($existingactivityids[$activityid][$USER->open_path]);
                    } else {
                        $record = new stdClass();
                        $record->courseid = $courseid;
                        $record->groupid = $groupid;
                        $record->topicid = $activitytopicid;
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
            // These removals are silent (raw delete_records fires no event), so log
            // them - without this there is no record of who removed what, or when.
            $removedtopics = [];
            $removedactivities = [];

            foreach ($existingtopicids as $topicid => $paths) {
                if (!isset($selectedtopics[$topicid]) && isset($paths[$USER->open_path])) {
                    $removedtopics[] = $topicid;
                    self::update_deleted_section_availability($courseid, $topicid, $groupid);
                    $DB->delete_records(self::COURSEGROUPTOPICS_TABLE, ['id' => $paths[$USER->open_path]]);
                }
            }

            foreach ($existingactivityids as $activityid => $paths) {
                if (!isset($selectedactivities[$activityid]) && isset($paths[$USER->open_path])) {
                    $removedactivities[] = $activityid;
                    self::update_deleted_module_availability($courseid, $activityid, $groupid);
                    $DB->delete_records(self::COURSEGROUPMODULES_TABLE, ['id' => $paths[$USER->open_path]]);
                }
            }

            if (!empty($removedtopics) || !empty($removedactivities)) {
                error_log(sprintf(
                    'local_courses groupcontentconfig: user %d (%s) course %d cohort %d path %s '
                        . '- removed %d/%d topics [%s] and %d/%d activities [%s]',
                    $USER->id,
                    $USER->username,
                    $courseid,
                    $groupid,
                    $USER->open_path,
                    count($removedtopics),
                    count($existingtopicids),
                    implode(',', $removedtopics),
                    count($removedactivities),
                    count($existingactivityids),
                    implode(',', $removedactivities)
                ));
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
                        // Core API keeps visibleold in sync - see update_section_availability().
                        set_coursemodule_visible($moduleid, 0, 1, false);
                        $DB->set_field('course_modules', 'availability', null, ['id' => $moduleid]);
                    }
                }
            }
        }
    }

    public static function update_section_availability($courseid) {
        global $DB;

        // Fetch teacher and employee (student) role IDs as integers
        $teacherRoleId = (int)self::get_teacher_roleid();
        $employeeRoleId = (int)self::get_student_roleid();

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
            // Default to the section's current visibility. Without this, a section
            // whose group paths do not match leaves ->visible undefined, and the
            // module loop below would read null and hide every activity.
            $sectiondata->visible = $section->visible;

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

                    if (empty($cohortsynclist)) {
                        continue; // Move to next topic record
                    }
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
                        $moduleRecord = $DB->get_record('course_modules', ['id' => $module->moduleid], 'id, availability, visibleoncoursepage');
                        $existingAvailability = !empty($moduleRecord->availability) ? json_decode($moduleRecord->availability, true) : [];

                        // If there are existing conditions, retrieve them
                        $existingConditions = isset($existingAvailability['c']) ? $existingAvailability['c'] : [];
                        foreach ($groupPaths as $customint2 => $path) {
                            if ($module->open_path === $path) {
                                // $newCondition = ["type" => "group", "id" => $customint2];

                                $newCondition = [
                                    "op" => "|",
                                    "c" => [
                                        ["type" => "group", "id" => $customint2],
                                        ["type" => "role", "id" => $teacherRoleId] // Add role condition for 'teacher'
                                    ]
                                ];

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
                            // Use the core API rather than a raw set_field: it is the only thing
                            // that keeps visibleold in sync with visible. A raw write desyncs them,
                            // and course_update_section()'s cascade then restores modules from
                            // visibleold - silently re-hiding content that was just granted.
                            set_coursemodule_visible($module->moduleid, $sectiondata->visible,
                                $moduleRecord->visibleoncoursepage, false);
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

    /**
     * Which classes have content in a section, and which modules, keyed by cohort.
     *
     * @param int $courseid
     * @param int $topicid Section id.
     * @param string|null $openpath Restrict to one path, as the non-admin deletes do.
     * @return array [cohortid => int[] moduleids]
     */
    protected static function topic_content_by_class($courseid, $topicid, $openpath = null): array {
        global $DB;

        $select = 'courseid = :courseid AND topicid = :topicid';
        $topicselect = 'courseid = :courseid AND topic = :topicid';
        $params = ['courseid' => $courseid, 'topicid' => $topicid];
        if ($openpath !== null) {
            $select .= ' AND open_path = :openpath';
            $topicselect .= ' AND open_path = :openpath';
            $params['openpath'] = $openpath;
        }

        $affected = [];
        $rs = $DB->get_recordset_select(self::COURSEGROUPMODULES_TABLE, $select, $params, '', 'id, groupid, moduleid');
        foreach ($rs as $row) {
            $affected[(int)$row->groupid][] = (int)$row->moduleid;
        }
        $rs->close();

        // A topic can be enabled for a class with no activities inside it, so the
        // cohort has to be picked up from the topic table too or its section keeps
        // the condition forever.
        foreach ($DB->get_fieldset_select(self::COURSEGROUPTOPICS_TABLE, 'DISTINCT groupid',
                $topicselect, $params) as $cohortid) {
            if (!isset($affected[(int)$cohortid])) {
                $affected[(int)$cohortid] = [];
            }
        }

        return $affected;
    }

    /**
     * The module and section ids a class currently has enabled in a course.
     *
     * @param int $courseid
     * @param int $cohortid
     * @param string|null $openpath Restrict to one path, as the non-admin deletes do.
     * @return array [int[] $moduleids, int[] $topicids]
     */
    protected static function class_content_ids($courseid, $cohortid, $openpath = null): array {
        global $DB;

        $select = 'courseid = :courseid AND groupid = :cohortid';
        $params = ['courseid' => $courseid, 'cohortid' => $cohortid];
        if ($openpath !== null) {
            $select .= ' AND open_path = :openpath';
            $params['openpath'] = $openpath;
        }

        $moduleids = $DB->get_fieldset_select(self::COURSEGROUPMODULES_TABLE,
            'DISTINCT moduleid', $select, $params);
        $topicids = $DB->get_fieldset_select(self::COURSEGROUPTOPICS_TABLE,
            'DISTINCT topic', $select, $params);

        return [array_map('intval', $moduleids), array_map('intval', $topicids)];
    }

    /**
     * Drop the group conditions left behind by content rows that have just been deleted.
     *
     * Call this AFTER the rows are gone: availability has no notion of open_path, so a
     * module still enabled for the same class under a different path must keep its
     * condition. Checking what survives is the only way to tell.
     *
     * @param int $courseid
     * @param int $cohortid
     * @param int[] $moduleids Course module ids whose rows were removed.
     * @param int[] $topicids Section ids whose rows were removed.
     */
    protected static function revoke_class_availability($courseid, $cohortid, array $moduleids, array $topicids) {
        global $DB;

        foreach (array_unique($moduleids) as $moduleid) {
            if ($DB->record_exists(self::COURSEGROUPMODULES_TABLE,
                    ['courseid' => $courseid, 'groupid' => $cohortid, 'moduleid' => $moduleid])) {
                continue;
            }
            self::update_deleted_module_availability($courseid, $moduleid, $cohortid);
        }

        foreach (array_unique($topicids) as $topicid) {
            if ($DB->record_exists(self::COURSEGROUPTOPICS_TABLE,
                    ['courseid' => $courseid, 'groupid' => $cohortid, 'topic' => $topicid])) {
                continue;
            }
            self::update_deleted_section_availability($courseid, $topicid, $cohortid);
        }
    }

    /**
     * The course groups a class (cohort) is synced into.
     *
     * The cohort enrolment instance is authoritative: customint1 is the cohort and
     * customint2 the group it feeds, which is the pairing update_section_availability()
     * uses when it writes the conditions in the first place. Falls back to
     * cohort/group membership overlap for courses enrolled some other way.
     *
     * @param int $courseid
     * @param int $cohortid The plugin stores this as "groupid" throughout.
     * @return int[]
     */
    protected static function class_group_ids($courseid, $cohortid): array {
        global $DB;

        $groupids = $DB->get_fieldset_select(self::ENROLL_TABLE, 'DISTINCT customint2',
            'courseid = :courseid AND enrol = :enrol AND customint1 = :cohortid AND customint2 > 0',
            ['courseid' => $courseid, 'enrol' => 'cohort', 'cohortid' => $cohortid]);

        if (empty($groupids)) {
            $groupids = $DB->get_fieldset_sql("
                SELECT DISTINCT g.id
                  FROM {groups} g
                  JOIN {groups_members} gm ON gm.groupid = g.id
                  JOIN {cohort_members} chm ON chm.userid = gm.userid
                 WHERE g.courseid = :courseid AND chm.cohortid = :cohortid",
                ['courseid' => $courseid, 'cohortid' => $cohortid]);
        }

        return array_values(array_unique(array_map('intval', $groupids)));
    }

    /**
     * Does this availability condition belong to one of the given groups?
     *
     * Both shapes written by this plugin are matched: the single-element block
     * sections get, ["op" => "&", "c" => [group]], and the two-element block
     * modules get, ["op" => "|", "c" => [group, teacher role]]. A block is owned
     * entirely by one class, so naming the group is enough to drop the block -
     * stripping only the group sub-condition would leave a bare teacher-role
     * clause behind, keeping the activity visible to staff forever.
     *
     * @param array $condition
     * @param int[] $groupids
     * @return bool
     */
    protected static function condition_targets_group(array $condition, array $groupids): bool {
        if (isset($condition['type'], $condition['id']) && $condition['type'] === 'group'
                && in_array((int)$condition['id'], $groupids, true)) {
            return true;
        }
        if (!empty($condition['c']) && is_array($condition['c'])) {
            foreach ($condition['c'] as $subcondition) {
                if (is_array($subcondition) && isset($subcondition['type'], $subcondition['id'])
                        && $subcondition['type'] === 'group'
                        && in_array((int)$subcondition['id'], $groupids, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Strip one class's conditions out of an availability tree, leaving every
     * other class's conditions untouched.
     *
     * @param string|null $json The availability field.
     * @param int[] $groupids Groups belonging to the class being removed.
     * @param bool $changed Set to true if anything was removed.
     * @return array The remaining top level conditions.
     */
    protected static function strip_group_conditions($json, array $groupids, &$changed): array {
        $changed = false;

        if (empty($json)) {
            return [];
        }
        $availability = json_decode($json, true);
        if (!is_array($availability) || empty($availability['c']) || !is_array($availability['c'])) {
            return [];
        }

        $kept = [];
        foreach ($availability['c'] as $condition) {
            if (is_array($condition) && self::condition_targets_group($condition, $groupids)) {
                $changed = true;
                continue;
            }
            $kept[] = $condition;
        }

        return array_values($kept);
    }

    /**
     * Remove a class's access to a section after its topic row has been unchecked.
     *
     * @param int $courseid
     * @param int $topicid Section id.
     * @param int $groupid Cohort id.
     */
    public static function update_deleted_section_availability($courseid, $topicid, $groupid) {
        global $DB;

        $section = $DB->get_record(self::COURSESECTIONS_TABLE, ['course' => $courseid, 'id' => $topicid]);
        if (!$section || empty($section->availability)) {
            return;
        }

        $groupids = self::class_group_ids($courseid, $groupid);
        if (empty($groupids)) {
            // Without the group there is no way to tell which condition belongs to
            // this class. Leaving availability alone keeps other classes working;
            // clearing it would expose the section to everybody.
            return;
        }

        $changed = false;
        $remaining = self::strip_group_conditions($section->availability, $groupids, $changed);
        if (!$changed) {
            return;
        }

        $sectiondata = new stdClass();
        $sectiondata->id = $section->id;
        $sectiondata->section = $section->section;

        if (!empty($remaining)) {
            // Other classes still have this section - only this one's clause goes.
            $sectiondata->availability = json_encode([
                "op" => "|",
                "c" => $remaining,
                "show" => false,
            ]);
        } else {
            // No class has it any more. The previous code skipped the write entirely
            // when nothing remained, which is what left sections still naming groups
            // that had been disabled long ago. Clearing availability on its own would
            // make the section available to everyone, so it is hidden at the same
            // time - course_update_section() cascades that to the activities inside
            // and records their state in visibleold, so re-enabling restores them.
            $sectiondata->availability = '';
            $sectiondata->visible = 0;
        }

        course_update_section($courseid, $section, $sectiondata);
    }

    /**
     * Remove a class's access to a module after its row has been unchecked.
     *
     * @param int $courseid
     * @param int $moduleid Course module id.
     * @param int $groupid Cohort id.
     */
    public static function update_deleted_module_availability($courseid, $moduleid, $groupid) {
        global $DB;

        // Look the module up by id alone. The previous code also matched
        // section => the stored topicid, so any row filed under the wrong section
        // returned false, the clean-up silently did nothing and the caller deleted
        // the row anyway - leaving the group restriction in place with no record of
        // it, which is how students kept access to content whose toggles read off.
        $cm = $DB->get_record(self::COURSEMODULES_TABLE, ['course' => $courseid, 'id' => $moduleid],
            'id, availability, visible, visibleoncoursepage');
        if (!$cm || empty($cm->availability)) {
            return;
        }

        $groupids = self::class_group_ids($courseid, $groupid);
        if (empty($groupids)) {
            return;
        }

        $changed = false;
        $remaining = self::strip_group_conditions($cm->availability, $groupids, $changed);
        if (!$changed) {
            return;
        }

        if (!empty($remaining)) {
            // Other classes still have this activity. The previous code blanked the
            // whole availability field and hid the module, revoking their access too.
            $DB->set_field(self::COURSEMODULES_TABLE, 'availability', json_encode([
                "op" => "|",
                "c" => $remaining,
                "show" => false,
            ]), ['id' => $cm->id]);
        } else {
            $DB->set_field(self::COURSEMODULES_TABLE, 'availability', '', ['id' => $cm->id]);
            // Core API rather than a raw set_field: it is the only thing that keeps
            // visibleold in sync with visible. A raw write desyncs them, and
            // course_update_section()'s cascade then restores modules from visibleold.
            set_coursemodule_visible($cm->id, 0, $cm->visibleoncoursepage, false);
        }

        \course_modinfo::purge_course_module_cache($courseid, $cm->id);
        rebuild_course_cache($courseid, true, true);
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
