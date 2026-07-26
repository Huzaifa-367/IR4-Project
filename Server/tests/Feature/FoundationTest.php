<?php

use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

it('returns the api health envelope', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson([
            'data' => ['status' => 'ok'],
        ]);
});

it('builds accepted and error api responses', function () {
    $accepted = ApiResponse::accepted(['accepted' => 1, 'duplicates' => 0, 'rejected' => []]);
    expect($accepted)->toBeInstanceOf(JsonResponse::class)
        ->and($accepted->getStatusCode())->toBe(202);

    $error = ApiResponse::error('VALIDATION_FAILED', 'Invalid', ['field' => ['Required']], 422);
    expect($error->getStatusCode())->toBe(422)
        ->and($error->getData(true))->toMatchArray([
            'error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'Invalid',
                'details' => ['field' => ['Required']],
            ],
        ]);
});

it('reads settings defaults from config when unset', function () {
    $service = app(SettingsService::class);

    expect($service->get('general.timezone'))->toBe('Asia/Riyadh')
        ->and($service->get('auth.session_timeout_minutes'))->toBe(15);
});

it('persists settings overrides', function () {
    $service = app(SettingsService::class);

    $service->set('general.timezone', 'UTC');

    expect($service->get('general.timezone'))->toBe('UTC');
});
