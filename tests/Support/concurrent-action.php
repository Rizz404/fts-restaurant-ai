<?php

/**
 * Worker process for PostgresConcurrencyTest. Boots the application in the
 * testing environment (same database as the test), waits until the shared
 * start time so both workers race, runs one action, and prints JSON.
 *
 *   php tests/Support/concurrent-action.php <scenario> <json-args> <start-unix-ms>
 */

use App\Models\GuestRequest;
use App\Models\GuestSession;
use App\Models\KnowledgeArticle;
use App\Models\RequestDraft;
use App\Models\User;
use App\Modules\Conversation\Support\AssistantBudget;
use App\Modules\Identity\Actions\UpdateUserAccess;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Knowledge\Actions\PublishRevision;
use App\Modules\Requests\Actions\ConfirmRequestDraft;
use App\Modules\Requests\Actions\TransitionRequest;
use App\Modules\Requests\Enums\RequestStatus;
use App\Support\Http\ApiException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $scenario, $json, $startAt] = $argv + [null, null, '{}', '0'];
$args = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

// Connect before the start line so both workers race on the action itself.
DB::select('select 1');
while ((int) floor(microtime(true) * 1000) < (int) $startAt) {
    usleep(500);
}

try {
    $result = match ($scenario) {
        'confirm' => (function () use ($args) {
            $guest = GuestContext::fromSession(GuestSession::findOrFail($args['session']));
            $out = app(ConfirmRequestDraft::class)->handle($guest, RequestDraft::findOrFail($args['draft']), (int) $args['revision'], $args['key']);

            return ['request' => $out['request']->public_id, 'created' => $out['created']];
        })(),
        'transition' => (function () use ($args) {
            $request = GuestRequest::findOrFail($args['request']);
            $out = app(TransitionRequest::class)->handle(User::findOrFail($args['actor']), $request, RequestStatus::from($args['status']), (int) $args['version'], $args['reason'] ?? null, $args['key']);

            return ['status' => $out['result']->status->value, 'replayed' => $out['replayed']];
        })(),
        'demote' => (function () use ($args) {
            $actor = User::findOrFail($args['actor']);
            $target = User::findOrFail($args['target']);
            app(UpdateUserAccess::class)->handle($actor, $target, [
                'name' => $target->name,
                'email' => $target->email,
                'preferred_locale' => 'id',
                'roles' => ['editor'],
                'staff_teams' => [],
                'is_active' => true,
            ]);

            return ['demoted' => $target->id];
        })(),
        'publish' => (function () use ($args) {
            $revision = app(PublishRevision::class)->handle(User::findOrFail($args['actor']), KnowledgeArticle::findOrFail($args['article']));

            return ['published' => $revision->id];
        })(),
        'budget' => (function () use ($args) {
            return DB::transaction(fn () => ['over' => AssistantBudget::consume((int) $args['session'])]);
        })(),
        default => throw new InvalidArgumentException("Unknown scenario {$scenario}"),
    };

    echo json_encode(['ok' => true] + $result);
} catch (Throwable $e) {
    $code = $e instanceof ApiException ? $e->errorCode : null;
    echo json_encode(['ok' => false, 'error' => class_basename($e), 'code' => $code, 'message' => mb_substr($e->getMessage(), 0, 200)]);
}
