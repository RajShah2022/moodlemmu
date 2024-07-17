<?php
namespace local_positions\local;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/positions/lib.php');
class querylib {
    private $db;

    private $user;

    public function __construct(){
        global $DB, $USER;
        $this->db = $DB;
        $this->user = $USER;
    }
    //Position related functions
    public function insert_update_position($formdata){
        if($formdata->id){
            $oldlevel = $this->db->get_record('local_costcenter', array('id' => $formdata->id));
            $formdata->usermodified = $this->user->id;
            $formdata->timemodified = time();
            $oldparent = $this->db->get_field('local_positions','parent', array('id'=>$formdata->id));
            if($oldparent != $formdata->parent)
            {
                if ($formdata->parent == 0) {
                    $formdata->depth = 1;
                    $formdata->path = '/'.$formdata->id;
                    $formdata->sortorder = 1;
                } else {
                    $parentpath = $this->db->get_field('local_positions', 'path', array('id'=>$formdata->parent));
                    $formdata->path = $parentpath.'/'.$formdata->id;
                }

            }
            $this->db->update_record('local_positions', $formdata);
        }else{
            $formdata->usercreated = $this->user->id;
            $formdata->timecreated = time();
            if ($formdata->parent == 0) {
                $formdata->depth = 1;
                $formdata->path = '';
                $formdata->sortorder = 1;
            } else {
                /* ---parent item must exist--- */
                $parent = $this->db->get_record('local_positions', array('id' => $formdata->parent));
                $formdata->depth = $parent->depth + 1;
                $formdata->path = $parent->path;
                // $get_mainparent = $this->db->get_record_sql("SELECT id FROM {local_positions} where costcenter={$formdata->costcenter} and domain={$formdata->domain} and parent={$formdata->parent}");
                // // print_object($get_mainparent);
                // $max_sortorder = $this->db->get_record_sql("SELECT MAX(sortorder)as sortorder FROM {local_positions} where costcenter={$formdata->costcenter} and domain={$formdata->domain} and path LIKE'%/{$get_mainparent->id}/%'");
                // if (!empty($max_sortorder)) {
                //     $sortorder=$max_sortorder->sortorder+1;
                // } else {
                //   $max_sortorder = $this->db->get_record_sql("SELECT MAX(sortorder) FROM {local_positions} where costcenter={$formdata->costcenter} and domain={$formdata->domain} and path LIKE'%/{$get_mainparent->id}%'");
                //   $sortorder=$max_sortorder->sortorder+1;
                // }
                
                $formdata->sortorder = $parent->sortorder+1;
            }
            
            $parent = $formdata->parent ?  $formdata->parent:0;

            $position = new \stdClass();
            $position->id = $this->db->insert_record('local_positions', $formdata);

            if($position->id) {
                $position = $this->db->get_record('local_positions', array('id' => $position->id));
                $parentpath = $this->db->get_field('local_positions', 'path', array('id'=>$parent));
                $path = $parentpath.'/'.$position->id;
                $position->path = $path;
                $this->db->update_record('local_positions',  $position);
            }
        }
    }

    public function get_positions_table_contents($params){
        $params = (object)$params;


        $contentsql = "SELECT * FROM {local_positions} WHERE 1=1 ";

        if(!is_siteadmin()){
            //For Organization head show only those positions created by them.
            $costcenterid=$this->user->open_costcenterid;
            $contentsql .=" AND costcenter=$costcenterid";
        }
        if($params->search){
            $contentsql .= " AND (name LIKE '%%{$params->search}%%' OR code LIKE '%%{$params->search}%%')";
        }
        $contentsql .=" ORDER BY id desc";
        if (isset($params->recordsperpage) && $params->perpage != '-1'){
            $content = $this->db->get_records_sql($contentsql, array(), $params->recordsperpage, $params->perpage);
        }else{
            $content = $this->db->get_records_sql($contentsql);
        }


        return $content;
    }

