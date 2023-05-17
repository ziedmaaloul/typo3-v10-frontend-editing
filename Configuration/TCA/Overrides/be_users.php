<?php
$GLOBALS['TCA']['be_users']['columns']['beuser_frontend_editing'] = [
    'label' => 'LLL:EXT:frontend_editing/Resources/Private/Language/locallang.xlf:settings.field.frontend_editing',
    "config" => [
        'renderType' => 'checkboxToggle',
        'default' => 1,
        'type' => 'check',
    ]
];

$GLOBALS['TCA']['be_users']["types"][0]["showitem"] = '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general, beuser_frontend_editing, disable, admin, username, password, mfa, avatar,usergroup, realName, email, lang, lastlogin, --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_users.tabs.rights,userMods, allowed_languages, --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_users.tabs.mounts_and_workspaces, workspace_perms, db_mountpoints, options, file_mountpoints, file_permissions, category_perms, --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_users.tabs.options,TSconfig, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, --palette--;;timeRestriction, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes, description, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,'; 
