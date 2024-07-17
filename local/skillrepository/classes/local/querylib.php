<?php
namespace local_skillrepository\local;
defined('MOODLE_INTERNAL') || die();
class querylib {
    private $db;

    private $user;

    public function __construct(){
        global $DB, $USER;
        $this->db = $DB;
        $this->user = $USER;
    }
    public function insert_update_level($formdata){
        if($formdata->id){
            $formdata->usermodified = $this->user->id;
            $formdata->timemodified = time();
            $this->db->update_record('local_course_levels', $formdata);
        }else{
            $formdata->usercreated = $this->user->id;
            $formdata->costcenterid = $formdata->costcenterid;
            $formdata->timecreated = time();
            $this->db->insert_record('local_course_levels', $formdata);
        }
    }

    public function get_table_contents($params){
        $params = (object)$params;
        $contentsql = "SELECT lcl.id,lcl.name,lcl.code, concat(u.firstname,' ', u.lastname) as username, lc.fullname as organisationname
            FROM {local_course_levels} AS lcl
            JOIN {user} AS u ON u.id=lcl.usercreated
            JOIN {local_costcenter} AS lc ON concat('/',lcl.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1
            WHERE 1=1 ";

        if(!is_siteadmin()){
        $concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='sk.open_path');

            //For Organization head show only those levels created by them.
            //$costcenterid=$this->user->open_costcenterid;
            $contentsql .=" $concatsql ";
        }
        if($params->search){
            $contentsql .= " AND (lcl.name LIKE '%%{$params->search}%%' OR lcl.code LIKE '%%{$params->search}%%')";
        }
        $contentsql .=" ORDER BY lcl.id desc";
        if (isset($params->recordsperpage) && $params->perpage != '-1'){
            $content = $this->db->get_records_sql($contentsql, array(), $params->recordsperpage, $params->perpage);
        }else{
            $content = $this->db->get_records_sql($contentsql);
        }
        return $content;
    }

    public function get_total_levels_count($params){
        $params = (object)$params;
        $countsql = "SELECT count(id) FROM {local_course_levels} WHERE 1=1 ";
        if($params->search){
            $countsql .= " AND (name LIKE '%%{$params->search}%%' OR code LIKE '%%{$params->search}%%')";
        }
        $count = $this->db->count_records_sql($countsql);
        return $count;
    }
    public function delete_level($levelid){
        return $this->db->delete_records('local_course_levels',array('id' => $levelid));
    }
    public function can_delete_level($levelid){
        return true;
    }
    public function can_edit_level($levelid){
        return true;
    }
    public function insert_update_competencylevel_skill($formdata){
		global $DB;
		if($formdata->skillid){
			foreach($formdata->skillid AS $skillid){
				$formdata->competencylevelid = $formdata->complevelid;
				$formdata->skillid = $skillid;
				$formdata->skilllevelid = $formdata->skilllevelid;
				$rec = $DB->get_record('local_comp_skill_mapping', array('costcenterid'=>$formdata->costcenterid,'competencyid'=>$formdata->competencyid,'competencylevelid'=>$formdata->complevelid,'skillid'=>$formdata->skillid,'skilllevelid'=>$formdata->skilllevelid));
				if(!empty($rec)){
					$formdata->usermodified = $this->user->id;
					$formdata->timemodified = time();
					$formdata->id=$rec->id;
					$id = $this->db->update_record('local_comp_skill_mapping', $formdata);
				} else {
					$formdata->usercreated = $this->user->id;
					$formdata->timecreated = time();
					$id = $this->db->insert_record('local_comp_skill_mapping', $formdata);

				}
			}
			return $id;
		}
	}
    public function insert_update_skillcourse($formdata){
		global $DB;
		if($formdata->course){
			foreach($formdata->course AS $courseid){
				$formdata->usermodified = $this->user->id;
				$formdata->timemodified = time();
				$formdata->id = $courseid;
				$formdata->open_skill = $formdata->skillid;
				$formdata->open_level = $formdata->levelid;
				$id = $this->db->update_record('course', $formdata);
			}
			return $id;
		}
	}
	//inserting from competency
    public function insert_competencycourse($formdata){
		global $DB;
		if($formdata->course){
			foreach($formdata->course AS $courseid){

				$formdata->usercreated = $this->user->id;
				$formdata->timecreated = time();
				$formdata->courseid = $courseid;
				if(!isset($formdata->competencyid))
				{
					$formdata->competencyid = $formdata->skill_categoryid;
				}
				$compcourse = $DB->get_record('local_comp_course_mapping', array('competencyid'=>$formdata->competencyid, 'skillid'=>$formdata->skillid, 'levelid'=>$formdata->levelid, 'courseid'=>$courseid));

				if(empty($compcourse))
				{
					$id = $this->db->insert_record('local_comp_course_mapping', $formdata);
				}
			}
			return $id;
		}
	}
    public function insert_update_skilllevel($formdata){
		global $DB;
		if($formdata->levelid){
			foreach($formdata->levelid AS $level){
				$formdata->usercreated = $this->user->id;
				$formdata->timecreated = time();
				$formdata->levelid = $level;
				if(empty($DB->get_record('local_skill_levels', array('skillid'=>$formdata->skillid,'costcenterid'=>$formdata->costcenterid,'levelid'=>$level,'competencyid'=>$formdata->competencyid)))){
					$id = $this->db->insert_record('local_skill_levels', $formdata);
				}
			}
			return $id;
		}
	}
    public function get_positions($costcenterid, $domainid){
		global $DB;
		$sql = "SELECT * FROM {local_positions} WHERE 1=1 ";
		if(!empty($costcenterid)) {
			$sql .= " AND costcenter=$costcenterid";
		}
		if(!empty($domainid)) {
			$sql .= " AND domain=$domainid";
		}
		$sql .= " ORDER BY path, sortorder DESC";
		$positions = $DB->get_records_sql($sql);
		return $positions;
	}
    public function get_competencies($costcenterid){
		global $DB;
		$concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $path = "/".$costcenterid;

		$sql = "SELECT * FROM {local_skill_categories} WHERE 1=1 AND open_path = '".$path."'";
		// if(!empty($costcenterid)) {
		// 	$sql .= " AND costcenterid={$costcenterid}";
		// }
		$sql .= " ORDER BY sortorder Asc";
		$competencies = $DB->get_records_sql($sql);
		return $competencies;
	}

