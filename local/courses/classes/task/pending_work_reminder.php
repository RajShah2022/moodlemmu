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

namespace local_courses\task;

use local_courses\local\pending_work_finder;

class pending_work_reminder extends \core\task\scheduled_task
{
    public function get_name()
    {
        return get_string('taskpendingworkreminder', 'local_courses');
    }

    public function execute()
    {
        global $DB;

        $corecomponent = new \core_component();
        $costcenterexist = (bool) $corecomponent::get_plugin_directory('local', 'costcenter');

        $type = 'course_pending_work';
        $typeid = $DB->get_field('local_notification_type', 'id', array('shortname' => $type));
        if (!$typeid) {
            return;
        }

        $sql = "SELECT * FROM {local_notification_info}
                WHERE notificationid = :notificationid AND active = 1
                      AND (moduleid IS NOT NULL AND moduleid <> '' AND moduleid <> '0')";
        $notifications = $DB->get_records_sql($sql, array('notificationid' => $typeid));
        foreach ($notifications as $notification) {
            $notification->costcenterid = $costcenterexist ? explode('/', $notification->open_path)[1] : 0;
            $this->send_reminders($notification, false, '', $costcenterexist);
        }

        // Exclude list for the global rule = every per-course row's courses, active or
        // NOT. This is what makes "turn it off for course X" possible: create a
        // per-course row scoped to course X and leave it inactive -- it sends nothing
        // of its own, but still pulls course X out of the global weekly reminder. Flip
        // that same row to active (with its own subject/body) to give course X a
        // custom reminder instead of silence.
        $allmoduleidsql = "SELECT moduleid FROM {local_notification_info}
                            WHERE notificationid = :notificationid
                                  AND (moduleid IS NOT NULL AND moduleid <> '' AND moduleid <> '0')";
        $allmoduleidrows = $DB->get_fieldset_sql($allmoduleidsql, array('notificationid' => $typeid));
        $excludeids = implode(',', $allmoduleidrows);

        $globalsql = "SELECT * FROM {local_notification_info}
                       WHERE notificationid = :notificationid AND active = 1
                             AND (moduleid IS NULL OR moduleid = '' OR moduleid = '0')";
        $globalnotifications = $DB->get_records_sql($globalsql, array('notificationid' => $typeid));
        foreach ($globalnotifications as $notification) {
            $notification->costcenterid = $costcenterexist ? explode('/', $notification->open_path)[1] : 0;
            $this->send_reminders($notification, true, $excludeids, $costcenterexist);
        }
    }

    private function send_reminders($notification, $global, $excludeids, $costcenterexist)
    {
        $pending = pending_work_finder::find_pending_work($notification, $global, $excludeids, $costcenterexist);
        if (!$pending) {
            return;
        }
        $coursenotification = new \local_courses\notification();
        foreach ($pending as $userid => $items) {
            $touser = \core_user::get_user($userid);
            if (!$touser || $touser->deleted || $touser->suspended) {
                continue;
            }
            $coursenotification->send_pending_work_email($touser, $items, $notification);
        }
    }
}
