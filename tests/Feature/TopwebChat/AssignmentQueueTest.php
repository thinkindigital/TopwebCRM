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

    expect($access)->toContain(
        'public function canUnassign',
        '$conversation->assigned_user_id === $user->id'
    )->and($assignment)->toContain(
        "'assigned_user_id' => ['nullable', 'integer', 'exists:users,id']",
        "['assigned_user_id' => null]"
    )->and($messages)->toContain(
        'lockForUpdate()',
        'if ($lockedConversation->assigned_user_id === null)',
        '[\'assigned_user_id\' => $user->id]'
    )->and($view)->toContain(
        'topweb_chat::app.assignment.release',
        'topweb_chat::app.assignment.release_confirm',
        '$isAdmin || $conversation->assigned_user_id === null',
        'value=""'
    )->and($repository)->toContain(
        '\'unassigned\' => $query->whereNull(\'assigned_user_id\')'
    );
});

it('includes last_error in polling render signatures for media failures', function () {
    $controller = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/ConversationController.php')
    );
    $messageController = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/MessageController.php')
    );
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($controller)->toContain('\'last_error\' => $message->last_error')
        ->and($messageController)->toContain('\'last_error\' => $message->last_error')
        ->and($view)->toContain('message.last_error');
});
