<?php

namespace local_custom_category;

use stdClass;

class lib {
    public function custom_category_operations($formdata) {
        global $DB, $USER;
        $data = new \stdClass();
        $costcenter = explode('/', $USER->open_path);
        $querylib = new \local_custom_category\querylib();
        $data->costcenterid = $formdata->open_costcenterid ? $formdata->open_costcenterid : $costcenter[1];
        $data->fullname = $formdata->name;
        $data->shortname = $formdata->shortname;
        $data->parentid = $formdata->parentid ? $formdata->parentid : 0;
        $data->performancestatus = $formdata->performancestatus;
        if ($formdata->parentid == 0) {
            $data->depth = $formdata->depth = 1;
        } else {
            $parent = $querylib->category_record(array('id' => $formdata->parentid));
            $data->depth = $parent->depth + 1;
        }
        $statesid = new stdClass();
        if ($formdata->id) {
            $data->id           = $formdata->id;
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            $parentpath = $querylib->category_field('path', array('id' => $formdata->parentid));
            $path = $parentpath . '/' . $formdata->id;
            $data->path = $path;
            $statesid->id = $DB->update_record('local_custom_category', $data, $returnid = true);
        } else {
            $data->timecreated  = time();
            $data->usercreated  = $USER->id;

            $statesid->id = $DB->insert_record('local_custom_category', $data, $returnid = true);

            if ($statesid->id) {
                $parentpath = $querylib->category_field('path', array('id' => $formdata->parentid));
                $path = $parentpath . '/' . $statesid->id;
                $datarecord = new \stdClass();
                $datarecord->id = $statesid->id;
                $datarecord->path = $path;
                $DB->update_record('local_custom_category',  $datarecord);
            }
        }
        return $statesid->id;
    }
}
