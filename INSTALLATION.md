# Installation & Setup Guide

## Quick Installation

### Step 1: Install via Composer

```bash
composer require gopimosali/global-logger
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="Gopimosali\GlobalLogger\GlobalLoggerServiceProvider" --tag="globallogger-config"
```

This creates `config/globallogger.php`.

### Step 3: Enable a Provider

Choose one or more providers and configure in `.env`:

#### Option A: Custom File Logging (Easiest for Testing)

```env
GLOBALLOG_CUSTOM_ENABLED=true
GLOBALLOG_CUSTOM_PATH=storage/logs/globallogger.log
```

#### Option B: AWS CloudWatch + X-Ray

```env
GLOBALLOG_AWS_ENABLED=true
AWS_DEFAULT_REGION=us-east-1
GLOBALLOG_AWS_LOG_GROUP=/aws/laravel/production
GLOBALLOG_AWS_LOG_STREAM=application
GLOBALLOG_XRAY_ENABLED=true
```

**Install AWS SDK:**
```bash
composer require aws/aws-sdk-php
```

#### Option C: Datadog

```env
GLOBALLOG_DATADOG_ENABLED=true
DATADOG_API_KEY=your_datadog_api_key
DATADOG_SERVICE=my-laravel-app
DATADOG_APM_ENABLED=true
```

**Install Datadog:**
```bash
composer require datadog/php-datadogstatsd
```

#### Option D: Database

```env
GLOBALLOG_DATABASE_ENABLED=true
GLOBALLOG_DB_CONNECTION=mysql
GLOBALLOG_DB_TABLE=global_logs
```

**Publish and run migration:**
```bash
php artisan vendor:publish --provider="Gopimosali\GlobalLogger\GlobalLoggerServiceProvider" --tag="globallogger-migrations"
php artisan migrate
```

#### Option E: Oracle Cloud

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

---

## Step 4: Test It!

```php
use Illuminate\Support\Facades\Log;

Log::info('GlobalLogger is working!', ['test' => true]);
```

**Check the logs:**

- **Custom File:** `tail -f storage/logs/globallogger.log`
- **Database:** `SELECT * FROM global_logs ORDER BY created_at DESC LIMIT 10;`
- **AWS CloudWatch:** AWS Console → CloudWatch → Log Groups
- **Datadog:** Datadog Console → Logs
- **Oracle:** Oracle Console → Logging

You should see a log entry with:
```json
{
  "message": "GlobalLogger is working!",
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "test": true
}
```

✅ **It's working! The request_id is automatically added!**

---

## Optional: Enable Automatic Tracing

Add to `.env`:

```env
GLOBALLOG_AUTO_TRACING_ENABLED=true
GLOBALLOG_AUTO_TRACE_HTTP=true
GLOBALLOG_AUTO_TRACE_DATABASE=true
GLOBALLOG_AUTO_TRACE_QUEUE=true
GLOBALLOG_AUTO_TRACE_MAIL=true
```

Now HTTP requests, database queries, queue jobs, and emails are automatically traced!

---

## Verification

### Verify Configuration

```bash
php artisan tinker
```

```php
>>> config('globallogger.providers.custom.enabled')
=> true

>>> config('globallogger.request_id.version')
=> 7

>>> config('globallogger.auto_tracing.enabled')
=> true
```

### Verify Middleware

```bash
php artisan route:list | grep LogContextMiddleware
```

You should see `LogContextMiddleware` in the middleware column.

### Verify Request ID in Response

Make an API request and check response headers:

```bash
curl -I https://your-app.com/api/test
```

You should see:
```
X-Request-ID: 550e8400-e29b-41d4-a716-446655440000
```

---

## Troubleshooting

### Logs Not Appearing

1. **Check provider is enabled:**
   ```bash
   php artisan tinker
   >>> config('globallogger.providers.custom.enabled')
   ```

2. **Check file permissions:**
   ```bash
   chmod -R 775 storage/logs
   chown -R www-data:www-data storage/logs
   ```

3. **Check AWS credentials:**
   ```bash
   aws sts get-caller-identity
   ```

### Request ID Not Showing

1. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Verify middleware is registered:**
   ```bash
   php artisan route:list
   ```

### Traces Not Appearing

1. **Check auto-tracing is enabled:**
   ```bash
   php artisan tinker
   >>> config('globallogger.auto_tracing.enabled')
   => true
   ```

2. **Check X-Ray daemon (for AWS):**
   ```bash
   curl http://localhost:2000
   ```

3. **Check Datadog agent (for Datadog):**
   ```bash
   curl http://localhost:8126
   ```

---

## Next Steps

✅ **Installation Complete!**

Now read:
1. **README.md** - Full documentation and examples
2. **AUTO_TRACING_GUIDE.md** - Automatic tracing guide
3. **PACKAGE_SUMMARY.md** - Architecture and design decisions

---

## Production Deployment

### Environment-Specific Configuration

**Development:**
```env
GLOBALLOG_CUSTOM_ENABLED=true
GLOBALLOG_AUTO_TRACING_ENABLED=true
```

**Staging:**
```env
GLOBALLOG_AWS_ENABLED=true
GLOBALLOG_AUTO_TRACING_ENABLED=true
```

**Production:**
```env
GLOBALLOG_AWS_ENABLED=true
GLOBALLOG_DATADOG_ENABLED=true
GLOBALLOG_AUTO_TRACING_ENABLED=true
GLOBALLOG_AUTO_TRACE_MIN_DURATION=10  # Only log slow operations
```

### IAM Permissions (AWS)

Create an IAM role with these permissions:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": "arn:aws:logs:*:*:*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "xray:PutTraceSegments",
        "xray:PutTelemetryRecords"
      ],
      "Resource": "*"
    }
  ]
}
```

### Datadog Agent Setup

Install Datadog agent on your servers:

```bash
DD_API_KEY=your_key DD_SITE="datadoghq.com" bash -c "$(curl -L https://s3.amazonaws.com/dd-agent/scripts/install_script.sh)"
```

---

**Need help? Check README.md or open an issue on GitHub!**
