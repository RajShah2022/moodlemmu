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
 * @package Bizlms 
 * @subpackage block_performance_matrix
 */
require_once($CFG->dirroot . '/local/custom_matrix/lib.php');

function make_custom_content($filters = array(),$userid = '') {
    global $DB,$USER;

    $config = get_config('block_performance_matrix');
    $labels = get_lables($filters['radio_filter']); 
    $costcenter = explode('/',$USER->open_path); 
    $templateid = get_active_template($costcenter[1]);  

    //------------------------------------
    $query = "SELECT performancecatid,performancetype as fullname FROM {local_performance_matrix} WHERE  parentid = 0 AND type = 0 AND performancecatid != 0";     
    if(!is_siteadmin()){
        $query .= " AND path = :path ";
    }
    $query .=" AND templateid = :templateid";
    $int_performancetypes = $DB->get_records_sql($query,['path' => '/'.$costcenter[1],'templateid' => $templateid]);    
    
    // ------ for external categories -----// => based on type if 1 -external 
    $exquery = "SELECT performancecatid,performancetype as fullname FROM {local_performance_matrix} WHERE parentid <> 0 AND  type = 1"; 
    if(!is_siteadmin()){
        $exquery .= " AND path = :path ";
    } 
    $exquery .=" AND templateid = :templateid";          
    $ext_performancetypes = $DB->get_records_sql($exquery,['path' => '/'.$costcenter[1],'templateid' => $templateid]); 

    $performancetypes = array_merge($int_performancetypes,$ext_performancetypes);
    $series = array();
    $params = array();
    $financialyr = get_financialyear();
    $usersql = '';    
    if(isset($userid) && !empty($userid)){
        $usersql .= " AND userid = {$userid} ";
    }else if(!is_siteadmin()){
        $usersql .= " AND userid = {$USER->id} ";
    } 

    if(isset($filters['performance']) && $filters['performance'] == 2){

        $radiofilter = isset($filters['radio_filter'])? $filters['radio_filter']:'';
        $userid = (isset($userid) && !empty($userid))?$userid : $USER->id;
        $data = user_overall_score($userid,$radiofilter,$financialyr,$templateid);        
        foreach($labels as $lab){  
            if(array_key_exists($lab,$data)){
                $catpoints[] = ($data[$lab]) ? $data[$lab]->avgscore : 0;
            }else{
                $catpoints[] = 0;
            }                
        }

        foreach($data as $dd){            
            $series['Overall Score'] = $catpoints;  
        }

    }else{
        foreach( $performancetypes  as $pkey => $pval){
            $params['performancetype'] = $pval->performancecatid;
            if(isset($filters['performancetype']) && !empty($filters['performancetype'])){
                $params['performancetype'] = $filters['performancetype'];
            }

            if(isset($filters['userid']) && !empty($filters['userid'])){
                $userid = $filters['userid'];
            }

            $yearsarr = explode("-",$financialyr);
            $params['startyr'] = $yearsarr[0];
            $params['endyr'] = $yearsarr[1];

            // if filter by month or no filter --- then taking aggregate from logs table current financial yr wise
            if(isset($filters['radio_filter']) && $filters['radio_filter'] == 'month'){
               $usersql .= " AND templateid = {$templateid} ";
            }
            $finalsql = get_monthwise_graph($usersql);            

            // if filter by year --- then taking aggregate from overall table financial yr wise
            if(isset($filters['radio_filter']) && $filters['radio_filter'] == 'year'){
                $finalsql = get_yearwise_graph($usersql);
            }

            // if filter by year --- then taking aggregate from overall table quarterly wise
            if(isset($filters['radio_filter']) && $filters['radio_filter'] == 'quarter'){
                $finalsql = get_quarterwise_graph($usersql);
            }

            // if filter by year --- then taking aggregate from overall table halfyearly wise
            if(isset($filters['radio_filter']) && $filters['radio_filter'] == 'halfyearly'){                  
                $finalsql = get_halfyearly_graph($usersql);
            }
                
            $points = $DB->get_records_sql_menu($finalsql, $params);             
            $catpoints = array();           
            foreach($labels as $lab){               
                if(array_key_exists($lab,$points)){
                    $catpoints[] = (empty($points[$lab])) ? 0 : $points[$lab];
                }else{
                    $catpoints[] = 0;
                }                
            }           
            if(isset($filters['performancetype']) && !empty($filters['performancetype'])){
                    if($filters['performancetype'] == $pval->performancecatid){
                        $series[$pval->fullname] = $catpoints; 
                    }
                  
            }else{
                $series[$pval->fullname] = $catpoints;   
            }
        } 
    }
       
    return display_graph($series, $labels);
}
function get_lables($radiofilter){
    $lables = array();
    if($radiofilter == 'quarter'){
        $labels = get_quarters();    
    }else if($radiofilter == 'year'){
        $labels = get_lastfiveyear();  
    }else if($radiofilter == 'half'){
         $labels = get_halfyears(); 
    }else{
        $labels = array( 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December','January', 'February', 'March');
    }
    return $labels;
}

function user_overall_score($userid,$radiofilter,$financialyr,$templateid){
     global $USER,$DB;
    $yearsarr = explode("-",$financialyr);
    $startyr = $yearsarr[0];
    $endyr = $yearsarr[1];
    $role = user_designation_position($userid);
    $userpath = $USER->open_path;
    $user_path = explode('/',$userpath);
    $upath = '/'.$user_path[1];
    $querylib = new local_custom_matrix\querylib();
    $categories = $querylib->performance_matrix_all(array('year' => $startyr,'role'=> $role,'path' => $upath));

    $series = [];
    
    foreach($categories as $cat){
         $finalquery = get_overall_score_records($radiofilter,$templateid);

        if($cat->performancecatid == 0 && $cat->type==0){
            $weightage = $cat->weightage;  
            $query = "SELECT SUM(maxscore) as totalmaxscore FROM {local_performance_matrix} WHERE year=:year AND parentid=:parentid AND performancecatid != 0 AND role =:role AND type=:type AND path=:path GROUP BY parentid";
            $maxscore_data = $DB->get_record_sql($query,array('year' => $startyr,'role'=> $role,'path' => $upath,'parentid' => $cat->performancecatid,'type'=>0));
            $total_maxscore = $maxscore_data->totalmaxscore;
           
            $records = $DB->get_records_sql($finalquery,array('startyr' => $startyr,'endyr' => $endyr,'role'=> $role,'parentid' => $cat->performancecatid,'userid' => $userid,'templateid' => $templateid));            
            foreach($records as $key=>$recd){
                $recd->avgscore = ($recd->totalpoints / $total_maxscore)*$weightage;
                
                $series[$key] = $recd;
                
            }
            
        }
        if($cat->parentid == 0 && $cat->type==1){
            $weightage = $cat->weightage;
            $query = "SELECT SUM(maxscore) as totalmaxscore FROM {local_performance_matrix} WHERE year=:year AND parentid=:parentid AND performancecatid != 0 AND role =:role AND type=:type AND path=:path GROUP BY parentid";
            $params1 = array('year' => $startyr,'role'=> $role,'path' => $upath,'parentid' => $cat->performancecatid,'type'=>1);
            $maxscore_data = $DB->get_record_sql($query,$params1);
            $total_maxscore = $maxscore_data->totalmaxscore;
            $params2 =  array('startyr' => $startyr,'endyr' => $endyr,'role'=> $role,'parentid' => $cat->performancecatid,'userid' => $userid,'templateid' => $templateid);
            
            $records = $DB->get_records_sql($finalquery,$params2);         
            foreach($records as $key=>$recd){
                $recd->avgscore = ($recd->totalpoints / $total_maxscore)*$weightage;
                if(array_key_exists($key,$series)){
                    $series[$key]->avgscore = $series[$key]->avgscore + $recd->avgscore;
                }else{
                   $series[$key] = $recd; 
                }
            }
        }
        
    }
    return $series;
}

function get_overall_score_records($radiofilter){
    $finalsql = "";
    if($radiofilter == 'quarter'){
        $selectsql = "SELECT period ,SUM(totalpoints) as totalpoints FROM {local_performance_overall} WHERE userid=:userid AND role=:role AND parentid=:parentid " ;
        $groupbysql = " GROUP BY period,financialyear,templateid ";
        $finalsql = $selectsql.$groupbysql;
    }else if($radiofilter == 'halfyearly'){
        $selectsql = "SELECT period ,SUM(totalpoints) as totalpoints FROM {local_performance_overall} WHERE userid=:userid AND role=:role AND parentid=:parentid " ;
        $groupbysql = " GROUP BY period,financialyear,templateid ";
        $finalsql = $selectsql.$groupbysql;
    }
    else if($radiofilter == 'year'){
        $selectsql = "SELECT financialyear ,SUM(totalpoints) as totalpoints FROM {local_performance_overall} WHERE userid=:userid AND role=:role AND parentid=:parentid " ;
        $groupbysql = " GROUP BY financialyear,templateid ";
        $finalsql = $selectsql.$groupbysql; 

    }else{
        $selectsql = "SELECT month, CASE WHEN SUM(pointsachieved) > SUM(maxpoints) THEN SUM(maxpoints) ELSE SUM(pointsachieved) END AS  totalpoints FROM {local_performance_logs} WHERE userid=:userid AND role=:role AND parentid=:parentid " ;
        $selectsql .= " AND templateid = :templateid";
        $selectsql .= "AND STR_TO_DATE(CONCAT(year,month, '01'), '%Y%M%d') between (STR_TO_DATE(CONCAT(:startyr,'April', '01'), '%Y%M%d')) AND (STR_TO_DATE(CONCAT(:endyr,'March', '01'), '%Y%M%d')) ";     
        $groupbysql = " GROUP BY month,financialyear,templateid ";    
        $finalsql = $selectsql.$groupbysql;

    }
    return $finalsql;   
}


function get_monthwise_graph($usersql){

    $selectsql = "SELECT month, CASE WHEN SUM(pointsachieved) > max(maxpoints) THEN max(maxpoints) ELSE SUM(pointsachieved) END AS  totalpoints FROM {local_performance_logs} WHERE  performancecatid = :performancetype" ;    
    $selectsql .= " AND STR_TO_DATE(CONCAT(year,month, '01'), '%Y%M%d') between (STR_TO_DATE(CONCAT(:startyr,'April', '01'), '%Y%M%d')) AND (STR_TO_DATE(CONCAT(:endyr,'March', '01'), '%Y%M%d')) ";     
    $groupbysql = " GROUP BY month,financialyear,templateid ";    
    $finalsql = $selectsql.$usersql.$groupbysql;
    return $finalsql;
}

function get_quarterwise_graph($usersql){
    $selectsql = "SELECT period ,SUM(totalpoints) as totalpoints FROM {local_performance_overall} WHERE  performancecatid = :performancetype " ;
    $groupbysql = " GROUP BY period,financialyear,templateid ";
    $finalsql = $selectsql.$usersql.$groupbysql;  
    return $finalsql;
}

function get_halfyearly_graph($usersql){    
    $selectsql = "SELECT period ,SUM(totalpoints) as totalpoints FROM {local_performance_overall} WHERE  performancecatid = :performancetype " ;
    $groupbysql = " GROUP BY period,financialyear,templateid ";
    $finalsql = $selectsql.$usersql.$groupbysql;  
    return $finalsql;
}

function get_yearwise_graph($usersql){
    $selectsql = "SELECT financialyear ,SUM(totalpoints) as totalpoints FROM {local_performance_overall} WHERE  performancecatid = :performancetype " ;
    $groupbysql = " GROUP BY financialyear,templateid ";
    $finalsql = $selectsql.$usersql.$groupbysql;  
    return $finalsql;
}

/*
* Display graph with line chart
*/

function display_graph_multiple($seriesvalue, $labels) {
    global $OUTPUT;
    $chart = new \core\chart_line();

    if(count($seriesvalue) > 0){
        foreach($seriesvalue as $key=>$val){
            $series = new \core\chart_series($key,$val);
            $chart->add_series($series);
        }
    } 
    
    if(!empty($labels)){
        $chart->set_labels($labels);
    }
    return $chart;   
}


/*
* Display graph with configured graph type from settings 
*/

function display_graph($seriesvalue, $labels) {
    global $CFG;
    
    $config = get_config('block_performance_matrix');
    
    $chart = new \core\chart_line();   
    if ($config->barcolor == '') {
        $chartcolour  = '#2385E5';
    } else {
        $chartcolour = $config->barcolor;
    }
   $chartcolour = rand_color(count($seriesvalue));

    if (isset($config->graphtype) && !empty($config->graphtype)) {
        $chart = $config->graphtype;       
        if ($chart  == 'line') {
            $chart = new \core\chart_line();           
        } else if($chart == 'bar'){
            $chart = new \core\chart_bar();
        }else {
            $CFG->chart_colorset = [$chartcolour];
        }
    }

    if(count($seriesvalue) > 0){
        foreach($seriesvalue as $key=>$val){
            $series = new \core\chart_series($key,$val);
            $chart->add_series($series);
        }
    } 

    if(!empty($labels)){
        $chart->set_labels($labels);
    }
    return $chart;
} 

function rand_color($len) {
    $colors = [];
    for($x = 1; $x <= $len; $x++){
        $rand = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f');
        $colors[] = '#'.$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)];
    }
    return $colors;
}

