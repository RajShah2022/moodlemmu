<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'skryptech';
$CFG->dbuser    = 'skryptech';
$CFG->dbpass    = 'SkrypTech123$';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_0900_ai_ci',
);

$CFG->wwwroot   = 'https://app.mathmeup.org';
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';
$CFG->forced_plugin_settings = ['block_xp' => ['enablepromoincourses' => 0]];
$CFG->directorypermissions = 0777;
$CFG->openai_api_key = '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';

$CFG->draft_area_bucket_capacity = 5000;
require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
