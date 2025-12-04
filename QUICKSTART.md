# GlobalLogger Quick Start Guide

## 🚀 **Get Started in 2 Minutes**

GlobalLogger works **out of the box** with zero configuration!

### **Step 1: Verify Installation**

The package is already installed and registered in your Laravel application.

```bash
php artisan about
```

You should see GlobalLogger listed in service providers.

### **Step 2: Test Logging (No configuration needed!)**

```bash
# Generate example logs (uses file provider by default)
php artisan globallogger:generate-examples --count=50

# View logs
tail -f storage/logs/globallogger.log
```

**That's it!** You're now logging with automatic request correlation, privacy features, and structured logging! 🎉

---

## 📝 **Basic Usage**

### **Standard Logging (PSR-3)**

```php
use Illuminate\Support\Facades\Log;

// Works exactly like Laravel's Log
Log::debug('User cache miss', ['user_id' => 123]);
Log::info('Email sent successfully', ['to' => 'user@example.com']);
Log::warning('API rate limit approaching', ['current' => 80, 'limit' => 100]);
Log::error('Payment processing failed', ['order_id' => 456]);
```

### **Structured Logging (New!)**

```php
// Metrics
Log::metric('api.response_time', 125.5, ['endpoint' => '/api/users']);
Log::metric('daily_sales', 15000, ['currency' => 'USD']);

// Audit Events
Log::audit('user.login', ['user_id' => 123, 'method' => 'oauth']);
Log::audit('order.created', ['order_id' => 789, 'amount' => 99.99]);

// Security Events
Log::security('failed_login_attempt', ['username' => 'admin', 'attempts' => 5]);
Log::security('suspicious_activity', ['ip' => '1.2.3.4']);

// Performance Tracking
Log::performance('database.query', 250.0, ['query' => 'SELECT * FROM users']);
```

---

## 🎯 **Default Configuration (No .env changes needed)**

By default, GlobalLogger:

- ✅ **Logs to file:** `storage/logs/globallogger.log` (no database setup required!)
- ✅ **Automatic request IDs:** Every log gets a unique correlation ID
- ✅ **PII redaction:** Passwords, tokens, API keys automatically redacted
- ✅ **Batch logging:** Optimized database writes (99% reduction)
- ✅ **Circuit breaker:** Prevents cascading failures
- ✅ **Automatic tracing:** HTTP, database, queue, mail operations

---

## 📊 **View Your Logs**

### **File-based (Default)**

```bash
# Follow logs in real-time
tail -f storage/logs/globallogger.log

# Search for errors
grep '"level":"error"' storage/logs/globallogger.log

# Search by request ID (trace all logs for a single request)
grep '"request_id":"9d4e5f6a"' storage/logs/globallogger.log

# View metrics
grep '"log_type":"metric"' storage/logs/globallogger.log | jq
```

### **Database (Optional - Requires setup)**

```bash
# Enable database provider
echo "GLOBALLOG_DATABASE_ENABLED=true" >> .env

# Run migration
php artisan migrate

# Query logs
php artisan tinker
>>> DB::table('global_logs')->latest()->limit(10)->get()
```

---

## 🔧 **Optional: Enable More Features**

### **1. Database Logging (For Dashboards/Queries)**

```bash
# In .env
GLOBALLOG_DATABASE_ENABLED=true
```

```bash
# Run migration
php artisan migrate

# Generate test data
php artisan globallogger:generate-examples --count=500
```

### **2. GDPR Strict Mode**

```bash
# In .env
GLOBALLOG_LOG_USER_ID=false
GLOBALLOG_LOG_IP_ADDRESS=false
GLOBALLOG_ANONYMIZE_IP=true
```

### **3. High-Traffic Optimization**

```bash
# In .env
GLOBALLOG_SAMPLING_ENABLED=true
GLOBALLOG_SAMPLING_RATE=0.1  # Log 10% of requests
GLOBALLOG_ASYNC_ENABLED=true  # Queue-based logging
```

### **4. Multiple Providers**

```bash
# Enable both file and database
GLOBALLOG_CUSTOM_ENABLED=true
GLOBALLOG_DATABASE_ENABLED=true

# Send different levels to different places
GLOBALLOG_CUSTOM_MIN_LEVEL=debug    # All logs to file
GLOBALLOG_DATABASE_MIN_LEVEL=error  # Only errors to database
```

---

## 🎓 **Common Use Cases**

### **Development**

```bash
# Use default file logging - no setup needed!
# Just use Log::info() as normal
```

### **Production**

```bash
# .env
GLOBALLOG_DATABASE_ENABLED=true
GLOBALLOG_CUSTOM_ENABLED=true
GLOBALLOG_REDACT_PII=true
GLOBALLOG_BATCH_ENABLED=true
GLOBALLOG_CIRCUIT_BREAKER_ENABLED=true
```

