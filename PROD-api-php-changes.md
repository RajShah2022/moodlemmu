# Prod change guide — local/courses/classes/api.php

ONE FILE. SIX EDITS. Nothing else.

Anchored on code text, not line numbers, so prod line numbers do not matter.
Find the OLD text exactly, replace with the NEW text.
Afterwards:  php -l local/courses/classes/api.php

WHY:
  Edit 1      - the 11 Sep deletion left no audit trail at all. This adds one.
  Edits 2-6   - the plugin writes course_modules.visible with raw set_field(),
                which never updates visibleold. Moodle's course_update_section()
                cascade restores modules FROM visibleold, so once they desync any
                section visibility toggle silently re-hides content. This is why
                the restored Private Gr 10 quizzes needed a manual
                UPDATE ... SET visibleold = visible.  Using set_coursemodule_visible()
                keeps them in sync permanently.


================================================================================
EDIT 1 of 6 — Audit log for the silent deletions
Function: insert_groupcontentconfig_instance()
================================================================================
FIND:
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

REPLACE WITH:
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


================================================================================
EDIT 2 of 6 — course_content_accessrestrictions(): keep visibleold in sync
================================================================================
FIND:
                    foreach ($modules as $moduleid) {
                        $DB->set_field('course_modules', 'visible', 0, ['id' => $moduleid]);
                        $DB->set_field('course_modules', 'availability', null, ['id' => $moduleid]);
                    }

REPLACE WITH:
                    foreach ($modules as $moduleid) {
                        // Core API keeps visibleold in sync - see update_section_availability().
                        set_coursemodule_visible($moduleid, 0, 1, false);
                        $DB->set_field('course_modules', 'availability', null, ['id' => $moduleid]);
                    }


================================================================================
EDIT 3 of 6 — Initialise $sectiondata->visible
Function: update_section_availability()

  !!  THIS TEXT APPEARS TWICE IN THE FILE  !!
  !!  Apply ONLY inside:
  !!      public static function update_section_availability($courseid)
  !!  Do NOT touch the identical block inside update_section_availability_bkp()
  !!  (that function has no callers anywhere).

WHY: ->visible is only assigned inside the group-path match further down. If no
     path matches, the module loop reads an undefined property (PHP 8 -> null)
     and writes every activity in that section as HIDDEN.
================================================================================
FIND:
            $sectiondata = new stdClass();
            $sectiondata->id = $section->id;
            $sectiondata->section = $section->section;

            // Initialize the availability conditions
            $allConditions = [];

REPLACE WITH:
            $sectiondata = new stdClass();
            $sectiondata->id = $section->id;
            $sectiondata->section = $section->section;
            // Default to the section's current visibility. Without this, a section
            // whose group paths do not match leaves ->visible undefined, and the
            // module loop below would read null and hide every activity.
            $sectiondata->visible = $section->visible;

            // Initialize the availability conditions
            $allConditions = [];


================================================================================
EDIT 4 of 6 — Fetch visibleoncoursepage so EDIT 5 can preserve it
Function: update_section_availability()
================================================================================
FIND:
                        $moduleRecord = $DB->get_record('course_modules', ['id' => $module->moduleid], 'id, availability');

REPLACE WITH:
                        $moduleRecord = $DB->get_record('course_modules', ['id' => $module->moduleid], 'id, availability, visibleoncoursepage');


================================================================================
EDIT 5 of 6 — update_section_availability(): keep visibleold in sync
  <-- THIS is the line that kept the restored Private Gr 10 quizzes hidden.
================================================================================
FIND:
                            $DB->set_field('course_modules', 'availability', $moduleAvailabilityJson, ['id' => $module->moduleid]);
                            $DB->set_field('course_modules', 'visible', $sectiondata->visible, ['id' => $module->moduleid]);

REPLACE WITH:
                            $DB->set_field('course_modules', 'availability', $moduleAvailabilityJson, ['id' => $module->moduleid]);
                            // Use the core API rather than a raw set_field: it is the only thing
                            // that keeps visibleold in sync with visible. A raw write desyncs them,
                            // and course_update_section()'s cascade then restores modules from
                            // visibleold - silently re-hiding content that was just granted.
                            set_coursemodule_visible($module->moduleid, $sectiondata->visible,
                                $moduleRecord->visibleoncoursepage, false);


================================================================================
EDIT 6 of 6 — update_deleted_module_availability(): keep visibleold in sync
================================================================================
FIND:
            $DB->set_field('course_modules', 'availability', '', ['id' => $cousreModulesRecord->id]);
            $DB->set_field('course_modules', 'visible', 0, ['id' => $cousreModulesRecord->id]);

REPLACE WITH:
            $DB->set_field('course_modules', 'availability', '', ['id' => $cousreModulesRecord->id]);
            // Core API keeps visibleold in sync - see update_section_availability().
            set_coursemodule_visible($cousreModulesRecord->id, 0,
                $cousreModulesRecord->visibleoncoursepage, false);


================================================================================
AFTER APPLYING
================================================================================
  php -l local/courses/classes/api.php
  php admin/cli/purge_caches.php

Verify logging: make a class-content save that removes ONE activity, then:
  grep 'local_courses groupcontentconfig' /var/log/apache2/error.log

Expected shape:
  local_courses groupcontentconfig: user 1383 (hod.private) course 360 cohort 71
  path /1/23 - removed 0/13 topics [] and 1/318 activities [86237]

A ratio where removed == total is the signature of the 11 Sep failure:
  grep -E 'removed ([0-9]+)/\1 topics' /var/log/apache2/error.log

TEST ON STAGING FIRST: set_coursemodule_visible() also propagates visibility to
grade items and toggles calendar events (course/lib.php:748-780). That is correct
Moodle behaviour and is what keeps visibleold in sync, but it IS a behaviour
change - hiding an activity via this plugin will now also hide its grade item.
Do one class-content save on staging and check the gradebook before prod.
