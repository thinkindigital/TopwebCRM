<?php

namespace Webkul\TopwebChat\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;
use Webkul\TopwebChat\Console\Commands\CloseStaleAttendances;
use Webkul\TopwebChat\Console\Commands\ProjectLeadMedia;
use Webkul\TopwebChat\Console\Commands\ReconcileTopwebChat;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Services\ConversationAccessService;

class TopwebChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/topweb-chat.php', 'topweb-chat');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->app->singleton(ConversationAccessService::class);
        $this->app->bind(MessagingProvider::class, OpenWaProvider::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CloseStaleAttendances::class,
                ProjectLeadMedia::class,
                ReconcileTopwebChat::class,
            ]);
        }
    }

    public function boot(): void
    {
        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(config('app.admin_path'))
            ->group(dirname(__DIR__).'/Routes/admin.php');

        Route::middleware('api')
            ->prefix('api/topweb-chat')
            ->group(dirname(__DIR__).'/Routes/api.php');

        $this->loadMigrationsFrom(dirname(__DIR__).'/Database/Migrations');
        $this->loadTranslationsFrom(dirname(__DIR__).'/Resources/lang', 'topweb_chat');
        $this->loadViewsFrom(dirname(__DIR__).'/Resources/views', 'topweb_chat');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('topweb-chat:reconcile')
                ->everyMinute()
                ->name('topweb-chat-instance-reconciliation')
                ->withoutOverlapping();

            $schedule->command('topweb-chat:reconcile --history')
                ->everyFiveMinutes()
                ->name('topweb-chat-history-reconciliation')
                ->withoutOverlapping();

            $schedule->command('topweb-chat:close-stale-attendances')
                ->everyMinute()
                ->name('topweb-chat-close-stale-attendances')
                ->withoutOverlapping();

            $schedule->command('topweb-chat:project-lead-media')
                ->everyFiveMinutes()
                ->name('topweb-chat-project-lead-media')
                ->withoutOverlapping();
        });

        Event::listen(
            'admin.contact.persons.view.actions.after',
            fn (ViewRenderEventManager $viewEventManager) => $viewEventManager
                ->addTemplate('topweb_chat::extensions.person-whatsapp-action')
        );

        Event::listen(
            'admin.leads.view.actions.after',
            fn (ViewRenderEventManager $viewEventManager) => $viewEventManager
                ->addTemplate('topweb_chat::extensions.lead-whatsapp-action')
        );
    }
}
