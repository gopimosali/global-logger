# Changelog

All notable changes to `gopimosali/global-logger` will be documented in this file.

## [1.0.0] - 2025-01-17

### Added
- Initial release
- PSR-3 compliant logging interface
- Automatic request_id generation (UUID v4 and v7 support)
- Multi-provider support:
  - AWS CloudWatch + X-Ray
  - Datadog Logs + APM
  - Oracle Cloud Logging
  - Database logging
  - Custom file logging
- Automatic tracing for:
  - HTTP requests (Laravel Http facade)
  - Database queries (Eloquent and Query Builder)
  - Queue jobs
  - Email sending
  - Cache operations
- Manual trace support with startTrace() / endTrace()
- Automatic middleware injection for request context
- Exception handler with automatic logging
- Request ID conversion formats (X-Ray, Datadog)
- Comprehensive documentation
- Production-ready error handling

### Features
- Drop-in replacement for Laravel's Log facade
- Zero code changes required for basic usage
- Automatic correlation across all providers
- Optional performance tracing
- Configurable minimum trace duration
- Support for Laravel 10, 11, and 12
- PHP 8.1, 8.2, and 8.3 support
