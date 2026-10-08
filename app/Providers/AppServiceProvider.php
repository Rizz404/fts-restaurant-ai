<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Assistant\Gateway\CompatibleChatCompletionsGateway;
use App\Modules\Assistant\Gateway\ModelGateway;
use App\Modules\Experience\Support\RestaurantSettings;
use App\Modules\Identity\CurrentActor;
use App\Modules\Identity\Guest\GuestChallenge;
use App\Modules\Identity\Guest\GuestSessions;
use App\Modules\Identity\Guest\HoneypotChallenge;
use App\Modules\Identity\Presenters\AdminNavigation;
use App\Modules\Operations\Support\Heartbeat;
use App\Modules\Requests\Support\RequestAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentActor::class);
        $this->app->scoped(RestaurantSettings::class);

        // Tests and local development may swap in FakeModelGateway.
        $this->app->bind(ModelGateway::class, CompatibleChatCompletionsGateway::class);

        // Guest anti-abuse check; a CAPTCHA provider can replace the honeypot here.
        $this->app->bind(GuestChallenge::class, HoneypotChallenge::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Entry to the /admin area (admin, editor, or staff). Each page still
        // authorizes its own resource through a Policy.
        Gate::define('admin.access', fn (User $user) => app(CurrentActor::class)->for($user)->canAccessAdminArea());

        // Admin pages receive their sidebar items from server-side abilities.
        View::composer('admin.*', function ($view) {
            $user = request()->user();

            $view->with('navItems', $user === null ? [] : AdminNavigation::items(
                app(CurrentActor::class)->for($user),
                request()->route()?->getName(),
            ));
        });

        $this->registerRateLimiters();

        // /admin/requests and the staff API: current membership of an active team (role staff);
        // the admin role alone is not enough.
        Gate::define('requests.staff', fn (User $user) => RequestAccess::handledTeamIds(app(CurrentActor::class)->for($user)) !== []);

        // /admin/operations and readiness: admin role only (counts, no request content).
        Gate::define('operations.view', fn (User $user) => app(CurrentActor::class)->for($user)->isAdmin());

        // Worker heartbeat for readiness when queue workers are in use.
        Queue::looping(fn () => Heartbeat::beat(Heartbeat::WORKER));
    }

    /**
     * Named limiters (TECHNICAL_CONTRACTS, "Limit default"). Guest keys use
     * the guest session public_id and the client IP; staff keys use the user.
     * Staff login is throttled by LoginRequest (5/min per email + IP).
     */
    private function registerRateLimiters(): void
    {
        // The guest session public_id once the row exists, otherwise the server session
        // (never a shared bucket, so one guest cannot exhaust everyone's limit).
        $guestKey = fn (Request $request): string => app(GuestSessions::class)->current()?->public_id
            ?? 'session:'.($request->hasSession() ? $request->session()->getId() : $request->ip());

        // Before a guest session row exists: bounds row creation by cookie-less clients.
        RateLimiter::for('guest-session-create', fn (Request $request) => Limit::perMinute(30)->by('guest-session-create:ip:'.$request->ip()));

        // AI turns: 8/min per guest session with 30/min per IP as a backstop.
        RateLimiter::for('guest-assistant', fn (Request $request) => [
            Limit::perMinute(8)->by('guest-assistant:session:'.$guestKey($request)),
            Limit::perMinute(30)->by('guest-assistant:ip:'.$request->ip()),
        ]);

        // Guest request writes (drafts, confirmation, messages, cancel): 10/min per guest session.
        RateLimiter::for('guest-writes', fn (Request $request) => [
            Limit::perMinute(10)->by('guest-writes:session:'.$guestKey($request)),
            Limit::perMinute(60)->by('guest-writes:ip:'.$request->ip()),
        ]);

        // Reference-code lookups: 10/min per IP (failed-lookup lockout lives in LookupRequest).
        RateLimiter::for('guest-lookup', fn (Request $request) => Limit::perMinute(10)->by('guest-lookup:ip:'.$request->ip()));

        // Public read-only APIs (menu, info, availability): 30/min per IP.
        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute(30)->by('public-read:ip:'.$request->ip()));

        // Staff writes: 20/min per user.
        RateLimiter::for('staff-writes', fn (Request $request) => Limit::perMinute(20)->by('staff-writes:user:'.$request->user()?->id));
    }
}
