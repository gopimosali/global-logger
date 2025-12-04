# GlobalLogger Testing Guide

## 🧪 **Running Tests**

### **Package Tests (Recommended)**

The package includes comprehensive tests in `vendor/gopimosali/global-logger/tests/`:

```bash
# Run all package tests
vendor/bin/phpunit vendor/gopimosali/global-logger/tests

# Run specific test
vendor/bin/phpunit vendor/gopimosali/global-logger/tests/Feature/GlobalLoggerTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage vendor/gopimosali/global-logger/tests
```

### **Application Tests**

You can also copy tests to your application for integration testing:

```bash
cp vendor/gopimosali/global-logger/tests/Feature/GlobalLoggerTest.php tests/Feature/
```

Then run with Laravel's test command:

```bash
php artisan test --filter=GlobalLoggerTest
```

---

## 📝 **Generating Example Logs**

The package includes a powerful command to generate example logs for testing and demonstration:

### **Basic Usage**

```bash
# Generate 50 example logs (default)
php artisan globallogger:generate-examples

# Generate specific number of logs
php artisan globallogger:generate-examples --count=100

# Generate specific type
php artisan globallogger:generate-examples --type=structured

# Include error examples
php artisan globallogger:generate-examples --with-errors
```

### **Available Log Types**

| Type | Description | Examples |
|------|-------------|----------|
| `all` | All types (default) | Everything |
| `basic` | Standard PSR-3 levels | debug, info, notice, warning |
| `structured` | Semantic logs | metrics, audit, security |
| `http` | HTTP requests | GET/POST API calls |
| `performance` | Performance tracking | Query times, processing durations |
| `errors` | Error scenarios | Errors, critical, with PII redaction |

### **Example Commands**

```bash
# Generate 200 basic logs
php artisan globallogger:generate-examples --count=200 --type=basic

# Generate structured logs (metrics, audit, security)
php artisan globallogger:generate-examples --count=100 --type=structured

# Generate HTTP request examples (limited to 10)
php artisan globallogger:generate-examples --type=http

# Generate performance logs
php artisan globallogger:generate-examples --count=50 --type=performance

# Generate errors with PII redaction examples
php artisan globallogger:generate-examples --type=errors --with-errors

# Generate comprehensive test dataset
php artisan globallogger:generate-examples --count=500 --with-errors
```

---

## 🎯 **What Gets Generated**

### **1. Basic Logs**
- **Debug:** Database queries, cache misses, configuration loading
- **Info:** User actions, emails sent, file uploads
- **Notice:** Settings changes, deprecation warnings, maintenance alerts
- **Warning:** Slow queries, disk space, API timeouts

### **2. Structured Logs**

#### **Metrics:**
```php
Log::metric('api.response_time', 125.5, ['endpoint' => '/api/users']);
Log::metric('database.query_time', 45.2, ['table' => 'users']);
Log::metric('cache.hit_rate', 95.5, ['cache' => 'redis']);
Log::metric('sales.daily_revenue', 15000, ['region' => 'US']);
```

#### **Audit Events:**
```php
Log::audit('user.login', ['user_id' => 123, 'method' => 'oauth']);
Log::audit('order.created', ['order_id' => 1234, 'amount' => 99.99]);
Log::audit('payment.processed', ['transaction_id' => 'txn_xxx']);
```

#### **Security Events:**
```php
Log::security('failed_login_attempt', ['username' => 'admin', 'attempts' => 5]);
Log::security('suspicious_activity', ['user_id' => 123, 'reason' => 'Multiple IPs']);
Log::security('unauthorized_access_attempt', ['endpoint' => '/admin']);
```

### **3. HTTP Requests**
- Real HTTP calls to public APIs
- Automatic duration tracking
- Status code logging
- Error handling

### **4. Performance Logs**
```php
Log::performance('database.complex_query', 250.0, ['tables' => ['users', 'orders']]);
Log::performance('image.processing', 1500.0, ['size' => '1920x1080']);
Log::performance('pdf.generation', 2500.0, ['pages' => 25]);
```

### **5. Error Examples with PII Redaction**
- Payment processing failures (with card numbers - redacted)
- Database connection errors
- API authentication failures (with API keys - redacted)
- Memory exceptions
- Redis connection issues (with passwords - redacted)

---

## 🔍 **Viewing Generated Logs**

### **Database Query**

