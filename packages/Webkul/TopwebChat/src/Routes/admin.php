<?php

use Illuminate\Support\Facades\Route;
use Webkul\TopwebChat\Http\Controllers\AssignmentController;
use Webkul\TopwebChat\Http\Controllers\ConversationActivityController;
use Webkul\TopwebChat\Http\Controllers\ConversationController;
use Webkul\TopwebChat\Http\Controllers\ConversationLaunchController;
use Webkul\TopwebChat\Http\Controllers\InternalNoteController;
use Webkul\TopwebChat\Http\Controllers\LeadStageController;
use Webkul\TopwebChat\Http\Controllers\MessageController;
use Webkul\TopwebChat\Http\Controllers\SearchController;
use Webkul\TopwebChat\Http\Controllers\SettingsController;

Route::prefix('topweb-chat')->group(function () {
    Route::get('', [ConversationController::class, 'index'])->name('admin.topweb_chat.index');
    Route::post('start/person/{person}', [ConversationLaunchController::class, 'fromPerson'])->name('admin.topweb_chat.start.person');
    Route::post('start/lead/{lead}', [ConversationLaunchController::class, 'fromLead'])->name('admin.topweb_chat.start.lead');
    // V-06: antes de conversations/{conversation} para nao ser capturada pelo binding.
    Route::get('conversations/search', [SearchController::class, 'search'])->name('admin.topweb_chat.search');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('admin.topweb_chat.show');
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('admin.topweb_chat.messages.index');
    Route::post('conversations/{conversation}/client-events', [ConversationController::class, 'clientEvent'])->name('admin.topweb_chat.client_events.store');
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->name('admin.topweb_chat.messages.store');
    Route::post('conversations/{conversation}/messages/batch', [MessageController::class, 'storeBatch'])->name('admin.topweb_chat.messages.batch');
    Route::get('conversations/{conversation}/messages/{message}/media', [ConversationController::class, 'media'])->name('admin.topweb_chat.messages.media');
    Route::post('conversations/{conversation}/messages/{message}/retry', [MessageController::class, 'retry'])->name('admin.topweb_chat.messages.retry');
    Route::post('conversations/{conversation}/notes', [InternalNoteController::class, 'store'])->name('admin.topweb_chat.notes.store');
    Route::post('conversations/{conversation}/activities', [ConversationActivityController::class, 'store'])->name('admin.topweb_chat.activities.store');
    Route::put('conversations/{conversation}/activities/{activity}', [ConversationActivityController::class, 'update'])->name('admin.topweb_chat.activities.update');
    Route::post('conversations/{conversation}/activities/{activity}/complete', [ConversationActivityController::class, 'complete'])->name('admin.topweb_chat.activities.complete');
    Route::delete('conversations/{conversation}/notes/{note}', [InternalNoteController::class, 'destroy'])->name('admin.topweb_chat.notes.destroy');
    Route::put('conversations/{conversation}/assignment', [AssignmentController::class, 'update'])->name('admin.topweb_chat.assignment.update');
    Route::put('conversations/{conversation}/lead-stage', [LeadStageController::class, 'update'])->name('admin.topweb_chat.lead_stage.update');
    Route::get('conversations/{conversation}/lead-pipelines/{pipeline}/stages', [LeadStageController::class, 'stages'])->name('admin.topweb_chat.lead_stage.stages');

    Route::delete('conversations/by-person/{person}', [ConversationController::class, 'destroyByPerson'])
        ->name('admin.topweb_chat.conversations.destroy_by_person')
        ->middleware('can:topweb_chat.settings.index');

    Route::prefix('settings')->group(function () {
        Route::get('', [SettingsController::class, 'index'])->name('admin.topweb_chat.settings.index');
        Route::post('instances', [SettingsController::class, 'storeInstance'])->name('admin.topweb_chat.settings.instances.store');
        Route::delete('instances/{instance}', [SettingsController::class, 'destroyInstance'])->name('admin.topweb_chat.settings.instances.destroy');
        Route::post('instances/{instance}/webhook', [SettingsController::class, 'configureWebhook'])->name('admin.topweb_chat.settings.instances.webhook');
        Route::post('instances/{instance}/reconcile', [SettingsController::class, 'reconcileInstance'])->name('admin.topweb_chat.settings.instances.reconcile');
        Route::put('sensitive-access', [SettingsController::class, 'updateSensitiveAccess'])->name('admin.topweb_chat.settings.sensitive_access.update');
    });
});
