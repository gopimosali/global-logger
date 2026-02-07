# GlobalLogger for Laravel

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

**Complete integration with Oracle Cloud Infrastructure (OCI) Logging service with automatic request signing.**

#### Quick Setup

```env
# Enable Oracle Cloud Logging
GLOBALLOG_ORACLE_ENABLED=true

# OCI Logging Endpoint (region-specific)
# Format: https://ingestion.logging.{region}.oci.oraclecloud.com
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.us-ashburn-1.oci.oraclecloud.com"

# Log OCID (created in OCI Console)
ORACLE_LOG_ID="ocid1.log.oc1.iad.amaaaaaavjeslaya..."

# OCI Authentication
ORACLE_COMPARTMENT_ID="ocid1.tenancy.oc1..aaaaaaaavl4ykid4..."
ORACLE_TENANCY_ID="ocid1.tenancy.oc1..aaaaaaaavl4ykid4..."
ORACLE_USER_ID="ocid1.user.oc1..aaaaaaaap64xt77mwdocz..."
ORACLE_KEY_FINGERPRINT="39:79:3c:e4:d9:13:e0:44:18:e6:0a:21:48:1c:57:85"
ORACLE_PRIVATE_KEY_PATH="/var/www/html/.oci/oci_api_key.pem"
```

#### Detailed Setup Steps

**1. Create a Log in OCI Console:**
```
OCI Console → Logging → Logs → Create Custom Log
  - Name: your-application-logs
  - Log Group: create or select existing
  - Region: us-ashburn-1 (or your region)
  - Copy the Log OCID
```

**2. Generate OCI API Key:**
```bash
# Generate private key
mkdir ~/.oci
openssl genrsa -out ~/.oci/oci_api_key.pem 2048

# Generate public key
openssl rsa -pubout -in ~/.oci/oci_api_key.pem -out ~/.oci/oci_api_key_public.pem

# Set permissions
chmod 600 ~/.oci/oci_api_key.pem
```

**3. Add API Key to OCI User:**
```
OCI Console → Identity → Users → Your User → API Keys → Add API Key
  - Paste the public key content
  - Copy the fingerprint
```

**4. Create IAM Policy:**
```
OCI Console → Identity → Policies → Create Policy
  Name: logging-policy
  Statement:
    Allow group Administrators to use log-content in tenancy
```

**5. Set File Permissions (Important!):**
```bash
# Ensure web server can read the private key
chown www-data:www-data /path/to/.oci/oci_api_key.pem
chmod 640 /path/to/.oci/oci_api_key.pem

# Add to .gitignore
echo ".oci/" >> .gitignore
```

#### Regional Endpoints

Choose the correct endpoint for your OCI region:

```env
# US Regions
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.us-ashburn-1.oci.oraclecloud.com"
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.us-phoenix-1.oci.oraclecloud.com"

# Asia Pacific
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.ap-mumbai-1.oci.oraclecloud.com"
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.ap-hyderabad-1.oci.oraclecloud.com"
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.ap-tokyo-1.oci.oraclecloud.com"
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.ap-sydney-1.oci.oraclecloud.com"

# Europe
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.eu-frankfurt-1.oci.oraclecloud.com"
ORACLE_LOGGING_ENDPOINT="https://ingestion.logging.eu-amsterdam-1.oci.oraclecloud.com"

# See full list: https://docs.oracle.com/en-us/iaas/api/#/en/logging-dataplane/20200831/
```

#### Testing Oracle Cloud Logging

```bash
# Test the integration
php artisan tinker --execute="Log::info('Oracle Cloud Logging Test');"

# Check for errors in Laravel logs
tail -f storage/logs/laravel.log | grep -i oracle

# Or check Docker stdout logs
docker logs -f your-container | grep -i oracle
```

**Features:**
- ✅ Native OCI Logging Ingestion API (20200831)
- ✅ Automatic request signing with RSA-SHA256
- ✅ Proper x-content-sha256 header generation
- ✅ Full request context preserved (request_id, timestamp, environment)
- ✅ JSON-formatted log entries
- ✅ Automatic error handling with detailed error messages
- ✅ Compatible with all OCI regions

**Troubleshooting:**

Common issues and solutions:

```bash
# Issue: HTTP 401 "SIGNATURE_NOT_VALID"
# Solution: Check fingerprint and key path match
php -r "echo openssl_pkey_get_private(file_get_contents('/path/to/key.pem')) ? 'Valid' : 'Invalid';"

# Issue: HTTP 404 "NotAuthorizedOrNotFound"
# Solution: Verify IAM policy allows log-content usage
# Required policy: Allow group Administrators to use log-content in tenancy

# Issue: Permission denied reading private key
# Solution: Fix file ownership and permissions
sudo chown www-data:www-data /path/to/.oci/oci_api_key.pem
sudo chmod 640 /path/to/.oci/oci_api_key.pem

# Test OCI credentials
php artisan tinker
>>> file_exists(env('ORACLE_PRIVATE_KEY_PATH'))
=> true
>>> openssl_pkey_get_private(file_get_contents(env('ORACLE_PRIVATE_KEY_PATH')))
=> OpenSSLAsymmetricKey {...}
```

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
- **Optional stdout support** for Docker/Kubernetes

#### Enable Stdout Output (Docker/Kubernetes)

For containerized environments, you can output logs to stdout **with full GlobalLogger context**:

```env
# Enable stdout in production (auto-enabled by default)
GLOBALLOG_STDOUT_ENABLED=true
```

**Or configure per environment in `.env`:**
```env
# Auto-enable for production only
GLOBALLOG_STDOUT_ENABLED=${APP_ENV=production}
```

**Configuration in `config/globallogger.php`:**
```php
'custom' => [
    'enabled' => env('GLOBALLOG_CUSTOM_ENABLED', true),
    'path' => env('GLOBALLOG_CUSTOM_PATH', storage_path('logs/globallogger.log')),
    'max_files' => env('GLOBALLOG_CUSTOM_MAX_FILES', 14),
    'stdout' => env('GLOBALLOG_STDOUT_ENABLED', env('APP_ENV') === 'production'),
],
```

**What you get in stdout:**

All logs are output as **JSON with full context**:

```json
{
  "message": "User logged in successfully",
  "context": {
    "request_id": "019be854-a2a5-71b8-94dc-33e1d7891cb2",
    "timestamp": "2026-01-23T00:50:19+00:00",
    "environment": "production",
    "application": "MyApp",
    "user_id": 12345
  },
  "level": 200,
  "level_name": "INFO",
  "channel": "globallogger"
}
```

**Benefits:**
- ✅ Docker logs: `docker logs -f container-name` shows structured JSON
- ✅ Kubernetes: Logs collected by K8s with full context
- ✅ Log aggregation: Easy parsing by CloudWatch, Datadog, ELK, etc.
- ✅ Same rich context: request_id, timestamp, environment in every log
- ✅ File + stdout: Logs written to both file and stdout simultaneously

**Test stdout logging:**
```bash
# Test with stdout enabled
GLOBALLOG_STDOUT_ENABLED=true php artisan your:command

# Or in production (auto-enabled)
APP_ENV=production php artisan your:command
```

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