    public function get_total_positions_count($params){
        $params = (object)$params;
        $countsql = "SELECT count(id) FROM {local_positions} WHERE 1=1 ";       
        if(!is_siteadmin()){
            //For Organization head show only those positions created by them.
            $costcenterid=$this->user->open_costcenterid;
            $countsql .=" AND costcenter=$costcenterid";
        }
        if($params->search){
            $countsql .= " AND (name LIKE '%%{$params->search}%%' OR code LIKE '%%{$params->search}%%')";
        }
        $count = $this->db->count_records_sql($countsql);
        return $count;
    }
    public function delete_position($positionid){
        global $DB;
        $rec = $DB->get_record('local_positions',array('id' => $positionid));
        if($rec->parent != 0){          
            $newparentcat = $DB->get_record('local_positions',array('id' =>$rec->parent));
            $catname = $rec->name;
            $children = $this->get_children($positionid);

            if ($children) {
                foreach ($children as $childcat) {
                   $this->change_parent_raw($newparentcat,$childcat);
                }
            }

            $positions_sql = "SELECT * FROM {local_positions} WHERE (path like'%/$rec->id%') AND costcenter=$rec->costcenter AND domain=$rec->domain AND id != $rec->id order by id asc";
            $positions = $DB->get_records_sql($positions_sql);
            foreach($positions as $position){
                $record = $DB->get_record('local_positions',array('id' => $position->id));
                $newparentcat = $DB->get_record('local_positions',array('id' =>$record->parent));//self::get($positionid, MUST_EXIST, true);
                $path = $newparentcat->path.'/'.$position->id;

                $max_sortorder = $this->db->get_record_sql("SELECT sortorder as sortorder FROM {local_positions} where costcenter={$position->costcenter} and domain={$position->domain} and id={$newparentcat->id}");
                if (!empty($max_sortorder)) {
                    $sortorder=$max_sortorder->sortorder+1;
                }
                
                if (($newparentcat->depth +1) != $position->depth) {
                    $diff = $newparentcat->depth - $position->depth + 1;
                    $setdepth = ", depth = depth + $diff";
                    // $diff = $position->depth - 1;//- $next_position->depth
                    // $setdepth = ", depth = $diff";
                }
                $sql = "UPDATE {local_positions}
                       SET path = '{$path}', sortorder='{$sortorder}' $setdepth
                     WHERE id = {$position->id}";//, sortorder='{$sortorder}'
                $DB->execute($sql);
            }
            return $this->db->delete_records('local_positions',array('id' => $positionid));
        } else {
            $positions_sql = "SELECT * FROM {local_positions} WHERE (path like'%/$rec->id%') AND costcenter=$rec->costcenter AND domain=$rec->domain";
            $positions = $DB->get_records_sql($positions_sql);
            foreach ($positions as $position) {
                $this->db->delete_records('local_positions',array('id' => $position->id));
            }
            return true;
        }
    }
    public function can_delete_position($positionid){
        return true;
    }
    public function can_edit_position($positionid){
        return true;
    }
    //Domain related functions
    public function insert_update_domain($formdata){
        if($formdata->id){
            $formdata->usermodified = $this->user->id;
            $formdata->timemodified = time();
            $formdata->costcenter = $formdata->open_costcenterid;
            $this->db->update_record('local_domains', $formdata);
        }else{
            $formdata->usercreated = $this->user->id;
            $formdata->timecreated = time();
            $formdata->costcenter = $formdata->open_costcenterid;
            // print_object($formdata);exit;costcenter
            $this->db->insert_record('local_domains', $formdata);
        }
    }

    public function get_domains_table_contents($params){
        $params = (object)$params;
        $contentsql = "SELECT * FROM {local_domains} WHERE 1=1 ";

        if(!is_siteadmin()){
            //For Organization head show only those domains created by them.
            $costcenterid=$this->user->open_costcenterid;
            $contentsql .=" AND costcenter=$costcenterid";
        }
        if($params->search){
            $contentsql .= " AND (name LIKE '%%{$params->search}%%' OR code LIKE '%%{$params->search}%%')";
        }
        $contentsql .=" ORDER BY id asc";
        if (isset($params->recordsperpage) && $params->perpage != '-1'){
            $content = $this->db->get_records_sql($contentsql, array(), $params->recordsperpage, $params->perpage);
        }else{
            $content = $this->db->get_records_sql($contentsql);
        }


        return $content;
    }

