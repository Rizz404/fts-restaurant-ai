<?php

namespace App\Modules\Operations\Support;

use App\Modules\Assistant\Gateway\ModelGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Readiness for admins: database, cache, provider settings (no model call),
 * and scheduler/worker heartbeats. Only database and cache are required;
 * the AI being unavailable never takes the menu, info, or request forms down. The
 * worker heartbeat is required only when a queue other than sync is used;
 * v1 notifications are written synchronously, so it is usually optional.
 */
class Readiness
{
    public const HEARTBEAT_MAX_AGE_SECONDS = 180;

    public function __construct(private readonly ModelGateway $gateway) {}

    /**
     * @return array{ready: bool, checks: array<string, array{status: string, required: bool, detail?: mixed}>}
     */
    public function handle(): array
    {
        $checks = [
            'database' => $this->check(true, function () {
                DB::select('select 1');

                return ['status' => 'ok'];
            }),
            'cache' => $this->check(true, function () {
                $key = 'ops:readiness:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return ['status' => $ok ? 'ok' : 'failed'];
            }),
            'assistant' => $this->check(false, fn () => [
                'status' => $this->gateway->available() ? 'ok' : 'unavailable',
                'detail' => [
                    'enabled' => (bool) config('assistant.enabled'),
                    'tools_enabled' => (bool) config('assistant.tools.enabled'),
                    'model_configured' => filled(config('services.local_llm.model')),
                ],
            ]),
            // Menu photos live on the public disk and are served through the storage link.
            'storage' => $this->check(false, fn () => $this->storage()),
            'scheduler' => $this->check(false, fn () => $this->heartbeat(Heartbeat::SCHEDULER)),
            'worker' => $this->check($this->workerRequired(), fn () => $this->workerRequired()
                ? $this->heartbeat(Heartbeat::WORKER)
                : ['status' => 'not_required', 'detail' => ['queue' => config('queue.default')]]),
        ];

        $ready = collect($checks)->every(fn (array $check) => ! $check['required'] || in_array($check['status'], ['ok', 'not_required'], true));

        return ['ready' => $ready, 'checks' => $checks];
    }

    /**
     * @return array{status: string, detail: array{writable: bool, linked: bool}}
     */
    private function storage(): array
    {
        $writable = is_dir(storage_path('app/public')) && is_writable(storage_path('app/public'));
        $linked = file_exists(public_path('storage'));

        return ['status' => $writable && $linked ? 'ok' : 'unavailable', 'detail' => ['writable' => $writable, 'linked' => $linked]];
    }

    private function workerRequired(): bool
    {
        return (bool) config('restaurant.operations.worker_required', false);
    }

    /**
     * @return array{status: string, detail: array{last_seen: ?string}}
     */
    private function heartbeat(string $process): array
    {
        $last = Heartbeat::last($process);
        $fresh = $last !== null && $last->greaterThan(CarbonImmutable::now()->subSeconds(self::HEARTBEAT_MAX_AGE_SECONDS));

        return ['status' => $fresh ? 'ok' : 'stale', 'detail' => ['last_seen' => $last?->toIso8601String()]];
    }

    /**
     * @param  callable(): array<string, mixed>  $probe
     * @return array{status: string, required: bool, detail?: mixed}
     */
    private function check(bool $required, callable $probe): array
    {
        try {
            return ['required' => $required] + $probe();
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'failed', 'required' => $required];
        }
    }
}
