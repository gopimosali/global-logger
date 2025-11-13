<?php

namespace Gopimosali\GlobalLogger\Tests\Unit\Providers;

use Gopimosali\GlobalLogger\Providers\CustomProvider;
use Gopimosali\GlobalLogger\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Psr\Log\LogLevel;

class CustomProviderTest extends TestCase
{
    protected string $testLogPath;
    protected CustomProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testLogPath = storage_path('logs/test-custom-provider.log');

        // Clean up any existing test log file
        if (File::exists($this->testLogPath)) {
            File::delete($this->testLogPath);
        }

        $this->provider = new CustomProvider([
            'path' => $this->testLogPath,
            'max_files' => 7,
        ]);
    }

    /** @test */
    public function it_can_write_logs_to_file()
    {
        $this->provider->log(LogLevel::INFO, 'Test message', ['key' => 'value']);

        $this->assertTrue(File::exists($this->testLogPath));
    }

    /** @test */
    public function it_writes_json_formatted_logs()
    {
        $this->provider->log(LogLevel::INFO, 'Test message', ['key' => 'value']);

        $content = File::get($this->testLogPath);
        $lines = array_filter(explode("\n", $content));
        $lastLine = end($lines);

        $decoded = json_decode($lastLine, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('info', $decoded['level']);
        $this->assertEquals('Test message', $decoded['message']);
        $this->assertEquals('value', $decoded['context']['key']);
    }

    /** @test */
    public function it_handles_different_log_levels()
    {
        $this->provider->log(LogLevel::DEBUG, 'Debug message', []);
        $this->provider->log(LogLevel::INFO, 'Info message', []);
        $this->provider->log(LogLevel::WARNING, 'Warning message', []);
        $this->provider->log(LogLevel::ERROR, 'Error message', []);

        $content = File::get($this->testLogPath);
        $lines = array_filter(explode("\n", $content));

        $this->assertCount(4, $lines);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testLogPath)) {
            File::delete($this->testLogPath);
        }

        parent::tearDown();
    }
}
