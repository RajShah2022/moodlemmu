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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\pluginbase;

class plugin_myonlinetests extends pluginbase {

    public function init() {
        $this->fullname = get_string('myonlinetest', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('myonlinetests');
    }

    public function summary($data) {
        return format_string($data->columname);
    }

    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }

    // Data -> Plugin configuration data.
    // Row -> Complet user row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
            global $DB, $CFG, $USER;
       switch ($data->column) {
            case 'achievedgrade':
                    $sql = "SELECT gg.finalgrade 
                            FROM {grade_grades} as gg 
                            JOIN {grade_items} as gi ON gi.id =gg.itemid 
                            WHERE gi.itemmodule = :itemmodule AND gi.courseid =:courseid AND gg.userid = :userid";
                            
                    $gradegrades = $DB->get_record_sql($sql, array( 'itemmodule'=>'quiz', 'courseid'=>$row->courseid,'userid' => $row->userid));
                    $row->{$data->column} = !is_null($gradegrades->finalgrade) ? round($gradegrades->finalgrade, 2) : '--';
                break;
            case 'completionstatus':
                /* $sql = "SELECT completionstate FROM {course_modules} as cm ON cm.course = c.id 
                            JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id AND u.id = cmc.userid
                            WHERE c.id = :courseid AND cmc.completionstate > 0 AND userid = :userid";
                $completionstatus = $DB->get_record_sql($sql, array('courseid'=>$row->courseid, 'userid' => $row->id));
                $row->{$data->column} = $completionstatus > 0 ? 'Completed' : 'Not Completed'; */
                $row->{$data->column} = (($row->{$data->column}) > 0) ? 'Completed' : 'Not Completed';
                break;
            case 'completiondate':
                $row->{$data->column} = !empty($row->{$data->column}) ? date('d-M-Y',$row->{$data->column}) : 'NA';
                break;
        }
        return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }
}