function get_financialyear(){
    if (date('m') >= 4) {
        $year = date('Y')."-".(date('Y') +1);
    }
    else {
        $year = (date('Y')-1)."-".date('Y');
    }
    return $year;
}

function get_lastfiveyear(){
    $years = array();
    $curryear = get_financialyear();

    for($i = 0; $i < 5; $i++){
       
        $years[$i] = $curryear;
        $yearsarr = explode("-",$curryear);
        $curryear = ($yearsarr[0]-1)."-".($yearsarr[1] -1);        
    }
    sort($years);
    return $years;
}

function get_quarters(){
    $quarters = array('Q1','Q2','Q3','Q4');   
    return $quarters;
}

function get_halfyears(){
    $halfyears = array('H1','H2');   
    return $halfyears;
}


function get_performance_types(){
    global $DB,$USER;
    // ---- for internal categories ---// => based on type if 0 -internal 
    $query = "SELECT id, fullname, shortname, parentid, type FROM {local_custom_category} WHERE  parentid = 0 AND type = 0 "; 
    $costcenter = explode('/',$USER->open_path); 
        
    if(!is_siteadmin()){
        $query .= " AND costcenterid = :costcenterid ";
    }
    
    $parents = $DB->get_records_sql($query,['costcenterid' => $costcenter[1]]);  
    $performancetypes = [];
    $parentdata = [];$childata = [];
    $parentdata['parentname'] = 'Performance/Learning Type';            
      
    foreach($parents as $parent ){
        $childata[] = $parent;
        $parentdata['child'] =  $childata;            
       
    }
    $performancetypes[] =(object) $parentdata;  
    
    // ------ for external categories -----// => based on type if 1 -external 
    $query = "SELECT id, fullname,parentid, type FROM {local_custom_category} WHERE  parentid = 0 AND type = 1"; 
    if(!is_siteadmin()){
       $query .= " AND costcenterid = :costcenterid ";
    }
           
    $parents = $DB->get_records_sql($query,['costcenterid' => $costcenter[1]]);
   
    $parentdata = [];
   
    foreach($parents as $parent ){
        $parentdata['parentname'] = $parent->fullname;   
        $childata = [];
        $parentid = $parent->id;

        $cquery = "SELECT id, fullname ,shortname, parentid FROM {local_custom_category} WHERE costcenterid = :costcenterid AND parentid = :parentid ";  
       
        $children = $DB->get_records_sql($cquery, ['costcenterid' => $costcenter[1], 'parentid' => $parentid]); 
        foreach($children as $child){
            $childata[] = $child;
        }
      
        $parentdata['child'] =  $childata; 
        $performancetypes[] =(object) $parentdata;
    }

    return $performancetypes;
}