    public function get_total_domains_count($params){
        $params = (object)$params;
        $countsql = "SELECT count(id) FROM {local_domains} WHERE 1=1 ";
        if(!is_siteadmin()){
            //For Organization head show only those positions created by them.
            $costcenterid=$this->user->open_costcenterid;
            $countsql .=" AND costcenter=$costcenterid";
        }
        if($params->search){
            $countsql .= " AND (name LIKE '%%{$params->search}%%' OR code LIKE '%%{$params->search}%%')";
        }
        $count = $this->db->count_records_sql($countsql);
        return $count;
    }
    public function delete_domain($domainid){
        $deletepositions =  $this->db->delete_records('local_positions',array('domain' => $domainid));
        return $this->db->delete_records('local_domains',array('id' => $domainid));
    }
    public function can_delete_domain($domainid){
        return true;
    }
    public function can_edit_domain($domainid){
        return true;
    }

    public function get_children($positionid,$options = array()) {
        global $DB;
        // $coursecatcache = cache::make('core', 'coursecat');
        // Get default values for options.
        if (!empty($options['sort']) && is_array($options['sort'])) {
            $sortfields = $options['sort'];
        } else {
            $sortfields = array('sortorder' => 1);
        }
        $limit = null;
        if (!empty($options['limit']) && (int)$options['limit']) {
            $limit = (int)$options['limit'];
        }
        $offset = 0;
        if (!empty($options['offset']) && (int)$options['offset']) {
            $offset = (int)$options['offset'];
        }

        // First retrieve list of user-visible and sorted children ids from cache.
        $child_positionid = $DB->get_record_sql("SELECT id FROM {local_positions} where parent={$positionid}");
        $sortedids = $child_positionid;
        if ($sortedids === false) {
            $sortfieldskeys = array_keys($sortfields);
            if ($sortfieldskeys[0] === 'sortorder') {
                // No DB requests required to build the list of ids sorted by sortorder.
                // We can easily ignore other sort fields because sortorder is always different.
                $sortedids = $this->get_tree($positionid);
                if ($sortedids) {
                    if ($sortfields['sortorder'] == -1) {
                        $sortedids = array_reverse($sortedids, true);
                    }
                }
            } else {
                // We need to retrieve and sort all children. Good thing that it is done only on first request.
                $records = $DB->get_records('cc.parent = :parent', array('parent' => $positionid));
                $this->sort_records($records, $sortfields);
                $sortedids = array_keys($records);
            }
        }
        if (empty($sortedids)) {
            return array();
        }

        // Now retrieive and return categories.
        if ($offset || $limit) {
            $sortedids = array_slice($sortedids, $offset, $limit);
        }
        if (isset($records)) {
            // Easy, we have already retrieved records.
            if ($offset || $limit) {
                $records = array_slice($records, $offset, $limit, true);
            }
        } else {
            list($sql, $params) = $DB->get_in_or_equal($sortedids, SQL_PARAMS_NAMED, 'id');
            $records = $DB->get_records('local_positions', array('parent' => $positionid));
        }

        $rv = array();
        foreach ($sortedids as $id) {
            if (isset($records[$id])) {
                $rv[$id] = $records[$id];//new self($records[$id]);
            }
        }
        return $rv;
    }
    public function sort_records(&$records, $sortfields) {
        if (empty($records)) {
            return;
        }
        // If sorting by course display name, calculate it (it may be fullname or shortname+fullname).
        if (array_key_exists('displayname', $sortfields)) {
            foreach ($records as $key => $record) {
                if (!isset($record->displayname)) {
                    $records[$key]->displayname = get_course_display_name_for_list($record);
                }
            }
        }
        // Sorting by one field - use core_collator.
        if (count($sortfields) == 1) {
            $property = key($sortfields);
            if (in_array($property, array('sortorder', 'id', 'visible', 'parent', 'depth'))) {
                $sortflag = core_collator::SORT_NUMERIC;
            } else if (in_array($property, array('idnumber', 'displayname', 'name', 'shortname', 'fullname'))) {
                $sortflag = core_collator::SORT_STRING;
            } else {
                $sortflag = core_collator::SORT_REGULAR;
            }
            core_collator::asort_objects_by_property($records, $property, $sortflag);
            if ($sortfields[$property] < 0) {
                $records = array_reverse($records, true);
            }
            return;
        }

        // Sort by multiple fields - use custom sorting.
        uasort($records, function($a, $b) use ($sortfields) {
            foreach ($sortfields as $field => $mult) {
                // Nulls first.
                if (is_null($a->$field) && !is_null($b->$field)) {
                    return -$mult;
                }
                if (is_null($b->$field) && !is_null($a->$field)) {
                    return $mult;
                }

                if (is_string($a->$field) || is_string($b->$field)) {
                    // String fields.
                    if ($cmp = strcoll($a->$field, $b->$field)) {
                        return $mult * $cmp;
                    }
                } else {
                    // Int fields.
                    if ($a->$field > $b->$field) {
                        return $mult;
                    }
                    if ($a->$field < $b->$field) {
                        return -$mult;
                    }
                }
            }
            return 0;
        });
    }

