<?php

return [
    [
        'key' => 'topwebchat',
        'name' => 'topweb_chat::app.configuration.title',
        'info' => 'topweb_chat::app.configuration.info',
        'sort' => 3,
    ], [
        'key' => 'topwebchat.appearance',
        'name' => 'topweb_chat::app.configuration.appearance.title',
        'info' => 'topweb_chat::app.configuration.appearance.info',
        'icon' => 'icon-setting',
        'sort' => 1,
    ], [
        'key' => 'topwebchat.appearance.style',
        'name' => 'topweb_chat::app.configuration.appearance.workspace-style.title',
        'info' => 'topweb_chat::app.configuration.appearance.workspace-style.info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'workspace_style',
                'title' => 'topweb_chat::app.configuration.appearance.workspace-style.title',
                'type' => 'select',
                'options' => [
                    [
                        'title' => 'topweb_chat::app.configuration.appearance.workspace-style.conversational',
                        'value' => 'R1',
                    ], [
                        'title' => 'topweb_chat::app.configuration.appearance.workspace-style.integrated',
                        'value' => 'R1K',
                    ],
                ],
            ],
        ],
    ],
];
