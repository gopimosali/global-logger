<?php

namespace Gopimosali\GlobalLogger\Tests\Feature;

use Gopimosali\GlobalLogger\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class RequestCorrelationTest extends TestCase
{
    /** @test */
    public function all_logs_in_same_request_have_same_request_id()
    {
        $requestIds = [];

        Route::get('/test', function () use (&$requestIds) {
            $requestIds[] = Log::getContextManager()->getRequestId();
            Log::info('First log');

            $requestIds[] = Log::getContextManager()->getRequestId();
            Log::info('Second log');

            $requestIds[] = Log::getContextManager()->getRequestId();
            Log::error('Third log');

            return 'OK';
        });

        $this->get('/test');

        $this->assertCount(3, $requestIds);
        $this->assertEquals($requestIds[0], $requestIds[1]);
        $this->assertEquals($requestIds[1], $requestIds[2]);
    }

    /** @test */
    public function different_requests_have_different_request_ids()
    {
        $requestIds = [];

        Route::get('/test', function () use (&$requestIds) {
            $requestIds[] = Log::getContextManager()->getRequestId();
            return 'OK';
        });

        $this->get('/test');
        $this->get('/test'); // Second request

        $this->assertCount(2, $requestIds);
        $this->assertNotEquals($requestIds[0], $requestIds[1]);
    }

    /** @test */
    public function request_id_is_included_in_response_headers()
    {
        Route::get('/test', function () {
            return 'OK';
        });

        $response = $this->get('/test');

        $response->assertHeader('X-Request-ID');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $response->headers->get('X-Request-ID'));
    }

    /** @test */
    public function custom_context_persists_throughout_request()
    {
        Route::get('/test', function () {
            Log::getContextManager()->addContext(['user_id' => 123]);

            $context1 = Log::getContextManager()->getContext();

            Log::info('Test log');

            $context2 = Log::getContextManager()->getContext();

            return response()->json([
                'has_user_id_before' => isset($context1['user_id']),
                'has_user_id_after' => isset($context2['user_id']),
                'user_id_matches' => ($context1['user_id'] ?? null) === ($context2['user_id'] ?? null),
            ]);
        });

        $response = $this->get('/test');

        $response->assertJson([
            'has_user_id_before' => true,
            'has_user_id_after' => true,
            'user_id_matches' => true,
        ]);
    }

    /** @test */
    public function external_request_id_is_preserved()
    {
        $externalId = '550e8400-e29b-41d4-a716-446655440000';

        Route::get('/test', function () {
            return Log::getContextManager()->getRequestId();
        });

        $response = $this->withHeaders([
            'X-Request-ID' => $externalId,
        ])->get('/test');

        $this->assertEquals($externalId, $response->getContent());
        $response->assertHeader('X-Request-ID', $externalId);
    }
}
