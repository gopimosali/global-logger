<?php

namespace Gopimosali\GlobalLogger\Tests\Unit\LogContext;

use Gopimosali\GlobalLogger\LogContext\LogContextManager;
use Gopimosali\GlobalLogger\Tests\TestCase;

class LogContextManagerTest extends TestCase
{
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
    }

    /** @test */
    public function it_generates_a_valid_uuid_request_id()
    {
        $requestId = $this->contextManager->getRequestId();

        $this->assertIsString($requestId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $requestId);
    }

    /** @test */
    public function it_returns_the_same_request_id_on_multiple_calls()
    {
        $requestId1 = $this->contextManager->getRequestId();
        $requestId2 = $this->contextManager->getRequestId();

        $this->assertEquals($requestId1, $requestId2);
    }

    /** @test */
    public function it_can_set_custom_request_id()
    {
        $customId = '550e8400-e29b-41d4-a716-446655440000';
        $this->contextManager->setRequestId($customId);

        $this->assertEquals($customId, $this->contextManager->getRequestId());
    }

    /** @test */
    public function it_includes_automatic_context()
    {
        $context = $this->contextManager->getContext();

        $this->assertArrayHasKey('request_id', $context);
        $this->assertArrayHasKey('timestamp', $context);
        $this->assertArrayHasKey('environment', $context);
        $this->assertArrayHasKey('application', $context);
    }

    /** @test */
    public function it_can_add_custom_context()
    {
        $this->contextManager->addContext([
            'user_id' => 123,
            'tenant_id' => 'tenant-abc',
        ]);

        $context = $this->contextManager->getContext();

        $this->assertEquals(123, $context['user_id']);
        $this->assertEquals('tenant-abc', $context['tenant_id']);
    }

    /** @test */
    public function it_merges_custom_context_with_automatic_context()
    {
        $this->contextManager->addContext(['custom_key' => 'custom_value']);

        $context = $this->contextManager->getContext();

        $this->assertArrayHasKey('request_id', $context);
        $this->assertArrayHasKey('custom_key', $context);
        $this->assertEquals('custom_value', $context['custom_key']);
    }

    /** @test */
    public function it_can_clear_custom_context()
    {
        $this->contextManager->addContext(['user_id' => 123]);
        $this->contextManager->clearContext();

        $context = $this->contextManager->getContext();

        $this->assertArrayNotHasKey('user_id', $context);
        $this->assertArrayHasKey('request_id', $context); // Automatic context remains
    }

    /** @test */
    public function it_converts_uuid_to_xray_trace_id_format()
    {
        $this->contextManager->setRequestId('550e8400-e29b-41d4-a716-446655440000');

        $xrayTraceId = $this->contextManager->toXRayTraceId();

        $this->assertStringStartsWith('1-', $xrayTraceId);
        $this->assertMatchesRegularExpression('/^1-\d+-[0-9a-f]{24}$/', $xrayTraceId);
    }

    /** @test */
    public function it_converts_uuid_to_datadog_trace_id_format()
    {
        $this->contextManager->setRequestId('550e8400-e29b-41d4-a716-446655440000');

        $datadogTraceId = $this->contextManager->toDatadogTraceId();

        $this->assertIsString($datadogTraceId);
        $this->assertMatchesRegularExpression('/^\d+$/', $datadogTraceId);
    }

    /** @test */
    public function it_generates_uuid_v4_when_configured()
    {
        $contextManager = new LogContextManager([
            'request_id' => ['version' => 4],
        ]);

        $requestId = $contextManager->getRequestId();

        // UUID v4 has '4' as the first character of the third group
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId);
    }

    /** @test */
    public function it_generates_uuid_v7_by_default()
    {
        $contextManager = new LogContextManager([
            'request_id' => ['version' => 7],
        ]);

        $requestId = $contextManager->getRequestId();

        // UUID v7 has '7' as the first character of the third group
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId);
    }

    /** @test */
    public function timestamp_is_in_iso8601_format()
    {
        $context = $this->contextManager->getContext();

        $this->assertArrayHasKey('timestamp', $context);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $context['timestamp']);
    }
}
