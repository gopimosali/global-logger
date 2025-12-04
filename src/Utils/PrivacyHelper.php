<?php

namespace Gopimosali\GlobalLogger\Utils;

/**
 * PrivacyHelper - Utility for PII redaction and privacy compliance
 *
 * Provides methods to redact sensitive information from logs to comply
 * with GDPR, CCPA, and other privacy regulations.
 */
class PrivacyHelper
{
    /**
     * Redact sensitive fields from context array
     *
     * @param  array<string, mixed>  $context  Context data to redact
     * @return array<string, mixed> Redacted context
     */
    public static function redactContext(array $context): array
    {
        if (! config('globallogger.privacy.redact_pii', true)) {
            return $context;
        }

        $sensitiveFields = config('globallogger.privacy.redact_fields', []);

        return self::redactArrayRecursive($context, $sensitiveFields);
    }

    /**
     * Recursively redact sensitive fields from an array
     *
     * @param  array<string, mixed>  $data  Data to redact
     * @param  array<int, string>  $sensitiveFields  Fields to redact
     * @return array<string, mixed> Redacted data
     */
    protected static function redactArrayRecursive(array $data, array $sensitiveFields): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redactArrayRecursive($value, $sensitiveFields);
            } elseif (self::isSensitiveField($key, $sensitiveFields)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Check if a field name is sensitive
     *
     * @param  string|int  $fieldName  Field name to check
     * @param  array<int, string>  $sensitiveFields  List of sensitive field names
     */
    protected static function isSensitiveField(string|int $fieldName, array $sensitiveFields): bool
    {
        if (! is_string($fieldName)) {
            return false;
        }

        $fieldNameLower = strtolower($fieldName);

        foreach ($sensitiveFields as $sensitiveField) {
            $sensitiveFieldLower = strtolower($sensitiveField);

            // Exact match
            if ($fieldNameLower === $sensitiveFieldLower) {
                return true;
            }

            // Contains match (e.g., "user_password" contains "password")
            if (str_contains($fieldNameLower, $sensitiveFieldLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Anonymize IP address by replacing last octet with 0
     *
     * @param  string|null  $ip  IP address to anonymize
     * @return string|null Anonymized IP address
     */
    public static function anonymizeIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        if (! config('globallogger.privacy.anonymize_ip', false)) {
            return $ip;
        }

        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        // IPv6 - replace last 80 bits with zeros
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts[count($parts) - 1] = '0000';
            $parts[count($parts) - 2] = '0000';
            $parts[count($parts) - 3] = '0000';
            $parts[count($parts) - 4] = '0000';

            return implode(':', $parts);
        }

        return $ip;
    }

    /**
     * Sanitize request data based on privacy settings
     *
     * @param  array<string, mixed>  $requestData  Request data to sanitize
     * @return array<string, mixed> Sanitized request data
     */
    public static function sanitizeRequestData(array $requestData): array
    {
        $sanitized = $requestData;

        // Redact user ID if configured
        if (! config('globallogger.privacy.log_user_id', true) && isset($sanitized['user_id'])) {
            $sanitized['user_id'] = '[REDACTED]';
        }

        // Redact IP address if configured
        if (! config('globallogger.privacy.log_ip_address', true) && isset($sanitized['ip'])) {
            $sanitized['ip'] = '[REDACTED]';
        } elseif (isset($sanitized['ip'])) {
            $sanitized['ip'] = self::anonymizeIp($sanitized['ip']);
        }

        // Redact user agent if configured
        if (! config('globallogger.privacy.log_user_agent', true) && isset($sanitized['user_agent'])) {
            $sanitized['user_agent'] = '[REDACTED]';
        }

        return $sanitized;
    }

    /**
     * Redact sensitive data from query bindings
     *
     * @param  array<int, mixed>  $bindings  Query bindings
     * @return array<int, mixed> Redacted bindings
     */
    public static function redactQueryBindings(array $bindings): array
    {
        if (! config('globallogger.privacy.redact_pii', true)) {
            return $bindings;
        }

        return array_map(function ($binding) {
            // Only redact string bindings that look sensitive
            if (! is_string($binding)) {
                return $binding;
            }

            // If it looks like a hash (60+ chars, alphanumeric), it might be a password
            if (strlen($binding) >= 60 && ctype_alnum(str_replace(['$', '.', '/'], '', $binding))) {
                return '[REDACTED_HASH]';
            }

            // If it looks like a token (40+ chars, hexadecimal or base64)
            if (strlen($binding) >= 40 && (ctype_xdigit($binding) || base64_encode(base64_decode($binding, true)) === $binding)) {
                return '[REDACTED_TOKEN]';
            }

            return $binding;
        }, $bindings);
    }

    /**
     * Mask sensitive string (show only first and last few characters)
     *
     * @param  string  $value  Value to mask
     * @param  int  $visibleChars  Number of visible characters on each side
     * @return string Masked value
     */
    public static function maskString(string $value, int $visibleChars = 4): string
    {
        if (strlen($value) <= ($visibleChars * 2)) {
            return str_repeat('*', strlen($value));
        }

        $start = substr($value, 0, $visibleChars);
        $end = substr($value, -$visibleChars);
        $middle = str_repeat('*', strlen($value) - ($visibleChars * 2));

        return $start.$middle.$end;
    }
}
