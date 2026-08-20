<?php

return [
    [
        'key' => 'topweb_chat',
        'name' => 'topweb_chat::app.acl.title',
        'route' => 'admin.topweb_chat.index',
        'sort' => 4,
    ], [
        'key' => 'topweb_chat.inbox',
        'name' => 'topweb_chat::app.acl.inbox',
        'route' => 'admin.topweb_chat.index',
        'sort' => 1,
    ], [
        'key' => 'topweb_chat.inbox.view',
        'name' => 'topweb_chat::app.acl.view',
        'route' => 'admin.topweb_chat.show',
        'sort' => 1,
    ], [
        'key' => 'topweb_chat.inbox.create',
        'name' => 'topweb_chat::app.acl.create',
        'route' => 'admin.topweb_chat.start.person',
        'sort' => 2,
    ], [
        'key' => 'topweb_chat.inbox.send',
        'name' => 'topweb_chat::app.acl.send',
        'route' => 'admin.topweb_chat.messages.store',
        'sort' => 3,
    ], [
        'key' => 'topweb_chat.inbox.notes',
        'name' => 'topweb_chat::app.acl.notes',
        'route' => 'admin.topweb_chat.notes.store',
        'sort' => 4,
    ], [
        'key' => 'topweb_chat.inbox.assign',
        'name' => 'topweb_chat::app.acl.assign',
        'route' => 'admin.topweb_chat.assignment.update',
        'sort' => 5,
    ], [
        'key' => 'topweb_chat.inbox.stage',
        'name' => 'topweb_chat::app.acl.stage',
        'route' => 'admin.topweb_chat.lead_stage.update',
        'sort' => 6,
    ], [
        'key' => 'topweb_chat.settings',
        'name' => 'topweb_chat::app.acl.settings',
        'route' => 'admin.topweb_chat.settings.index',
        'sort' => 2,
    ],
];
