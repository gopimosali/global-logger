<?php

namespace Gopimosali\GlobalLogger\Utils;

use Illuminate\Support\Facades\Cache;

/**
 * CircuitBreaker - Prevents cascading failures by stopping calls to failing providers
 *
 * Implements the Circuit Breaker pattern:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Provider is failing, requests are blocked
 * - HALF_OPEN: Testing if provider has recovered
 */
class CircuitBreaker
{
    /**
     * Circuit breaker states
     */
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Create a new CircuitBreaker instance
     *
     * @param  string  $name  Unique identifier for this circuit (e.g., 'aws_provider')
     */
    public function __construct(
        protected string $name
    ) {}

    /**
     * Check if circuit allows the operation to proceed
     */
    public function canProceed(): bool
    {
        if (! config('globallogger.performance.circuit_breaker.enabled', true)) {
            return true;
        }

        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            // Check if timeout has elapsed
            if ($this->isTimeoutElapsed()) {
                $this->transitionToHalfOpen();

                return true;
            }

            return false;
        }

        if ($state === self::STATE_HALF_OPEN) {
            // Allow limited requests to test if provider recovered
            return $this->canHalfOpenRequest();
        }

        return true;
    }

    /**
     * Record a successful operation
     */
    public function recordSuccess(): void
    {
        if (! config('globallogger.performance.circuit_breaker.enabled', true)) {
            return;
        }

        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Provider recovered, close the circuit
            $this->transitionToClosed();
        }

        // Reset failure count
        Cache::forget($this->getFailureCountKey());
    }

    /**
     * Record a failed operation
     */
    public function recordFailure(): void
    {
        if (! config('globallogger.performance.circuit_breaker.enabled', true)) {
            return;
        }

        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Provider still failing, reopen the circuit
            $this->transitionToOpen();

            return;
        }

        if ($state === self::STATE_CLOSED) {
            $failureCount = $this->incrementFailureCount();
            $threshold = config('globallogger.performance.circuit_breaker.failure_threshold', 5);

            if ($failureCount >= $threshold) {
                $this->transitionToOpen();
            }
        }
    }

    /**
     * Get current circuit state
     */
    protected function getState(): string
    {
        return Cache::get($this->getStateKey(), self::STATE_CLOSED);
    }

    /**
     * Transition to OPEN state (provider is failing)
     */
    protected function transitionToOpen(): void
    {
        Cache::put($this->getStateKey(), self::STATE_OPEN, now()->addMinutes(10));
        Cache::put($this->getOpenedAtKey(), now()->timestamp, now()->addMinutes(10));
    }

    /**
     * Transition to HALF_OPEN state (testing if provider recovered)
     */
    protected function transitionToHalfOpen(): void
    {
        Cache::put($this->getStateKey(), self::STATE_HALF_OPEN, now()->addMinutes(10));
        Cache::put($this->getHalfOpenRequestsKey(), 0, now()->addMinutes(10));
    }

    /**
     * Transition to CLOSED state (provider recovered)
     */
    protected function transitionToClosed(): void
    {
        Cache::forget($this->getStateKey());
        Cache::forget($this->getFailureCountKey());
        Cache::forget($this->getOpenedAtKey());
        Cache::forget($this->getHalfOpenRequestsKey());
    }

    /**
     * Check if timeout has elapsed since circuit opened
     */
    protected function isTimeoutElapsed(): bool
    {
        $openedAt = Cache::get($this->getOpenedAtKey());

        if ($openedAt === null) {
            return true;
        }

        $timeout = config('globallogger.performance.circuit_breaker.timeout_seconds', 60);

        return (now()->timestamp - $openedAt) >= $timeout;
    }

    /**
     * Check if half-open request can proceed
     */
    protected function canHalfOpenRequest(): bool
    {
        $requests = Cache::get($this->getHalfOpenRequestsKey(), 0);
        $maxRequests = config('globallogger.performance.circuit_breaker.half_open_requests', 1);

        if ($requests < $maxRequests) {
            Cache::increment($this->getHalfOpenRequestsKey());

            return true;
        }

        return false;
    }

    /**
     * Increment failure count and return new count
     */
    protected function incrementFailureCount(): int
    {
        $key = $this->getFailureCountKey();
        $count = Cache::get($key, 0);
        $count++;
        Cache::put($key, $count, now()->addMinutes(10));

        return $count;
    }

    /**
     * Get cache key for circuit state
     */
    protected function getStateKey(): string
    {
        return "globallogger:circuit:{$this->name}:state";
    }

    /**
     * Get cache key for failure count
     */
    protected function getFailureCountKey(): string
    {
        return "globallogger:circuit:{$this->name}:failures";
    }

    /**
     * Get cache key for opened timestamp
     */
    protected function getOpenedAtKey(): string
    {
        return "globallogger:circuit:{$this->name}:opened_at";
    }

    /**
     * Get cache key for half-open requests count
     */
    protected function getHalfOpenRequestsKey(): string
    {
        return "globallogger:circuit:{$this->name}:half_open_requests";
    }

    /**
     * Get circuit status for monitoring
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->getState(),
            'failure_count' => Cache::get($this->getFailureCountKey(), 0),
            'opened_at' => Cache::get($this->getOpenedAtKey()),
            'can_proceed' => $this->canProceed(),
        ];
    }

    /**
     * Reset circuit breaker (for testing or manual recovery)
     */
    public function reset(): void
    {
        $this->transitionToClosed();
    }
}
