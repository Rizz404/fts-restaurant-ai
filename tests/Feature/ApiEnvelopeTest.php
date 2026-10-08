<?php

namespace Tests\Feature;

use App\Support\Http\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercises the shared JSON envelope with routes registered only for tests.
 */
class ApiEnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->prefix('api/v1/__test')->group(function () {
            Route::get('ok', fn () => ApiResponse::success(['value' => 1], ['next_after_id' => null]));
            Route::post('validate', fn (Request $request) => ApiResponse::success($request->validate(['message' => 'required|string|max:5'])));
            Route::get('unauthenticated', fn () => throw new AuthenticationException);
            Route::get('forbidden', fn () => throw new AuthorizationException('secret detail'));
            Route::get('missing', fn () => abort(404));
            Route::get('conflict', fn () => throw new ApiException('conflict', 409));
            Route::get('csrf', fn () => throw new TokenMismatchException);
            Route::get('limited', fn () => ApiResponse::success(null))->middleware('throttle:1,1');
            Route::get('unavailable', fn () => throw new ApiException('service_unavailable', 503));
            Route::get('crash', fn () => throw new RuntimeException('provider body: sk-secret'));
        });
    }

    public function test_success_envelope(): void
    {
        $response = $this->getJson('/api/v1/__test/ok');

        $response->assertOk()->assertExactJson([
            'schema_version' => 1,
            'data' => ['value' => 1],
            'meta' => ['request_id' => $response->headers->get('X-Request-Id'), 'next_after_id' => null],
            'error' => null,
        ]);
        $this->assertTrue(Str::isUuid($response->json('meta.request_id')));
    }

    public function test_valid_incoming_request_id_is_reused(): void
    {
        $id = (string) Str::uuid();

        $this->getJson('/api/v1/__test/ok', ['X-Request-Id' => $id])
            ->assertHeader('X-Request-Id', $id)
            ->assertJsonPath('meta.request_id', $id);

        $this->getJson('/api/v1/__test/ok', ['X-Request-Id' => 'not-a-uuid'])
            ->assertJsonPath('meta.request_id', fn ($value) => Str::isUuid($value) && $value !== 'not-a-uuid');
    }

    public function test_validation_error_envelope_has_fields(): void
    {
        $this->postJson('/api/v1/__test/validate', ['message' => 'too long'])
            ->assertStatus(422)
            ->assertJsonPath('data', null)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.message', __('ui.errors.validation_failed'))
            ->assertJsonStructure(['error' => ['fields' => ['message']]]);
    }

    public function test_status_codes_map_to_error_codes(): void
    {
        $cases = [
            'unauthenticated' => [401, 'unauthenticated'],
            'forbidden' => [403, 'forbidden'],
            'missing' => [404, 'not_found'],
            'conflict' => [409, 'conflict'],
            'csrf' => [419, 'session_expired'],
            'unavailable' => [503, 'service_unavailable'],
        ];

        foreach ($cases as $path => [$status, $code]) {
            $this->getJson("/api/v1/__test/{$path}")
                ->assertStatus($status)
                ->assertJsonPath('schema_version', 1)
                ->assertJsonPath('data', null)
                ->assertJsonPath('error.code', $code)
                ->assertJsonPath('error.fields', [])
                ->assertJsonStructure(['meta' => ['request_id']]);
        }
    }

    public function test_unknown_api_route_uses_envelope(): void
    {
        $this->getJson('/api/v1/__test/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_rate_limit_uses_envelope_and_keeps_retry_after(): void
    {
        $this->getJson('/api/v1/__test/limited')->assertOk();

        $this->getJson('/api/v1/__test/limited')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }

    public function test_server_error_hides_internal_details(): void
    {
        $response = $this->getJson('/api/v1/__test/crash');

        $response->assertStatus(500)->assertJsonPath('error.code', 'server_error');
        $this->assertStringNotContainsString('sk-secret', $response->getContent());
        $this->assertStringNotContainsString('provider body', $response->getContent());
    }

    public function test_error_messages_are_translated(): void
    {
        $this->getJson('/api/v1/__test/forbidden?lang=ja')
            ->assertJsonPath('error.message', __('ui.errors.forbidden', [], 'ja'));

        $this->getJson('/api/v1/__test/forbidden?lang=en')
            ->assertJsonPath('error.message', __('ui.errors.forbidden', [], 'en'))
            ->assertDontSee('secret detail');
    }

    public function test_invalid_locale_on_json_is_422(): void
    {
        $this->getJson('/api/v1/__test/ok?lang=fr')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_locale')
            ->assertJsonStructure(['error' => ['fields' => ['lang']]]);
    }
}
