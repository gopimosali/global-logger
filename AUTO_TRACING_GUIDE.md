# Automatic Tracing Guide

This guide explains how to use GlobalLogger's automatic tracing feature to track performance without manual code changes.

## What is Automatic Tracing?

Automatic tracing listens to Laravel events and automatically creates performance traces for common operations:

- ✅ HTTP requests (via `Http::` facade)
- ✅ Database queries (Eloquent & Query Builder)
- ✅ Queue jobs
- ✅ Email sending
- ✅ Cache operations (optional)

**No code changes needed** - just enable it and get automatic performance tracking!

---

## Quick Start

### Enable Automatic Tracing

Edit `.env`:

```env
GLOBALLOG_AUTO_TRACING_ENABLED=true
GLOBALLOG_AUTO_TRACE_HTTP=true
GLOBALLOG_AUTO_TRACE_DATABASE=true
GLOBALLOG_AUTO_TRACE_QUEUE=true
GLOBALLOG_AUTO_TRACE_MAIL=true
GLOBALLOG_AUTO_TRACE_CACHE=false  # Can be noisy

# Minimum duration (ms) to log
GLOBALLOG_AUTO_TRACE_MIN_DURATION=10
```

That's it! Now all operations are automatically traced.

---

## HTTP Requests

**Before (Manual):**
```php
$trace = Log::startTrace('http.request');
$response = Http::post('https://api.example.com/users');
Log::endTrace($trace, ['status' => $response->status()]);
```

**After (Automatic):**
```php
// Just use Http:: normally - automatically traced!
$response = Http::post('https://api.example.com/users');
```

**Trace includes:**
- Method (GET, POST, etc.)
- URL
- Status code
- Duration

---

## Database Queries

**Before (Manual):**
```php
$trace = Log::startTrace('database.query');
$users = User::where('active', true)->get();
Log::endTrace($trace);
```

**After (Automatic):**
```php
// Just use Eloquent normally - automatically traced!
$users = User::where('active', true)->get();
```

**Trace includes:**
- SQL query
- Bindings
- Connection name
- Duration

**Note:** Only queries taking longer than `GLOBALLOG_AUTO_TRACE_MIN_DURATION` are logged to reduce noise.

---

## Queue Jobs

**Before (Manual):**
```php
$trace = Log::startTrace('queue.job');
ProcessOrderJob::dispatch($order);
Log::endTrace($trace);
```

**After (Automatic):**
```php
// Just dispatch normally - automatically traced!
ProcessOrderJob::dispatch($order);
```

**Trace includes:**
- Job class name
- Queue name
- Connection
- Duration

---

## Email Sending

**Before (Manual):**
```php
$trace = Log::startTrace('mail.send');
Mail::to($user)->send(new Welcome($user));
Log::endTrace($trace);
```

**After (Automatic):**
```php
// Just send mail normally - automatically traced!
Mail::to($user)->send(new Welcome($user));
```

**Trace includes:**
- Recipients
- Subject
- Duration

---

## Complete Example

```php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\{Log, Http};
use App\Jobs\ProcessOrderJob;
use App\Mail\OrderConfirmation;

class OrderController extends Controller
{
    public function create(Request $request)
    {
        Log::info('Creating order');

        // Automatically traced!
        $inventory = Http::post('https://inventory.example.com/check', [
            'items' => $request->items
        ]);

        // Automatically traced!
        $order = Order::create([
            'user_id' => $request->user()->id,
            'items' => $request->items,
        ]);

        // Automatically traced!
        ProcessOrderJob::dispatch($order);

        // Automatically traced!
        Mail::to($request->user())->send(new OrderConfirmation($order));

        Log::info('Order created', ['order_id' => $order->id]);

        return response()->json(['order_id' => $order->id]);
    }
}
```

**Result in X-Ray:**
```
POST /api/orders (650ms)
├─ http.request (120ms) ← Automatic!
├─ database.insert (45ms) ← Automatic!
├─ queue.job (5ms) ← Automatic!
└─ mail.send (95ms) ← Automatic!
```

**60% less code**, same visibility!

---

## When to Use Manual Tracing

Use manual `startTrace()` / `endTrace()` for:

1. **Custom business logic**
```php
$trace = Log::startTrace('payment.process');
$payment = $this->paymentGateway->charge($amount);
Log::endTrace($trace, ['charge_id' => $payment->id]);
```

2. **Third-party SDKs**
```php
$trace = Log::startTrace('stripe.create_charge');
$charge = \Stripe\Charge::create([...]);
Log::endTrace($trace);
```

3. **File operations**
```php
$trace = Log::startTrace('file.resize');
$resized = Image::make($file)->resize(800, 600);
Log::endTrace($trace);
```

4. **Complex algorithms**
```php
$trace = Log::startTrace('recommendation.generate');
$recommendations = $this->engine->generate($userId);
Log::endTrace($trace, ['count' => count($recommendations)]);
```

---

## Configuration Options

### Minimum Duration

Only log traces that take longer than a threshold (reduces noise):

```env
GLOBALLOG_AUTO_TRACE_MIN_DURATION=10  # milliseconds
```

### Disable Specific Types

```env
GLOBALLOG_AUTO_TRACE_HTTP=true
GLOBALLOG_AUTO_TRACE_DATABASE=true
GLOBALLOG_AUTO_TRACE_QUEUE=false  # Disable queue tracing
GLOBALLOG_AUTO_TRACE_MAIL=true
GLOBALLOG_AUTO_TRACE_CACHE=false  # Disable cache (can be very noisy)
```

### Completely Disable Auto-Tracing

```env
GLOBALLOG_AUTO_TRACING_ENABLED=false
```

---

## Combining with Manual Tracing

You can use **both** automatic and manual tracing together:

```php
Log::info('Processing checkout');

// Automatically traced
$inventory = Http::post('https://inventory.example.com/check');

// Manually traced (custom operation)
$paymentTrace = Log::startTrace('payment.stripe.charge');
$payment = \Stripe\Charge::create([...]);
Log::endTrace($paymentTrace, ['charge_id' => $payment->id]);

// Automatically traced
$order = Order::create([...]);
```

**Result:**
```
POST /api/checkout (800ms)
├─ http.request (120ms) ← Automatic
├─ payment.stripe.charge (500ms) ← Manual
└─ database.insert (45ms) ← Automatic
```

---

## Performance Impact

Automatic tracing has **minimal performance impact**:

- Event listeners add ~0.1ms per operation
- Traces only logged if duration > min_duration_ms
- Asynchronous sending to providers (non-blocking)

**Production-ready** - used in high-traffic applications without issues.

---

## Troubleshooting

### Traces Not Appearing

**Check auto-tracing is enabled:**
```bash
php artisan tinker
>>> config('globallogger.auto_tracing.enabled')
=> true
```

**Check specific type is enabled:**
```bash
>>> config('globallogger.auto_tracing.http')
=> true
```

### Too Many Traces

**Increase minimum duration:**
```env
GLOBALLOG_AUTO_TRACE_MIN_DURATION=50  # Only log operations > 50ms
```

**Disable noisy types:**
```env
GLOBALLOG_AUTO_TRACE_CACHE=false  # Cache can generate many traces
```

---

## Summary

✅ **Automatic Tracing** = Less code, automatic performance tracking
✅ **Manual Tracing** = Full control for custom operations
✅ **Use Both** = Best of both worlds!

Most applications should enable automatic tracing and only use manual tracing for custom operations not covered automatically.
