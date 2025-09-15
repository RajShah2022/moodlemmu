<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';  // MySQL or MariaDB
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';  // Assuming MySQL is hosted locally on the Azure instance
$CFG->dbname    = 'moodleprod';  // The database name you created
$CFG->dbuser    = 'moodleuser';  // The database username
$CFG->dbpass    = 'NewPassword123!';  // The database password
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',  // Keep empty unless MySQL is running on a custom port
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_0900_ai_ci',  // Adjust collation if needed
);

// Adjust the wwwroot to your local development IP or domain:
$CFG->wwwroot   = 'https://dev.mathmeup.org';  // Replace with your Azure VM's public IP

// Set the dataroot path to where Moodle stores files:
$CFG->dataroot  = '/var/www/moodledata';  // Make sure the directory is created and writable

$CFG->admin     = 'admin';  // This is the Moodle admin directory (default is 'admin')
$CFG->forced_plugin_settings = ['block_xp' => ['enablepromoincourses' => 0]];  // Example plugin settings

$CFG->directorypermissions = 0777;  // Ensure proper directory permissions for Moodle to write data
$CFG->openai_api_key = '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';

require_once(__DIR__ . '/lib/setup.php');

// Enable debugging
//@error_reporting(E_ALL | E_STRICT);
//@ini_set('display_errors', '1');
//$CFG->debug = (E_ALL | E_STRICT);
//$CFG->debugdisplay = 1;


// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!