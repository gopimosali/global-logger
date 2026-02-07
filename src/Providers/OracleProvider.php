<?php

namespace Gopimosali\GlobalLogger\Providers;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;

class OracleProvider implements LogProviderInterface
{
    public function __construct(
        protected array $config
    ) {}

    public function log(string $level, string $message, array $context): void
    {
        try {
            // Oracle Logging Service requires this specific format
            $payload = [
                'specversion' => '1.0',
                'logEntryBatches' => [
                    [
                        'entries' => [
                            [
                                'data' => json_encode([
                                    'message' => $message,
                                    'level' => strtoupper($level),
                                    'application' => config('app.name'),
                                    'context' => $context,
                                ]),
                                'id' => $context['request_id'] ?? uniqid('log_', true),
                                'time' => gmdate('Y-m-d\TH:i:s.000\Z'),
                            ],
                        ],
                        'source' => config('app.name', 'laravel-app'),
                        'type' => 'com.oraclecloud.logging.custom',
                        'subject' => $this->config['log_id'],
                        'defaultlogentrytime' => gmdate('Y-m-d\TH:i:s.000\Z'),
                    ],
                ],
            ];

            $path = "/20200831/logs/{$this->config['log_id']}/actions/push";
            $body = json_encode($payload);
            $date = gmdate('D, d M Y H:i:s \G\M\T');
            $host = parse_url($this->config['endpoint'], PHP_URL_HOST);
            $contentSha256 = base64_encode(hash('sha256', $body, true));
            $signature = $this->generateSignature('post', $path, $body, $date, $host);

            $ch = curl_init("{$this->config['endpoint']}{$path}");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Host: {$host}",
                    'Content-Type: application/json',
                    "Content-Length: " . strlen($body),
                    "date: {$date}",
                    "x-content-sha256: {$contentSha256}",
                    "Authorization: {$signature}",
                ],
                CURLOPT_POSTFIELDS => $body,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode < 200 || $httpCode >= 300) {
                error_log("Oracle Cloud Logging failed: HTTP {$httpCode} - {$response}");
            }

            curl_close($ch);
        } catch (\Throwable $e) {
            error_log('Oracle Cloud Logging failed: '.$e->getMessage());
        }
    }

    protected function generateSignature(string $method, string $path, string $body, string $date, string $host): string
    {
        // Oracle Cloud Infrastructure signature generation
        // This is a simplified version - production code should use the official OCI SDK

        $contentLength = strlen($body);
        $contentType = 'application/json';
        $contentSha256 = base64_encode(hash('sha256', $body, true));

        $signingString = "(request-target): {$method} {$path}\n"
            ."host: {$host}\n"
            ."date: {$date}\n"
            ."content-length: {$contentLength}\n"
            ."content-type: {$contentType}\n"
            ."x-content-sha256: {$contentSha256}";

        $privateKey = openssl_pkey_get_private(file_get_contents($this->config['private_key_path']));
        openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $signatureBase64 = base64_encode($signature);

        return sprintf(
            'Signature version="1",headers="(request-target) host date content-length content-type x-content-sha256",keyId="%s/%s/%s",algorithm="rsa-sha256",signature="%s"',
            $this->config['tenancy_id'],
            $this->config['user_id'],
            $this->config['fingerprint'],
            $signatureBase64
        );
    }
}
