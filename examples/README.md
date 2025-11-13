# GlobalLogger Examples

This directory contains real-world examples of using GlobalLogger in various scenarios.

## Examples

### 1. Basic Logging
**File:** `basic-logging.php`

The simplest possible usage - just install and log!

```php
Log::info('User logged in', ['user_id' => 123]);
```

All logs automatically include `request_id`, `timestamp`, `environment`, and `application`.

---

### 2. E-Commerce Checkout
**File:** `e-commerce-checkout.php`

Complete e-commerce checkout flow showing:
- Automatic request correlation
- Manual performance tracing for payment processing
- Automatic tracing for HTTP, database, and email
- Error handling and logging

**Use this example to learn:**
- When to use automatic vs manual tracing
- How to trace custom business operations
- Best practices for logging in production

---

### 3. Microservices Correlation
**File:** `microservices-correlation.php`

Shows how to pass `request_id` between services for complete request tracing across your microservices architecture.

**Covers 4 services:**
1. API Gateway (receives request)
2. User Service (validates user)
3. Order Service (creates order)
4. Inventory Service (checks stock)

**Use this example to learn:**
- How to propagate request_id between services
- How to search logs across all services
- Best practices for distributed tracing

---

## Running Examples

These are code examples, not executable scripts. Copy the relevant patterns into your Laravel application.

### Setup Requirements

1. Install GlobalLogger:
```bash
composer require gopimosali/global-logger
```

2. Publish config:
```bash
php artisan vendor:publish --provider="Gopimosali\GlobalLogger\GlobalLoggerServiceProvider"
```

3. Enable a provider in `.env`:
```env
GLOBALLOG_CUSTOM_ENABLED=true
GLOBALLOG_CUSTOM_PATH=storage/logs/globallogger.log
```

4. Use the patterns from examples in your controllers!

---

## More Examples Needed?

Open an issue or PR to request more examples:
- Queue job tracing
- Event-driven architecture
- Multi-tenant logging
- Custom provider implementation
- Performance optimization scenarios
- Error tracking patterns

## Questions?

- Read the [main README](../README.md)
- Check the [AUTO_TRACING_GUIDE](../AUTO_TRACING_GUIDE.md)
- Open a [GitHub issue](https://github.com/gopimosali/global-logger/issues)
