<?php


defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'report/ai:view' => [
        'riskbitmask'   => RISK_PERSONAL,
        'captype'       => 'read',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => [
            'manager'        => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ]
    ],
    
    'report/ai:generate' => [
        'riskbitmask'   => RISK_PERSONAL | RISK_SPAM,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => [
            'manager'        => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ]
    ],
    
    'report/ai:delete' => [
        'riskbitmask'   => RISK_PERSONAL,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => [
            'manager'        => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
        ]
    ]
];