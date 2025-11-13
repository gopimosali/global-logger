# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to **gopimosali@example.com**.

You should receive a response within 48 hours. If for some reason you do not, please follow up via email to ensure we received your original message.

### What to Include

Please include the following information in your report:

- **Type of vulnerability** (e.g., XSS, SQL injection, code execution)
- **Full paths of source file(s)** related to the vulnerability
- **Location of the affected source code** (tag/branch/commit or direct URL)
- **Step-by-step instructions** to reproduce the issue
- **Proof-of-concept or exploit code** (if possible)
- **Impact of the vulnerability**, including how an attacker might exploit it

This information will help us triage your report more quickly.

## Disclosure Policy

When we receive a security bug report, we will:

1. **Confirm the problem** and determine affected versions
2. **Audit code** to find any similar problems
3. **Prepare fixes** for all supported versions
4. **Release new versions** as soon as possible
5. **Publish a security advisory** on GitHub

We appreciate your efforts to responsibly disclose your findings and will make every effort to acknowledge your contributions.

## Security Best Practices

When using GlobalLogger, we recommend:

### 1. Never Log Sensitive Data

```php
// ❌ DON'T DO THIS
Log::info('User login', [
    'password' => $request->password,
    'credit_card' => $request->cc_number,
]);

// ✅ DO THIS
Log::info('User login', [
    'user_id' => $user->id,
    'ip' => $request->ip(),
]);
```

### 2. Protect API Keys and Credentials

Store all provider credentials in `.env` and never commit them:

```env
# .env
DATADOG_API_KEY=your_secret_key
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
```

Add `.env` to your `.gitignore`:

```
.env
.env.*
```

### 3. Use IAM Roles for Cloud Providers

Instead of hardcoded credentials, use IAM roles:

**AWS:**
```php
// Don't store credentials in config
// Use EC2 instance roles or ECS task roles instead
```

**Datadog:**
```php
// Use environment variables, not hardcoded keys
'api_key' => env('DATADOG_API_KEY'),
```

### 4. Sanitize User Input in Logs

```php
// ✅ Sanitize user input
Log::info('Search query', [
    'query' => strip_tags($request->query),
    'user_id' => $user->id,
]);
```

### 5. Limit Log Retention

Configure appropriate retention periods:

```php
// config/globallogger.php
'providers' => [
    'custom' => [
        'max_files' => 7, // Keep logs for 7 days only
    ],
],
```

### 6. Secure Database Logs

If using the database provider:

```php
// Ensure proper database permissions
// Only grant INSERT and SELECT to logging user
GRANT INSERT, SELECT ON global_logs TO 'logger_user'@'localhost';
```

### 7. Encrypt Logs in Transit

Use HTTPS/TLS for all cloud providers:

```php
// AWS CloudWatch
'endpoint' => 'https://logs.us-east-1.amazonaws.com',

// Datadog
'host' => 'https://http-intake.logs.datadoghq.com',
```

### 8. Monitor for Injection Attacks

Be careful with dynamic log messages:

```php
// ❌ DON'T DO THIS (potential log injection)
Log::info($request->input('message'));

// ✅ DO THIS
Log::info('User message', [
    'message' => Str::limit($request->input('message'), 1000),
    'sanitized' => true,
]);
```

## Known Limitations

### Request ID Uniqueness

Request IDs are generated using UUID v7, which provides high uniqueness but is not cryptographically secure. Do not use request IDs for:

- Authentication tokens
- Security-sensitive identifiers
- Cryptographic purposes

### Provider Failures

If a logging provider fails, the package will catch the exception and continue. This is intentional to prevent logging issues from breaking your application. However, this means failed logs will be silently dropped.

Monitor your logging providers to ensure they're working correctly.

## Security Updates

Subscribe to security updates by:

1. **Watching this repository** on GitHub
2. **Enabling security alerts** in your GitHub settings
3. **Following releases** on Packagist

## Responsible Disclosure

We follow responsible disclosure practices and ask that you:

- **Give us reasonable time** to respond and fix the issue
- **Do not publicly disclose** the vulnerability until we've released a fix
- **Do not exploit** the vulnerability beyond what's necessary to demonstrate it

We will:

- **Respond promptly** to your report
- **Keep you informed** of our progress
- **Credit you** in the security advisory (if you wish)

## Past Security Advisories

No security advisories have been published yet.

You can view all published security advisories at:
https://github.com/gopimosali/global-logger/security/advisories

## Questions?

If you have questions about this security policy, please email gopimosali@example.com.

Thank you for helping keep GlobalLogger and its users safe! 🛡️
