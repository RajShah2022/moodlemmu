<?php

/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_courses
 */

namespace local_courses\local;

class pending_work_finder
{
    /**
     * Finds activities that are completion-tracked and not yet completed, for every
     * actively enrolled user, under one local_notification_info row.
     *
     * There is no per-user day-count gating: the weekly cadence is entirely owned by the
     * pending_work_reminder task's cron schedule (Monday 08:00), so every run simply
     * reports whatever is currently pending. reminderdays/frequencyflag/frequencydays/
     * untildays on the notification row are not used by this type.
     *
     * Scoped to mod_quiz only -- supervideo (and any other completion-tracked module)
     * is deliberately excluded, so this only ever reports pending quizzes.
     *
     * @param \stdClass $notification a local_notification_info record
     * @param bool $global true for the "all courses not explicitly listed" row
     * @param string $excludemoduleids comma separated course ids to exclude when $global
     * @param bool $costcenterexist whether local_costcenter is installed
     * @return array [userid => [ (object){courseid, coursefullname, cmid, modname, name, url} , ... ] ]
     */
    public static function find_pending_work($notification, $global = false, $excludemoduleids = '', $costcenterexist = false)
    {
        global $DB;

        $params = array();
        $sql = "SELECT ue.id AS ueid, ue.userid,
                       c.id AS courseid, c.fullname AS coursefullname,
                       cm.id AS cmid
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                JOIN {course} c ON c.id = e.courseid
                JOIN {course_modules} cm ON cm.course = c.id AND cm.completion > 0
                     AND cm.visible = 1 AND cm.deletioninprogress = 0
                JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = ue.userid
                WHERE ue.status = 0 AND c.id > 1 AND c.visible = 1
                      AND (cmc.completionstate IS NULL OR cmc.completionstate = 0)
                      AND EXISTS (SELECT 1 FROM {role_assignments} ra
                                  JOIN {context} ctx ON ctx.id = ra.contextid
                                       AND ctx.contextlevel = " . CONTEXT_COURSE . " AND ctx.instanceid = c.id
                                  JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
                                  WHERE ra.userid = ue.userid) ";

        if ($global) {
            if (trim($excludemoduleids) !== '') {
                $sql .= " AND c.id NOT IN ($excludemoduleids) ";
            }
        } else if (!empty($notification->moduleid)) {
            $sql .= " AND c.id IN ($notification->moduleid) ";
        }

        if ($costcenterexist && !empty($notification->costcenterid)) {
            $sql .= " AND concat('/', c.open_path, '/') LIKE :costcenterid ";
            $params['costcenterid'] = '%' . $notification->costcenterid . '%';
        }

        $candidates = array();
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $candidates[$r->courseid][$r->userid][] = $r->cmid;
        }
        $rs->close();

        $pending = array();
        foreach ($candidates as $courseid => $useridmap) {
            $modinfobyuser = array();
            foreach ($useridmap as $userid => $cmids) {
                $modinfo = get_fast_modinfo($courseid, $userid);
                foreach ($cmids as $cmid) {
                    if (!array_key_exists($cmid, $modinfo->get_cms())) {
                        continue;
                    }
                    $cm = $modinfo->get_cm($cmid);
                    if (!$cm->uservisible) {
                        continue;
                    }
                    $pending[$userid][] = (object) array(
                        'courseid' => $courseid,
                        'coursefullname' => $cm->get_course()->fullname,
                        'cmid' => $cmid,
                        'modname' => $cm->modname,
                        'name' => $cm->get_formatted_name(),
                        'url' => $cm->url ? $cm->url->out(false) : '',
                    );
                }
            }
        }

        return $pending;
    }
}