	public function get_skills($costcenterid, $competencyid){
		global $DB;
		$sql = "SELECT * FROM {local_skill} WHERE 1=1";
		if(!empty($costcenterid)) {
			$sql .= " AND costcenterid={$costcenterid}";
		}
		if(!empty($competencyid)) {
			$sql .= " AND category=$competencyid";
		}
		$skills = $DB->get_records_sql($sql);
		return $skills;
	}

	public function get_skillmatrix($costcenterid, $competencyid, $skillid, $positionid){
		global $DB;
		$sql = "SELECT * FROM {local_skillmatrix} WHERE costcenterid=$costcenterid AND skill_categoryid = $competencyid AND skillid = $skillid AND positionid = $positionid ";
		if(!empty($costcenterid)) {
			$sql .= " AND costcenterid={$costcenterid}";
		}
		if(!empty($competencyid)) {
			$sql .= " AND skill_categoryid=$competencyid";
		}
		if(!empty($skillid)) {
			$sql .= " AND skillid=$skillid";
		}
		if(!empty($positionid)) {
			$sql .= " AND positionid=$positionid";
		}
		$skills = $DB->get_record_sql($sql);
		return $skills;
	}
	public function insert_update_skillmatrix($costcenterid, $competencyid, $skillid, $positionid, $levelid, $skilllevel){
		global $DB, $USER;
		$skillmatrix = $DB->get_record('local_skillmatrix', array('costcenterid' => $costcenterid, 'skill_categoryid' => $competencyid,'skillid' => $skillid, 'positionid' => $positionid, 'levelid' => $levelid, 'skilllevel' => $skilllevel));
        if(empty($skillmatrix)) {
            $formdata = new \stdClass();
            $formdata->costcenterid = $costcenterid;
            $formdata->skill_categoryid = $competencyid;
            $formdata->skillid = $skillid;
            $formdata->positionid = $positionid;
            $formdata->levelid = $levelid;
            $formdata->skilllevel = $skilllevel;
            $formdata->usercreated = $USER->id;
            $formdata->timecreated = time();
            // $this->db->delete_records('local_skillmatrix', array('costcenterid' => $costcenterid, 'skill_categoryid' => $competencyid,'skillid' => $skillid, 'positionid' => $positionid, 'levelid' => $levelid));
            $this->db->insert_record('local_skillmatrix', $formdata);
            // return $id;
        } else {
            // $formdata = new stdClass();
            // $formdata->id = $skillmatrix->id;
            // $formdata->levelid = $levelid;
            // $formdata->usermodified = $this->user->id;
            // $formdata->timemodified = time();
            // $this->db->update_record('local_skillmatrix', $formdata);

            $this->db->delete_records('local_skillmatrix',  array('id' => $skillmatrix->id));
            // return $id;
        }
		// if(!empty($formdata->costcenterid) && !empty($formdata->skill_categoryid) && !empty($formdata->skillid) && !empty($formdata->positionid)) {
		// 	$formdata->usercreated = $this->user->id;
		// 	$formdata->timecreated = time();
		// 	if($formdata->levelid) {
		// 		$levelname = $DB->get_field('local_course_levels',  'name', array('id' => $formdata->levelid));
		// 		$formdata->levelname = $levelname;
		// 	} else {
		// 		$formdata->levelid = 0;
		// 		// $formdata->levelname = null;
		// 	}
		// 	$skillmatrix = $DB->get_record('local_skillmatrix', array('costcenterid' => $formdata->costcenterid, 'costcenterid' => $formdata->costcenterid, 'skill_categoryid' => $formdata->skill_categoryid,'skillid' => $formdata->skillid, 'positionid' => $formdata->positionid));
		// 	if(empty($skillmatrix)) {
		// 		$this->db->insert_record('local_skillmatrix', $formdata);
		// 	} else {
		// 		$formdata->id = $skillmatrix->id;
		// 		$formdata->usermodified = $this->user->id;
		// 		$formdata->timemodified = time();
		// 		$this->db->update_record('local_skillmatrix', $formdata);
		// 	}
		// }
	}

}
