<?php

use plugin_renderer_base;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');

class block_performance_matrix_renderer extends plugin_renderer_base
{
    public function render_performancefilters()
    {
        global $CFG,$DB;  
        require_once($CFG->dirroot.'/blocks/performance_matrix/lib.php');
        
        $performancetypes = get_performance_types();
        $periodtype = get_config('local_custom_matrix','performance_period_type');
        $enable_quarter = false;
        $enable_halfyear = false;
        if($periodtype == 1) {
            $enable_quarter = true; 
        }else if($periodtype == 2){
            $enable_halfyear = true;
        }
        $renderedtemplate = $this->render_from_template('block_performance_matrix/performancematrix', ['performancetypes' => ($performancetypes), 'enable_quarter' => $enable_quarter, 'is_admin' => is_siteadmin(), 'enable_halfyear' => $enable_halfyear]);
        
        return $renderedtemplate;
    }
}
