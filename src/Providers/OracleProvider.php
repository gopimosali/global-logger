<?php

namespace Gopimosali\GlobalLogger\Providers;

use Gopimosali\GlobalLogger\Contracts\LogProviderInterface;

class OracleProvider implements LogProviderInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function log(string $level, string $message, array $context): void
    {
        try {
            $logEntry = [
                'specversion' => '1.0',
                'id' => $context['request_id'] ?? uniqid('log_', true),
                'type' => 'com.oracle.logging.custom',
                'source' => $this->config['compartment_id'],
                'time' => date('c'),
                'data' => [
                    'logSourceName' => config('app.name'),
                    'message' => $message,
                    'level' => strtoupper($level),
                    'context' => $context,
                ],
            ];

            $payload = [
                'logEvents' => [$logEntry],
            ];

            $signature = $this->generateSignature('POST', '/logEvents', json_encode($payload));

            $ch = curl_init("{$this->config['endpoint']}/logEvents");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    "Authorization: {$signature}",
                    "x-log-id: {$this->config['log_id']}",
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
            ]);

            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            error_log('Oracle Cloud Logging failed: '.$e->getMessage());
        }
    }

    protected function generateSignature(string $method, string $path, string $body): string
    {
        // Oracle Cloud Infrastructure signature generation
        // This is a simplified version - production code should use the official OCI SDK

        $date = gmdate('D, d M Y H:i:s T');
        $contentLength = strlen($body);
        $contentType = 'application/json';

        $signingString = "(request-target): {$method} {$path}\n"
            ."date: {$date}\n"
            ."content-length: {$contentLength}\n"
            ."content-type: {$contentType}";

        $privateKey = openssl_pkey_get_private(file_get_contents($this->config['private_key_path']));
        openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $signatureBase64 = base64_encode($signature);

        return sprintf(
            'Signature version="1",headers="(request-target) date content-length content-type",keyId="%s/%s/%s",algorithm="rsa-sha256",signature="%s"',
            $this->config['tenancy_id'],
            $this->config['user_id'],
            $this->config['fingerprint'],
            $signatureBase64
        );
    }
}