```sql
-- View all generated logs
SELECT * FROM global_logs
ORDER BY created_at DESC
LIMIT 100;

-- View by log type
SELECT * FROM global_logs
WHERE JSON_EXTRACT(context, '$.log_type') = 'metric'
LIMIT 50;

-- View metrics
SELECT
    JSON_EXTRACT(context, '$.metric_name') as metric,
    AVG(JSON_EXTRACT(context, '$.metric_value')) as avg_value,
    COUNT(*) as count
FROM global_logs
WHERE JSON_EXTRACT(context, '$.log_type') = 'metric'
GROUP BY metric;

-- View audit events
SELECT
    JSON_EXTRACT(context, '$.action') as action,
    COUNT(*) as count
FROM global_logs
WHERE JSON_EXTRACT(context, '$.log_type') = 'audit'
GROUP BY action;

-- View security events
SELECT * FROM global_logs
WHERE JSON_EXTRACT(context, '$.log_type') = 'security'
ORDER BY created_at DESC;

-- View errors
SELECT * FROM global_logs
WHERE level IN ('error', 'critical')
ORDER BY created_at DESC;

-- View performance metrics
SELECT
    JSON_EXTRACT(context, '$.operation') as operation,
    AVG(JSON_EXTRACT(context, '$.duration_ms')) as avg_duration,
    MAX(JSON_EXTRACT(context, '$.duration_ms')) as max_duration
FROM global_logs
WHERE JSON_EXTRACT(context, '$.log_type') = 'performance'
GROUP BY operation;
```

### **Log Visualizer Dashboard**

If you have `gopimosali/log-visualizer` installed, the generated logs will appear in:
- Metrics dashboard
- Audit log viewer
- Security events panel
- Performance charts
- Error tracking

---

## 🎓 **Use Cases**

### **Development**
```bash
# Test your logging setup
php artisan globallogger:generate-examples --count=100

# Test PII redaction
php artisan globallogger:generate-examples --type=errors --with-errors

# Test structured logging
php artisan globallogger:generate-examples --type=structured --count=50
```

### **Testing**
```bash
# Generate test data for integration tests
php artisan globallogger:generate-examples --count=1000 --with-errors

# Test specific features
php artisan globallogger:generate-examples --type=http
php artisan globallogger:generate-examples --type=performance
```

### **Demo/Training**
```bash
# Create comprehensive demo dataset
php artisan globallogger:generate-examples --count=500 --with-errors

# Show specific features
php artisan globallogger:generate-examples --type=structured --count=100
php artisan globallogger:generate-examples --type=performance --count=50
```

### **Performance Testing**
```bash
# Test batch logging performance
php artisan globallogger:generate-examples --count=10000

# Test database provider under load
php artisan globallogger:generate-examples --count=5000 --type=basic
```

---

## ⚙️ **Configuration for Testing**

### **Enable Database Provider**
```bash
# In .env
GLOBALLOG_DATABASE_ENABLED=true
GLOBALLOG_DATABASE_MIN_LEVEL=debug
```

### **Enable Batch Logging**
```bash
GLOBALLOG_BATCH_ENABLED=true
GLOBALLOG_BATCH_SIZE=100
```

### **Enable PII Redaction**
```bash
GLOBALLOG_REDACT_PII=true
GLOBALLOG_ANONYMIZE_IP=true
```

### **Disable Sampling for Testing**
```bash
GLOBALLOG_SAMPLING_ENABLED=false
```

---

## 🚀 **Quick Start Testing Workflow**

1. **Setup Database Provider:**
```bash
php artisan migrate
```

2. **Generate Example Logs:**
```bash
php artisan globallogger:generate-examples --count=500 --with-errors
```

3. **View Results:**
```sql
SELECT COUNT(*) FROM global_logs;  -- Should show ~500+ logs
```

4. **Test Features:**
```bash
# Test cleanup
php artisan globallogger:prune --days=30 --dry-run

# Test different log types
php artisan globallogger:generate-examples --type=structured
php artisan globallogger:generate-examples --type=performance
```

5. **View in Log Visualizer:**
- Navigate to your log-visualizer dashboard
- See metrics, audit events, security alerts
- Filter by request_id
- View performance charts

---

## 📊 **Expected Results**

After running `php artisan globallogger:generate-examples --count=100 --with-errors`, you should see approximately:

- **20 basic logs** (debug, info, notice, warning)
- **40 structured logs** (15 metrics, 15 audit, 10 security)
- **5 HTTP request logs** (with automatic tracing)
- **15 performance logs** (various operations)
- **10 error logs** (with PII redaction)

**Total:** ~90-100 log entries

All logs will have:
- ✅ Unique `request_id`
- ✅ Proper `log_type` classification
- ✅ Rich context data
- ✅ PII redaction applied
- ✅ Timestamp in ISO 8601 format

---

## 🐛 **Troubleshooting**

### **No logs generated?**
```bash
# Check database provider is enabled
php artisan config:cache
php artisan config:clear

# Verify migration ran
php artisan migrate:status

# Check database connection
php artisan tinker
>>> DB::table('global_logs')->count()
```

### **PII not redacted?**
```bash
# Verify configuration
php artisan config:show globallogger.privacy

# Clear config cache
php artisan config:clear
```

### **HTTP requests failing?**
```bash
# Check internet connectivity
curl https://jsonplaceholder.typicode.com/posts/1

# Use --type=http to isolate HTTP logging
php artisan globallogger:generate-examples --type=http --count=5
```

---

## 📚 **Further Reading**

- [Main Documentation](../../GLOBALLOGGER_IMPROVEMENTS.md)
- [Configuration Reference](../../config/globallogger.php)
- [Testing Examples](./tests/Feature/GlobalLoggerTest.php)

---

**Happy Testing!** 🎉
