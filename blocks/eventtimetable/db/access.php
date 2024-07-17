<?php
$capabilities = array(
    'block/eventtimetable:myaddinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'user' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/my:manageblocks'
    ),

    'block/eventtimetable:addinstance' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
    'block/eventtimetable:view_all_events' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
    'block/eventtimetable:view_current_trg_events' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
    'block/eventtimetable:eventtimetable_menu' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
    'block/eventtimetable:vieweventtimetable' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
    'block/eventtimetable:view_events_trainer' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
);
