# Changelog

All notable changes to `gopimosali/global-logger` will be documented in this file.

## [1.6.2] - 2026-02-07

### Fixed
- **Oracle Cloud Logging Integration** - Complete rewrite of Oracle Cloud provider with correct API implementation
  - Fixed endpoint hostname format: `ingestion.logging.{region}.oci.oraclecloud.com` (was incorrectly using `logging-ingestion`)
  - Fixed API endpoint path: `POST /20200831/logs/{logId}/actions/push` (correct PutLogs operation)
  - Added required `x-content-sha256` header for POST requests (SHA256 hash of body, base64 encoded)
  - Added `host` header to request signature signing string
  - Added `Content-Length` header to HTTP requests
  - Updated payload structure to use `logEntryBatches` format (correct OCI API format)
  - Added proper HTTP status code validation and error logging
  - Fixed signature generation to include all required headers: `(request-target)`, `host`, `date`, `content-length`, `content-type`, `x-content-sha256`

### Changed
- **Oracle Cloud Logging** - Updated endpoint configuration and documentation
  - API Version: 20200831 (verified and tested)
  - HTTP Method: POST (not PUT as previously implemented)
  - Signature Algorithm: RSA-SHA256 with complete header set

### Added
- **README Documentation** - Comprehensive Oracle Cloud Logging setup guide
  - Complete setup steps from OCI Console configuration to Laravel integration
  - Regional endpoint list for all OCI regions
  - Detailed troubleshooting section with common issues and solutions
  - IAM policy requirements and examples
  - File permissions and security best practices
  - Testing and verification commands

### Technical Details
- Oracle Cloud Logging now uses official OCI Logging Ingestion API specification
- Proper request signing following Oracle Cloud Infrastructure authentication requirements
- Tested and verified working with Oracle Cloud Infrastructure in all regions
- All logs successfully ingested with full request context (request_id, timestamp, environment, application)

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
