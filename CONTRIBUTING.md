# Contributing to GlobalLogger

Thank you for considering contributing to GlobalLogger! This document provides guidelines and instructions for contributing.

## Code of Conduct

This project adheres to a Code of Conduct. By participating, you are expected to uphold this code. Please read [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When creating a bug report, include:

- **Clear title and description**
- **Steps to reproduce** the issue
- **Expected behavior** vs actual behavior
- **Laravel version**, PHP version, and package version
- **Code samples** if applicable
- **Error messages** and stack traces

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

- **Use a clear and descriptive title**
- **Provide a detailed description** of the suggested enhancement
- **Explain why this enhancement would be useful** to most users
- **List any similar features** in other packages

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow the coding standards** (see below)
3. **Add tests** for any new functionality
4. **Update documentation** as needed
5. **Ensure all tests pass**
6. **Run code quality checks**

## Development Setup

### Prerequisites

- PHP 8.1, 8.2, or 8.3
- Composer
- Git

### Setup Steps

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/global-logger.git
cd global-logger

# Install dependencies
composer install

# Run tests
composer test

# Run code quality checks
composer quality
```

## Coding Standards

### PSR-12 Compliance

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards.

```bash
# Check code style
composer format-check

# Auto-fix code style
composer format
```

### Static Analysis

We use PHPStan at level 6 for static analysis.

```bash
# Run static analysis
composer analyse
```

### Testing

All new features must include tests.

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage
```

### Test Guidelines

- Write **unit tests** for individual classes and methods
- Write **feature tests** for integration scenarios
- Aim for **high test coverage** (80%+ is ideal)
- Use **descriptive test names**: `it_generates_unique_request_id_per_request`
- Follow **Arrange-Act-Assert** pattern

Example:

```php
/** @test */
public function it_generates_unique_request_id_per_request()
{
    // Arrange
    $contextManager = new LogContextManager([]);

    // Act
    $requestId1 = $contextManager->getRequestId();
    $contextManager->clearContext();
    $requestId2 = $contextManager->getRequestId();

    // Assert
    $this->assertNotEquals($requestId1, $requestId2);
}
```

## Commit Messages

### Format

```
<type>: <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### Examples

```
feat: add support for custom trace sampling rates

Added configurable sampling rates for traces to reduce
overhead in high-traffic applications.

Closes #123
```

```
fix: prevent duplicate request IDs in concurrent requests

Fixed race condition where concurrent requests could
generate the same request ID.

Fixes #456
```

## Documentation

### README Updates

If your change affects usage, update the README:

- Add examples for new features
- Update configuration options
- Add troubleshooting tips

### Inline Documentation

- Add **PHPDoc blocks** for all public methods
- Include **parameter types** and **return types**
- Add **usage examples** for complex functionality
- Document **exceptions** that may be thrown

Example:

```php
/**
 * Convert request_id to AWS X-Ray trace ID format
 *
 * X-Ray uses a specific format: 1-{timestamp}-{24-hex-chars}
 * This method converts a standard UUID to X-Ray format while
 * preserving the original UUID in trace annotations.
 *
 * Example:
 * UUID: 550e8400-e29b-41d4-a716-446655440000
 * X-Ray: 1-1427846144-550e8400e29b41d4a716
 *
 * @param  string|null  $requestId  Request ID to convert (uses current if null)
 * @return string X-Ray formatted trace ID
 */
public function toXRayTraceId(?string $requestId = null): string
{
    // Implementation...
}
```

## Pull Request Process

### Before Submitting

1. **Update your fork** with the latest main branch
2. **Run all quality checks**: `composer quality`
3. **Update CHANGELOG.md** with your changes
4. **Update documentation** if needed

### PR Checklist

- [ ] Tests pass locally
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
- [ ] New tests added for new functionality
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Commit messages are clear and descriptive

### PR Description Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
How has this been tested?

## Checklist
- [ ] Tests pass
- [ ] Code style checked
- [ ] Documentation updated
- [ ] CHANGELOG updated
```

## Adding New Providers

If you're adding support for a new logging provider:

1. **Create provider class** in `src/Providers/`
2. **Implement `LogProviderInterface`**
3. **Add configuration** in `config/globallogger.php`
4. **Register in service provider**
5. **Add tests** in `tests/Unit/Providers/`
6. **Update documentation**
7. **Add to README** provider list

Example structure:

```php
namespace Gopimosali\GlobalLogger\Providers;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;

class NewProvider implements LogProviderInterface
{
    public function __construct(protected array $config) {}

    public function log(string $level, string $message, array $context): void
    {
        // Implementation
    }

    public function sendTrace(array $trace): void
    {
        // Implementation
    }
}
```

## Release Process

Maintainers will handle releases:

1. Update version in relevant files
2. Update CHANGELOG.md
3. Create Git tag
4. Push to GitHub
5. Packagist automatically updates

## Questions?

- **Open an issue** for questions
- **Join discussions** on GitHub
- **Email maintainers** for sensitive issues

## Recognition

Contributors will be acknowledged in:

- CHANGELOG.md
- GitHub contributors page
- Release notes

Thank you for contributing! 🎉
