<?php

it('documents unassigned queue and atomic claim behavior in executable code', function () {
    $access = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Services/ConversationAccessService.php')
    );
    $assignment = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/AssignmentController.php')
    );
    $messages = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Services/MessageService.php')
    );
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );
    $repository = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Repositories/ConversationRepository.php')
    );
    $aside = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/crm-context.blade.php')
    );

    expect($access)->toContain(
        'public function canUnassign',
        '$conversation->assigned_user_id === $user->id',
        'D04: onde ha Lead, o dono e a unica autoridade operacional',
        '$lead->user_id'
    )->and($assignment)->toContain(
        "'assigned_user_id' => ['nullable', 'integer', 'exists:users,id']",
        "['assigned_user_id' => null]"
    )->and($messages)->toContain(
        'lockForUpdate()',
        'if ($lockedConversation->assigned_user_id === null)',
        'D04: com Lead, so o dono assume',
        '? $leadOwnerId'
    )->and($aside)->toContain(
        'topweb_chat::app.assignment.release',
        'topweb_chat::app.assignment.release_confirm',
        '$isAdmin || $conversation->assigned_user_id === null',
        'value=""'
    )->and($repository)->toContain(
        'whereNull(\'lead_id\')',
        'whereHas(\'lead\''
    );
});

it('exposes only the canonical error envelope for media failures', function () {
    $controller = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/ConversationController.php')
    );
    $messageController = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/MessageController.php')
    );
    $partial = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/timeline-messages.blade.php')
    );

    expect($controller)->toContain("'error' => TopwebChatError::envelope")
        ->and($messageController)->toContain("'error' => TopwebChatError::envelope")
        ->and($partial)->toContain('TopwebChatError::envelope');
});