    public function get_tree($id) {
        global $DB;
        // Re-build the tree.
        $sql = "SELECT cc.id, cc.parent
                FROM {local_positions} cc
                ORDER BY cc.sortorder";
        $rs = $DB->get_recordset_sql($sql, array());
        $all = array(0 => array(), '0i' => array());
        $count = 0;
        foreach ($rs as $record) {
            $all[$record->id] = array();
            $all[$record->id. 'i'] = array();
            if (array_key_exists($record->parent, $all)) {
                $all[$record->parent][] = $record->id;
                if (!$record->visible) {
                    $all[$record->parent. 'i'][] = $record->id;
                }
            } else {
                // Parent not found. This is data consistency error but next fix_course_sortorder() should fix it.
                $all[0][] = $record->id;
            }
            $count++;
        }
        $rs->close();
        if (!$count) {
            // No categories found.
            // This may happen after upgrade of a very old moodle version.
            // In new versions the default category is created on install.
            $defcoursecat = self::create(array('name' => get_string('miscellaneous')));
            set_config('defaultrequestcategory', $defcoursecat->id);
            $all[0] = array($defcoursecat->id);
            $all[$defcoursecat->id] = array();
            $count++;
        }
        // We must add countall to all in case it was the requested ID.
        $all['countall'] = $count;
        if (array_key_exists($id, $all)) {
            return $all[$id];
        }
        // Requested non-existing category.
        return array();
    }

    public function change_parent_raw($newparentcat,$next_position) {
        global $DB;
        $hidecat = false;
        if (empty($newparentcat->id)) {
            $DB->set_field('local_positions', 'parent', 0, array('id' => $next_position));
            $newparent = 0;
        } else {
            $parents = preg_split('|/|', $next_position->path, 0, PREG_SPLIT_NO_EMPTY);
            array_pop($parents);

            if ($newparentcat->id == $next_position->id || in_array($next_position->id, $parents)) {
                // Can not move to itself or it's own child.
                throw new moodle_exception('cannotmovecategory');
            }
            $path = $newparentcat->path . '/' . $next_position->id;
            $setdepth = '';
            if (($newparentcat->depth +1) != $next_position->depth) {
                $diff = $newparentcat->depth + 1;//- $next_position->depth
                $setdepth = ", depth = $diff";
            }
            if($newparentcat->sortorder){
                $sortorder = $newparentcat->sortorder+1;
            }
            $sql = "UPDATE {local_positions}
                   SET path = '{$path}', parent = {$newparentcat->id} $setdepth, sortorder='{$sortorder}'
                 WHERE id = {$next_position->id}";
            // $params = array($path, $newparentcat->id, $next_position->id);
            $DB->execute($sql);
        }
    }
}
