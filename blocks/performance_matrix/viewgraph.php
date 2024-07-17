<?php

require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $CFG, $USER, $PAGE, $OUTPUT;
require_once($CFG->dirroot.'/blocks/performance_matrix/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
use context_system;
$context = context_system::instance();
require_login();

$userid = optional_param('id','', PARAM_RAW); 

$costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
$selectsql = "SELECT  concat(firstname,' ',lastname) as fullname FROM {user} AS u WHERE u.id > 2 AND u.deleted = 0 AND u.id = :id ".$costcenterpathconcatsql;
$username = $DB->get_field_sql($selectsql, array('id'=>$userid));

$PAGE->set_context($context);
$PAGE->requires->jquery();
$PAGE->requires->js('/blocks/performance_matrix/js/matrix.js');
$PAGE->requires->css('/blocks/performance_matrix/styles.css');

$PAGE->set_heading(get_string('performancegraph', 'block_performance_matrix') . ' - ' .$username );

$header = get_string('user_performance', 'block_performance_matrix');
$PAGE->set_title($header);
echo $OUTPUT->header();
$filters = array();

if(!$DB->record_exists_sql($selectsql,array('id'=>$userid))){
    throw new moodle_exception(get_string('nopermission', 'local_users'));
}

/** @var stdClass $config */
$content = '';
$renderer = $PAGE->get_renderer('block_performance_matrix');
$content = '<ul class="course_extended_menu_list">
                <li>
                    <div class="coursebackup course_extended_menu_itemcontainer">
                    <a href="'.$CFG->wwwroot .'/local/myteam/team.php" title="Back" class="course_extended_menu_itemlink">
                      <i class="icon fa fa-reply"></i>
                    </a>
                    </div>
                </li>
            </ul>
            <input type = "hidden" id = "userid" value = "'.$userid.'">';

$content .= $renderer->render_performancefilters();
$filterdata = make_custom_content($filters,$userid);

$content .= html_writer::tag('div', $OUTPUT->render($filterdata),['class' => 'block_performance_matrix_filter']);
echo $content;
echo $OUTPUT->footer();

