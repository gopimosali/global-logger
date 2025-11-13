<?php

namespace Gopimosali\GlobalLogger\Tests\Unit\Middleware;

use Gopimosali\GlobalLogger\GlobalLogger;
use Gopimosali\GlobalLogger\LogContext\LogContextManager;
use Gopimosali\GlobalLogger\Middleware\LogContextMiddleware;
use Gopimosali\GlobalLogger\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogContextMiddlewareTest extends TestCase
{
    protected LogContextMiddleware $middleware;
    protected GlobalLogger $logger;
    protected LogContextManager $contextManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextManager = new LogContextManager([
            'request_id' => [
                'version' => 7,
                'header' => 'X-Request-ID',
            ],
        ]);
        $this->logger = new GlobalLogger($this->contextManager);
        $this->middleware = new LogContextMiddleware($this->logger);
    }

    /** @test */
    public function it_generates_request_id_for_new_requests()
    {
        $request = Request::create('/test', 'GET');

        $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $requestId = $this->contextManager->getRequestId();

        $this->assertNotEmpty($requestId);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $requestId);
    }

    /** @test */
    public function it_uses_existing_request_id_from_header()
    {
        $existingId = '550e8400-e29b-41d4-a716-446655440000';
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Request-ID', $existingId);

        $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $requestId = $this->contextManager->getRequestId();

        $this->assertEquals($existingId, $requestId);
    }

    /** @test */
    public function it_adds_request_id_to_response_headers()
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertTrue($response->headers->has('X-Request-ID'));
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    /** @test */
    public function it_adds_request_context()
    {
        $request = Request::create('/test?foo=bar', 'POST');
        $request->headers->set('User-Agent', 'TestAgent/1.0');

        $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $context = $this->contextManager->getContext();

        $this->assertEquals('POST', $context['method']);
        $this->assertStringContainsString('/test', $context['url']);
        $this->assertNotEmpty($context['ip']);
        $this->assertEquals('TestAgent/1.0', $context['user_agent']);
    }

    /** @test */
    public function it_includes_user_id_when_authenticated()
    {
        $request = Request::create('/test', 'GET');

        // Mock authenticated user
        $user = new class {
            public $id = 123;
        };
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $context = $this->contextManager->getContext();

        $this->assertEquals(123, $context['user_id']);
    }

    /** @test */
    public function it_does_not_add_request_id_to_response_when_disabled()
    {
        config(['globallogger.request_id.include_in_response' => false]);

        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertFalse($response->headers->has('X-Request-ID'));
    }

    /** @test */
    public function it_preserves_request_id_throughout_request_lifecycle()
    {
        $request = Request::create('/test', 'GET');

        $requestIdInMiddleware = null;
        $requestIdInClosure = null;

        $this->middleware->handle($request, function ($req) use (&$requestIdInClosure) {
            $requestIdInClosure = $this->contextManager->getRequestId();
            return new Response('OK');
        });

        $requestIdInMiddleware = $this->contextManager->getRequestId();

        $this->assertEquals($requestIdInMiddleware, $requestIdInClosure);
    }
}
