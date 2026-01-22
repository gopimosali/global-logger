<?php

namespace Gopimosali\GlobalLogger\Middleware;

use Closure;
use Gopimosali\GlobalLogger\GlobalLogger;
use Gopimosali\GlobalLogger\Utils\PrivacyHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogContextMiddleware
{
    public function __construct(
        protected GlobalLogger $logger
    ) {}

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

        // Prepare request context
        $requestContext = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ];

        // Add IP address (with privacy settings)
        if (config('globallogger.privacy.log_ip_address', true)) {
            $requestContext['ip'] = PrivacyHelper::anonymizeIp($request->ip());
        }

        // Add user agent (with privacy settings)
        if (config('globallogger.privacy.log_user_agent', true)) {
            $requestContext['user_agent'] = $request->userAgent();
        }

        // Add user ID (with privacy settings)
        if (config('globallogger.privacy.log_user_id', true) && $request->user()) {
            $requestContext['user_id'] = $request->user()->id;
        }

        // Add request context
        $contextManager->addContext($requestContext);

        $response = $next($request);

        // Include request_id in response headers if configured
        if (config('globallogger.request_id.include_in_response', true)) {
            $response->headers->set($headerName, $requestId);
        }

        return $response;
    }
}
