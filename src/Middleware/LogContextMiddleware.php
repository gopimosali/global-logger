<?php

namespace Gopimosali\GlobalLogger\Middleware;

use Closure;
use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogContextMiddleware
{
    protected GlobalLogger $logger;

    public function __construct(GlobalLogger $logger)
    {
        $this->logger = $logger;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $contextManager = $this->logger->getContextManager();

        // Check if request already has an ID from upstream (load balancer, etc.)
        $headerName = config('globallogger.request_id.header', 'X-Request-ID');
        if ($request->hasHeader($headerName)) {
            $contextManager->setRequestId($request->header($headerName));
        }

        // Generate request_id if not set
        $requestId = $contextManager->getRequestId();

        // Add request context
        $contextManager->addContext([
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ]);

        $response = $next($request);

        // Include request_id in response headers if configured
        if (config('globallogger.request_id.include_in_response', true)) {
            $response->headers->set($headerName, $requestId);
        }

        return $response;
    }
}
