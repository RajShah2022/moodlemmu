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
    public const LOCAL_GROUPS = 'local_groups';
    public const ENROLLMENT_REQUESTS_TABLE = 'local_request_records';
    public const ENROLL_TABLE = 'enrol';
    public const COURSESECTIONS_TABLE = 'course_sections';

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


    public static function course_content_accessrestrictions($courseid) {
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

        // print_r($coursepathsQuoted);

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
