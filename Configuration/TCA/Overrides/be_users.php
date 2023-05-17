<?php
$GLOBALS['TCA']['be_users']['columns']['beuser_frontend_editing'] = [
    'label' => 'LLL:EXT:frontend_editing/Resources/Private/Language/locallang.xlf:settings.field.frontend_editing',
    "config" => [
        'renderType' => 'checkboxToggle',
        'default' => 1,
        'type' => 'check',
    ]
];

$GLOBALS['TCA']['be_users']["types"][0]["showitem"] = str_replace('--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,','--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,beuser_frontend_editing,' , $GLOBALS['TCA']['be_users']["types"][0]["showitem"]); 
