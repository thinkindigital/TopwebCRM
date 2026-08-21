<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\TopwebChat\Jobs\ReconcileInstance;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Services\WebhookUrlService;
use Webkul\User\Models\User;

class SettingsController
{
    public function __construct(
        protected MessagingProvider $provider,
        protected WebhookUrlService $webhookUrls
    ) {}

    public function index(): View
    {
        $this->authorizeAdministrator();

        $instances = Instance::query()->orderBy('name')->get();
        $openWaInstance = $instances->first(fn (Instance $instance) => $instance->isOpenWA());
        $openWaHealth = null;
        $openWaSessions = [];
        $openWaUnavailable = false;

        if ($openWaInstance) {
            try {
                $health = $this->provider->health($openWaInstance);
                $openWaHealth = [
                    'status' => $health['status'] ?? null,
                    'version' => $health['version'] ?? null,
                ];

                $openWaSessions = array_map(fn (array $session) => [
                    'id' => $session['id'] ?? null,
                    'name' => $session['name'] ?? null,
                    'status' => $session['status'] ?? null,
                    'engine_loaded' => (bool) ($session['engineLoaded'] ?? false),
                ], $this->provider->listSessions($openWaInstance));
            } catch (\Throwable) {
                $openWaUnavailable = true;

                Log::warning('OpenWA is unavailable while loading Topweb Chat settings.', [
                    'instance_id' => $openWaInstance->id,
                ]);
            }
        }

        return view('topweb_chat::settings.index', [
            'instances' => $instances,
            'users' => User::query()->with('role')->orderBy('name')->get(),
            'openWaHealth' => $openWaHealth,
            'openWaSessions' => $openWaSessions,
            'openWaUnavailable' => $openWaUnavailable,
        ]);
    }

    public function storeInstance(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'token' => ['required', 'string', 'max:2000'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $instance = Instance::query()->firstOrNew(['name' => $data['name']]);

        $instance->fill([
            'provider' => 'ryzeapi',
            'token' => $data['token'],
            'enabled' => (bool) ($data['enabled'] ?? false),
        ]);

        if (! $instance->exists) {
            $instance->webhook_secret = Str::random(64);
        }

        $instance->save();
        ReconcileInstance::dispatchAfterResponse($instance->id);

        return back()->with('success', trans('topweb_chat::app.settings.instance_saved'));
    }

    public function configureWebhook(Instance $instance): RedirectResponse
    {
        $this->authorizeAdministrator();

        try {
            $url = $this->webhookUrls->forInstance($instance);

            $this->provider->configureWebhook(
                $instance,
                $url,
                'Bearer '.$instance->webhook_secret
            );

            $instance->update(['last_synced_at' => now()]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', trans('topweb_chat::app.settings.webhook_configured'));
    }

    public function reconcileInstance(Instance $instance): RedirectResponse
    {
        $this->authorizeAdministrator();

        abort_unless($instance->enabled, 422);

        ReconcileInstance::dispatch($instance->id);

        return back()->with('success', trans('topweb_chat::app.settings.reconciliation_queued'));
    }

    public function updateSensitiveAccess(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator();

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'can_view_sensitive_data' => ['required', 'boolean'],
        ]);

        User::query()
            ->whereKey($data['user_id'])
            ->update(['can_view_sensitive_data' => $data['can_view_sensitive_data']]);

        return back()->with('success', trans('topweb_chat::app.settings.access_updated'));
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->guard('user')->user()?->role?->permission_type === 'all',
            403
        );
    }
}