### **High-Traffic**

```bash
# .env
GLOBALLOG_DATABASE_ENABLED=true
GLOBALLOG_DATABASE_MIN_LEVEL=warning
GLOBALLOG_SAMPLING_ENABLED=true
GLOBALLOG_SAMPLING_RATE=0.1
GLOBALLOG_ASYNC_ENABLED=true
```

### **Demo/Testing**

```bash
# Generate comprehensive test data
php artisan globallogger:generate-examples --count=1000 --with-errors
```

---

## 📚 **Next Steps**

### **1. Explore Features**

```bash
# View all commands
php artisan list globallogger

# Generate examples for each type
php artisan globallogger:generate-examples --type=structured
php artisan globallogger:generate-examples --type=performance
php artisan globallogger:generate-examples --type=errors --with-errors
```

### **2. Setup Scheduled Cleanup**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('globallogger:prune --days=30')
        ->weekly()
        ->sundays()
        ->at('01:00');
}
```

### **3. Read Full Documentation**

- [Complete Features](../../GLOBALLOGGER_IMPROVEMENTS.md)
- [Testing Guide](./TESTING.md)
- [Configuration Reference](./config/globallogger.php)
- [Environment Variables](./.env.example)

### **4. Install Log Visualizer (Optional)**

For a beautiful dashboard to view your logs:

```bash
composer require gopimosali/log-visualizer
```

---

## 🐛 **Troubleshooting**

### **No logs appearing?**

```bash
# Clear config cache
php artisan config:clear

# Verify provider is enabled
php artisan tinker
>>> config('globallogger.providers.custom.enabled')
# Should return: true
```

### **Want to see what's being logged?**

```bash
# Enable database temporarily
GLOBALLOG_DATABASE_ENABLED=true

# Generate examples
php artisan globallogger:generate-examples --count=10

# View in database
php artisan tinker
>>> DB::table('global_logs')->latest()->get()
```

### **Permission issues?**

```bash
# Ensure storage directory is writable
chmod -R 775 storage/logs
chown -R www-data:www-data storage/logs
```

---

## ✨ **Key Features You Get For Free**

| Feature | Status | Configuration Needed |
|---------|--------|---------------------|
| File Logging | ✅ Enabled | None - works out of box |
| Request Correlation | ✅ Enabled | None |
| PII Redaction | ✅ Enabled | None |
| Batch Logging | ✅ Enabled | None |
| Circuit Breaker | ✅ Enabled | None |
| Automatic Tracing | ✅ Enabled | None |
| Structured Logging | ✅ Available | Just use Log::metric(), etc. |
| Database Logging | ⚪ Optional | Set GLOBALLOG_DATABASE_ENABLED=true |
| Log Sampling | ⚪ Optional | Set GLOBALLOG_SAMPLING_ENABLED=true |
| Async Logging | ⚪ Optional | Set GLOBALLOG_ASYNC_ENABLED=true |

---

## 🎯 **One-Liner Setup for Different Scenarios**

### **Just Works (Default)**
```bash
# No configuration needed! Start logging immediately
Log::info('Hello GlobalLogger!');
```

### **Production Ready**
```bash
echo "GLOBALLOG_DATABASE_ENABLED=true" >> .env && php artisan migrate
```

### **GDPR Compliant**
```bash
echo "GLOBALLOG_LOG_USER_ID=false\nGLOBALLOG_ANONYMIZE_IP=true" >> .env
```

### **High Performance**
```bash
echo "GLOBALLOG_SAMPLING_ENABLED=true\nGLOBALLOG_ASYNC_ENABLED=true" >> .env
```

---

## 💡 **Pro Tips**

1. **Request Correlation:** Every log automatically includes a `request_id`. Use it to trace entire request flows!

2. **Zero Changes:** GlobalLogger is a drop-in replacement. Your existing `Log::` calls work unchanged.

3. **Structured Logs:** Use `Log::metric()`, `Log::audit()`, `Log::security()` for better analytics.

4. **Test Data:** Use `php artisan globallogger:generate-examples` to populate your dashboards.

5. **Privacy First:** PII is automatically redacted. No sensitive data leaks!

---

## 🆘 **Need Help?**

1. Check [.env.example](./.env.example) for all available options
2. Read [TESTING.md](./TESTING.md) for testing guide
3. See [GLOBALLOGGER_IMPROVEMENTS.md](../../GLOBALLOGGER_IMPROVEMENTS.md) for complete documentation

---

**Welcome to smarter logging!** 🎉

Start logging with `Log::info('Hello!')` and see the magic happen! ✨
