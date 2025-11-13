<?php

/**
 * Basic Logging Example
 *
 * This example shows the most basic usage of GlobalLogger.
 * No code changes needed - just install and enable a provider!
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Log;

// Basic logging - request_id automatically included!
Log::info('Application started');

Log::info('User action', [
    'user_id' => 123,
    'action' => 'login',
    'ip' => '192.168.1.1',
]);

Log::warning('High memory usage', [
    'memory_mb' => 256,
    'threshold_mb' => 200,
]);

Log::error('Payment failed', [
    'order_id' => 'ORD-12345',
    'amount' => 99.99,
    'reason' => 'Card declined',
]);

// All logs automatically include:
// - request_id: unique per request
// - timestamp: ISO 8601 format
// - environment: production/staging/local
// - application: your app name

/*
Example output:
{
  "level": "info",
  "message": "User action",
  "context": {
    "user_id": 123,
    "action": "login",
    "ip": "192.168.1.1",
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "timestamp": "2025-01-17T10:30:00+00:00",
    "environment": "production",
    "application": "my-app"
  }
}
*/
