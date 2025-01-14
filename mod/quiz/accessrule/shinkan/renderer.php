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
 * @subpackage quizaccess_shinkan
 */

class quizaccess_shinkan_renderer extends plugin_renderer_base
{
    public function summary_report($courseid, $quizid, $userid, $attemptid)
    {
        global $DB;
        $sql = " SELECT qsl.*,u.email,concat(u.firstname,' ',u.lastname) as fullname 
                    FROM {quizaccess_shinkan_logs} AS qsl 
                    JOIN {course_modules} cm ON cm.instance = qsl.quizid
                    JOIN {user} AS u ON u.id = qsl.userid
                    WHERE  cm.id = :quizid ";
        // AND qsl.attemptid =:attemptid
        $params = ['quizid' => $quizid];
        $quizlogs = $DB->get_records_sql($sql, $params);
      
        if ($quizlogs) {
            foreach ($quizlogs as $log) {
                $userdetails['fullname'] = $log->fullname;
                $userdetails['email'] = $log->email;
                $userdetails['datetime'] = null;
                $userdetails['warnings'] = null;
                $userdetails['actions'] = null;

                $userdetailsarr[] = $userdetails;
            }
            return $this->render_from_template('quizaccess_shinkan/reportlogs', ['reportdata' => $userdetailsarr]);
        }
    }
}
