<?php
namespace local_onlineexams\local;
class userdashboard_content extends \block_userdashboard\content{
	public function userdashboard_menu_content(){
		$returndata = array();
		$returndata['id'] = 'onlineexams';
		$returndata['order'] = 5;
		$returndata['pluginname'] = 'local_onlineexams';
		$returndata['tabname'] = 'inprogress';
		$returndata['status'] = 'inprogress';
		$returndata['class'] = 'userdashboard_menu_link';
		$returndata['iconclass'] = 'fa fa-desktop ot_icon';
		$returndata['label'] = get_string('onlineexams', 'block_userdashboard');
		$returndata['templatename'] = 'local_onlineexams/userdashboard_content';
		return $returndata;
	}

	/******Function to the show the inprogress course names in the E-learning Tab********/
    public static function inprogress_onlineexamnames($filter_text='', $offset = 0, $limit = 10) {
        global $DB, $USER;

        $sqlquery = "SELECT course.* ";
        $sql = " FROM {course} AS course
                JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid ";

        $sql .= " WHERE ue.userid = {$USER->id} AND course.id <> 1 AND course.visible=1 ";
        // if($source == 'mobile'){
        //     $sql .= " AND course.open_securecourse != 1   ";
        // }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('course.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }

        $sql .= " AND course.id NOT IN(SELECT DISTINCT(course) FROM {course_modules} cm
        JOIN   {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id 
        WHERE cmc.userid = {$USER->id} AND cmc.completionstate > 0 ) AND course.open_coursetype = 1 AND course.open_module = 'online_exams'  ";

        $sql .= ' order by ue.timecreated desc';

        $inprogress_onlineexams = $DB->get_records_sql($sqlquery . $sql, $params, $offset, $limit);

        return $inprogress_onlineexams;

    }

    public static function inprogress_onlineexamnames_count($filter_text = ''){
        global $USER, $DB;
        $sql = "SELECT COUNT(DISTINCT(course.id))  FROM {course} AS course
            JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
            JOIN {user_enrolments} ue ON e.id = ue.enrolid
            WHERE ue.userid = {$USER->id}
            AND course.id <> 1 AND course.visible = 1 AND course.id NOT IN(SELECT DISTINCT(course) FROM {course_modules} cm
        JOIN   {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id 
        WHERE cmc.userid = {$USER->id} AND cmc.completionstate > 0 AND course = course.id) AND course.open_coursetype = 1 AND course.open_module = 'online_exams'  ";
        // if($source == 'mobile'){
        //     $sql .= " AND course.open_securecourse != 1 ";
        // }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('course.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }
        $inprogress_count = $DB->count_records_sql($sql, $params);
        return $inprogress_count;
    }
    /*********End of the Function*********/

    /******Function to the show the Completed course names in the E-learning Tab********/
    public static function completed_onlineexamnames($filter_text='', $offset = 0, $limit = 10) {
        global $DB, $USER;
        $sql = '';
        $sqlquery = "SELECT c.*";
        $sql .= " FROM {course} c
                JOIN {course_modules} as cm ON cm.course = c.id                
                JOIN {enrol} e ON c.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id AND ue.userid = cmc.userid
                WHERE ue.userid = {$USER->id}
                AND cmc.completionstate > 0 AND c.visible = 1 AND c.id > 1 AND c.open_coursetype = 1 AND c.open_module = 'online_exams' ";
        // if($source == 'mobile'){
        //    $sql .= " AND c.open_securecourse != 1 ";
        // }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('c.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }
        $sql .= " ORDER BY c.id DESC ";
       
        $onlineexamnames = $DB->get_records_sql($sqlquery . $sql, $params, $offset, $limit);
        return $onlineexamnames;
    }
    /********end of the Function****/

    public static function completed_onlineexamnames_count($filter_text = ''){
    	global $DB, $USER;

        $sql = "SELECT COUNT(DISTINCT(c.id))
                FROM {course} c
                JOIN {course_modules} as cm ON cm.course = c.id                
                JOIN {enrol} e ON c.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id AND ue.userid = cmc.userid
                WHERE ue.userid = {$USER->id}
                AND cmc.completionstate > 0 AND c.visible = 1 AND c.id > 1 AND c.open_coursetype = 1 AND c.open_module = 'online_exams'";

        // if($source == 'mobile'){
        //     $sql .= " AND c.open_securecourse != 1 ";
        // }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('c.fullname', ':coursename', false);
            $params['coursename'] = '%'.$filter_text.'%';
        }
        $completed_count = $DB->count_records_sql($sql, $params);
        return $completed_count;
    }
}
