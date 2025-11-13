# GlobalLogger for Laravel

[![Tests](https://github.com/gopimosali/global-logger/workflows/Tests/badge.svg)](https://github.com/gopimosali/global-logger/actions)
[![Code Quality](https://github.com/gopimosali/global-logger/workflows/Code%20Quality/badge.svg)](https://github.com/gopimosali/global-logger/actions)
[![Latest Stable Version](https://poser.pugx.org/gopimosali/global-logger/v/stable)](https://packagist.org/packages/gopimosali/global-logger)
[![Total Downloads](https://poser.pugx.org/gopimosali/global-logger/downloads)](https://packagist.org/packages/gopimosali/global-logger)
[![License](https://poser.pugx.org/gopimosali/global-logger/license)](https://packagist.org/packages/gopimosali/global-logger)
[![PHP Version](https://img.shields.io/packagist/php-v/gopimosali/global-logger)](https://packagist.org/packages/gopimosali/global-logger)

A powerful, production-ready Laravel logging package that provides **universal request tracking** and **multi-provider support** with automatic correlation across AWS CloudWatch/X-Ray, Datadog, Oracle Cloud, Database, and file-based logs.

## 🎯 Key Features

- **Automatic Request Correlation** - Every log includes a unique `request_id` for complete request tracing
- **Multiple Providers** - Send logs to AWS CloudWatch/X-Ray, Datadog, Oracle Cloud, Database, and files simultaneously
- **Optional Performance Tracing** - Track operation performance with automatic or manual traces
- **Automatic Tracing** - Auto-trace HTTP requests, database queries, queue jobs, emails, and cache operations
- **PSR-3 Compliant** - Standard logging interface compatible with all Laravel applications
- **Zero Code Changes** - Works as a drop-in replacement for Laravel's `Log` facade
- **Production Ready** - Battle-tested with comprehensive error handling

---

## 📦 Installation

```bash
composer require gopimosali/global-logger
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="Gopimosali\GlobalLogger\GlobalLoggerServiceProvider" --tag="globallogger-config"
```

### Publish Database Migration (if using Database provider)

```bash
php artisan vendor:publish --provider="Gopimosali\GlobalLogger\GlobalLoggerServiceProvider" --tag="globallogger-migrations"
php artisan migrate
```

---

## 🚀 Quick Start

### 1. Enable a Provider

Edit your `.env` file:

```env
# Enable custom file logging
GLOBALLOG_CUSTOM_ENABLED=true
GLOBALLOG_CUSTOM_PATH=storage/logs/globallogger.log

# Or enable AWS CloudWatch + X-Ray
GLOBALLOG_AWS_ENABLED=true
AWS_DEFAULT_REGION=us-east-1
GLOBALLOG_AWS_LOG_GROUP=/aws/laravel
GLOBALLOG_XRAY_ENABLED=true

# Or enable Datadog
GLOBALLOG_DATADOG_ENABLED=true
DATADOG_API_KEY=your_api_key
DATADOG_SERVICE=my-app
```

### 2. Use It (No Code Changes!)

```php
use Illuminate\Support\Facades\Log;

// Standard logging - now includes request_id automatically!
Log::info('User logged in', ['user_id' => 123]);
Log::error('Payment failed', ['order_id' => 456]);

// Logs now include:
// - request_id: 550e8400-e29b-41d4-a716-446655440000
// - timestamp: 2025-01-17T10:30:00+00:00
// - environment: production
// - application: my-app
```

### 3. See the Results

All your logs now automatically include `request_id` across **all enabled providers**:

```json
{
  "level": "info",
  "message": "User logged in",
  "context": {
    "user_id": 123,
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "timestamp": "2025-01-17T10:30:00+00:00",
    "environment": "production",
    "application": "my-app"
  }
}
```

---

## 🎓 Understanding request_id vs Traces

### request_id (Automatic - You Don't Do Anything!)

The `request_id` is **automatically generated once per request** and included in **every log**. It allows you to correlate all logs from a single request.

**You don't need to do anything - it just works!**

```php
// Request starts - request_id generated: 550e8400...

Log::info('User login attempt');  // ✅ Has request_id
Log::info('Checking database');   // ✅ Same request_id
Log::error('Invalid password');   // ✅ Same request_id

// All logs from this request share the same request_id
```

**Search all logs from one request:**
- CloudWatch: `{ $.request_id = "550e8400..." }`
- Datadog: `@request_id:550e8400...`
- X-Ray: `annotation.request_id = "550e8400..."`

---

### Traces (Optional - For Performance Tracking)

Traces are **optional** and used to **measure how long specific operations take**. Use them when you want to see:
- Which operations are slow
- Performance bottlenecks
- Detailed timing breakdowns

#### Option 1: Automatic Tracing (Recommended)

Enable automatic tracing in `.env`:

```env
GLOBALLOG_AUTO_TRACING_ENABLED=true
GLOBALLOG_AUTO_TRACE_HTTP=true
GLOBALLOG_AUTO_TRACE_DATABASE=true
GLOBALLOG_AUTO_TRACE_QUEUE=true
GLOBALLOG_AUTO_TRACE_MAIL=true
```

Now **all** HTTP requests, database queries, queue jobs, and emails are automatically traced - **no code changes needed!**

```php
// Automatically traced!
$response = Http::post('https://api.example.com/users');

// Automatically traced!
$user = User::create(['name' => 'John']);

// Automatically traced!
Mail::to($user)->send(new Welcome($user));
```

#### Option 2: Manual Tracing (For Custom Operations)

Wrap important operations with manual traces:

```php
use Illuminate\Support\Facades\Log;

// Start a trace
$trace = Log::startTrace('payment.process', [
    'amount' => 100.00,
    'gateway' => 'stripe'
]);

// Your code here...
$payment = $stripe->charges->create([...]);

// End the trace - logs duration automatically
Log::endTrace($trace, [
    'charge_id' => $payment->id,
    'status' => $payment->status
]);
```

**See traces in:**
- **AWS X-Ray**: Visual service map showing all traced operations
- **Datadog APM**: Flame graphs and performance metrics
- **Logs**: Search for `type:trace` to see all performance data

---

## 📊 Complete Example: E-Commerce Checkout

Here's a complete example showing both automatic `request_id` correlation and optional performance tracing:

```php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use App\Services\{PaymentService, InventoryService};

class CheckoutController extends Controller
{
    public function process(Request $request)
    {
        // request_id automatically generated: 550e8400-e29b-41d4-a716-446655440000

        Log::info('Checkout started', [
            'cart_items' => count($request->cart_items),
            'user_id' => $request->user()->id
        ]);

        // ═══════════════════════════════════════════════════════
        // Option A: With automatic tracing (RECOMMENDED)
        // ═══════════════════════════════════════════════════════

        // Automatically traced!
        $inventory = Http::post('https://inventory.example.com/check', [
            'items' => $request->cart_items
        ]);

        // Automatically traced!
        $order = Order::create([
            'user_id' => $request->user()->id,
            'items' => $request->cart_items,
            'total' => $request->total
        ]);

        // Automatically traced!
        Mail::to($request->user())->send(new OrderConfirmation($order));

        // ═══════════════════════════════════════════════════════
        // Option B: With manual tracing (for custom operations)
        // ═══════════════════════════════════════════════════════

        $paymentTrace = Log::startTrace('payment.stripe.charge', [
            'amount' => $request->total
        ]);

        $payment = $this->paymentService->charge(
            $request->total,
            $request->payment_method
        );

        Log::endTrace($paymentTrace, [
            'charge_id' => $payment->id,
            'status' => $payment->status
        ]);

        Log::info('Checkout completed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id
        ]);

        return response()->json(['order_id' => $order->id]);
    }
}
```

### What You See in AWS X-Ray:

```
POST /api/checkout (850ms)
request_id: 550e8400-e29b-41d4-a716-446655440000

├─ http.request (120ms) ← Automatic!
│  └─ POST inventory.example.com
│
├─ database.insert (45ms) ← Automatic!
│  └─ INSERT orders
│
├─ payment.stripe.charge (340ms) ← Manual trace
│  └─ Stripe API
│
└─ mail.send (95ms) ← Automatic!
   └─ Send OrderConfirmation
```

### What You See in CloudWatch Logs:

```json
[
  {
    "message": "Checkout started",
    "request_id": "550e8400...",
    "timestamp": "2025-01-17T10:30:00Z"
  },
  {
    "message": "Trace completed: http.request",
    "request_id": "550e8400...",
    "duration_ms": 120,
    "type": "trace"
  },
  {
    "message": "Trace completed: database.insert",
    "request_id": "550e8400...",
    "duration_ms": 45,
    "type": "trace"
  },
  {
    "message": "Trace completed: payment.stripe.charge",
    "request_id": "550e8400...",
    "duration_ms": 340,
    "type": "trace"
  },
  {
    "message": "Checkout completed",
    "request_id": "550e8400...",
    "order_id": "ORD-123"
  }
]
```

**Search all logs from this checkout:**
```
{ $.request_id = "550e8400..." }
```

Returns **ALL** logs and traces from start to finish!

---

## 🔧 Provider Configuration

### AWS CloudWatch + X-Ray

```env
GLOBALLOG_AWS_ENABLED=true
AWS_DEFAULT_REGION=us-east-1
GLOBALLOG_AWS_LOG_GROUP=/aws/laravel/production
GLOBALLOG_AWS_LOG_STREAM=application

# Enable X-Ray for trace visualization
GLOBALLOG_XRAY_ENABLED=true
XRAY_DAEMON_ADDRESS=127.0.0.1:2000
```

**Install AWS SDK:**
```bash
composer require aws/aws-sdk-php
```

**Features:**
- Logs sent to CloudWatch Logs
- Traces visualized in X-Ray service map
- `request_id` converted to X-Ray trace ID format
- Original `request_id` preserved in annotations

---

### Datadog

```env
GLOBALLOG_DATADOG_ENABLED=true
DATADOG_API_KEY=your_datadog_api_key
DATADOG_HOST=http-intake.logs.datadoghq.com
DATADOG_SERVICE=my-laravel-app

# Enable APM for trace visualization
DATADOG_APM_ENABLED=true
DD_AGENT_HOST=localhost
DD_TRACE_AGENT_PORT=8126
```

**Install Datadog PHP:**
```bash
composer require datadog/php-datadogstatsd
```

**Features:**
- Logs sent to Datadog Logs
- Traces visualized in Datadog APM
- `request_id` converted to Datadog trace ID format
- Original `request_id` preserved in tags

---

### Oracle Cloud Logging

```env
GLOBALLOG_ORACLE_ENABLED=true
ORACLE_LOGGING_ENDPOINT=https://logging.us-ashburn-1.oci.oraclecloud.com
ORACLE_LOG_ID=ocid1.log.oc1...
ORACLE_COMPARTMENT_ID=ocid1.compartment.oc1...
ORACLE_TENANCY_ID=ocid1.tenancy.oc1...
ORACLE_USER_ID=ocid1.user.oc1...
ORACLE_KEY_FINGERPRINT=aa:bb:cc:dd...
ORACLE_PRIVATE_KEY_PATH=/path/to/oci_api_key.pem
```

**Features:**
- Native Oracle Cloud Logging API integration
- Automatic OCI signature generation
- Full request context preserved

---

### Database

```env
GLOBALLOG_DATABASE_ENABLED=true
GLOBALLOG_DB_CONNECTION=mysql
GLOBALLOG_DB_TABLE=global_logs
```

**Features:**
- Store logs in your database
- Query logs with Eloquent
- Index on `request_id` for fast searches

**Publish and run migration:**
```bash
php artisan vendor:publish --provider="Gopimosali\GlobalLogger\GlobalLoggerServiceProvider" --tag="globallogger-migrations"
php artisan migrate
```

---

### Custom File Logging

```env
GLOBALLOG_CUSTOM_ENABLED=true
GLOBALLOG_CUSTOM_PATH=storage/logs/globallogger.log
GLOBALLOG_CUSTOM_MAX_FILES=14
```

**Features:**
- Rotating file handler (14 days by default)
- JSON-formatted logs
- Local development friendly

---

## 🎯 When to Use What?

### Use request_id (Always Automatic!)

✅ **You don't need to do anything** - it's automatic!

**Perfect for:**
- Correlating all logs from one request
- Debugging user issues
- Following a request through microservices
- Understanding what happened during an error

**Search examples:**
```bash
# CloudWatch
{ $.request_id = "550e8400-e29b-41d4-a716-446655440000" }

# Datadog
@request_id:550e8400-e29b-41d4-a716-446655440000

# Database
SELECT * FROM global_logs WHERE request_id = '550e8400...'
```

---

### Use Automatic Tracing (Recommended!)

✅ **Just enable it** - no code changes needed!

```env
GLOBALLOG_AUTO_TRACING_ENABLED=true
```

**Perfect for:**
- Automatically tracking all HTTP, database, queue, and mail operations
- Reducing boilerplate code
- Ensuring nothing is missed
- Quick performance insights

**What gets traced:**
- HTTP requests (Laravel `Http::` facade)
- Database queries (Eloquent and Query Builder)
- Queue jobs (all dispatched jobs)
- Email sending (Laravel `Mail::` facade)
- Cache operations (optional)

---

### Use Manual Tracing (When Needed)

✅ **Wrap specific operations** when automatic tracing isn't enough

**Perfect for:**
- Custom business logic
- Third-party SDK calls
- File operations
- Complex algorithms
- External API calls not using Laravel's `Http::`

**Example:**
```php
$trace = Log::startTrace('image.resize', ['width' => 800]);
$resized = $imageProcessor->resize($image, 800, 600);
Log::endTrace($trace, ['size_bytes' => strlen($resized)]);
```

---

## 📚 Advanced Usage

### Passing request_id to External Services

```php
use Illuminate\Support\Facades\{Log, Http};

$requestId = Log::getContextManager()->getRequestId();

// Pass to external service
$response = Http::withHeaders([
    'X-Request-ID' => $requestId
])->post('https://external-service.com/api', [
    'data' => 'value'
]);

// Now external service logs can use the same request_id!
```

### Adding Custom Context

```php
Log::getContextManager()->addContext([
    'feature_flag' => 'new_checkout',
    'ab_test_variant' => 'B',
    'tenant_id' => 'tenant-123'
]);

// All subsequent logs in this request include custom context
Log::info('Checkout completed');  // Includes feature_flag, ab_test_variant, tenant_id
```

### Converting request_id Formats

```php
$contextManager = Log::getContextManager();

// Get standard request_id
$requestId = $contextManager->getRequestId();
// 550e8400-e29b-41d4-a716-446655440000

// Convert to X-Ray format
$xrayTraceId = $contextManager->toXRayTraceId();
// 1-65a5b12c-550e8400e29b41d4a716

// Convert to Datadog format
$datadogTraceId = $contextManager->toDatadogTraceId();
// 6145998120563704832
```

---

## 🔍 Searching and Querying

### AWS CloudWatch Logs Insights

```sql
-- Find all logs from one request
fields @timestamp, level, message
| filter request_id = "550e8400-e29b-41d4-a716-446655440000"
| sort @timestamp asc

-- Find slow operations
fields @timestamp, message, context.duration_ms
| filter context.type = "trace" and context.duration_ms > 500
| sort context.duration_ms desc

-- Find errors for a user
fields @timestamp, message, level
| filter context.user_id = 123 and level = "error"
```

### AWS X-Ray

```
-- Find by request_id
annotation.request_id = "550e8400-e29b-41d4-a716-446655440000"

-- Find slow payments
service("my-app") AND annotation.operation = "payment" AND duration > 200

-- Find errors
error = true AND fault = true
```

### Datadog

```
-- Find by request_id
@request_id:550e8400-e29b-41d4-a716-446655440000

-- Find traces by user
@user_id:123 type:trace

-- Find slow database queries
service:my-app operation:database.query duration:>100ms
```

### Database (Eloquent)

```php
use Illuminate\Support\Facades\DB;

// Find all logs from one request
DB::table('global_logs')
    ->where('request_id', '550e8400-e29b-41d4-a716-446655440000')
    ->orderBy('created_at')
    ->get();

// Find errors in last hour
DB::table('global_logs')
    ->where('level', 'error')
    ->where('created_at', '>', now()->subHour())
    ->get();
```

---

## 🛠️ Troubleshooting

### Logs Not Appearing

**Check provider is enabled:**
```bash
php artisan tinker
>>> config('globallogger.providers.custom.enabled')
=> true
```

**Check file permissions:**
```bash
chmod -R 775 storage/logs
```

**Check AWS credentials:**
```bash
aws sts get-caller-identity
```

### request_id Not Showing

**Ensure middleware is registered:**
```bash
php artisan route:list
```

Middleware should show `Gopimosali\GlobalLogger\Middleware\LogContextMiddleware`.

### Traces Not Appearing in X-Ray

**Check X-Ray daemon is running:**
```bash
curl http://localhost:2000/GetSamplingRules
```

**Check X-Ray is enabled:**
```env
GLOBALLOG_XRAY_ENABLED=true
```

---

## 📖 Additional Documentation

- [TRACING_GUIDE.md](TRACING_GUIDE.md) - Detailed guide on when and how to use traces
- [REQUEST_ID_FLOW.md](REQUEST_ID_FLOW.md) - How request_id flows through systems
- [AUTO_TRACING_GUIDE.md](AUTO_TRACING_GUIDE.md) - Complete automatic tracing documentation

---

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## 📄 License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

---

## 🙏 Credits

Created by [Gopi Mosali](https://github.com/gopimosali)

Special thanks to the Laravel community for inspiration and feedback.

---

## ⭐ Show Your Support

If this package helps you, please star it on GitHub! ⭐
