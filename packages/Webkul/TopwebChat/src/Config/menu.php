<?php

return [
    [
        'key' => 'topweb_chat',
        'name' => 'topweb_chat::app.menu.title',
        'route' => 'admin.topweb_chat.index',
        'sort' => 4,
        'icon-class' => 'icon-mail',
    ], [
        'key' => 'topweb_chat.inbox',
        'name' => 'topweb_chat::app.menu.inbox',
        'route' => 'admin.topweb_chat.index',
        'sort' => 1,
        'icon-class' => '',
    ], [
        'key' => 'topweb_chat.settings',
        'name' => 'topweb_chat::app.menu.settings',
        'route' => 'admin.topweb_chat.settings.index',
        'sort' => 2,
        'icon-class' => '',
    ],
];
