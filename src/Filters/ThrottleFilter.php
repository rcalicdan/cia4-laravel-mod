<?php

namespace Rcalicdan\Ci4Larabridge\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ThrottleFilter implements FilterInterface
{
    private const DEFAULT_MAX_ATTEMPTS = 60;
    private const DEFAULT_DECAY_MINUTES = 1;
    private const SECONDS_PER_MINUTE = 60;
    private const API_PATH_INDICATOR = '/api/';
    private const JSON_CONTENT_TYPE = 'application/json';
    
    private const RATE_LIMIT_HEADERS = [
        'LIMIT' => 'X-RateLimit-Limit',
        'REMAINING' => 'X-RateLimit-Remaining', 
        'RESET' => 'X-RateLimit-Reset'
    ];

    /**
     * Executes before the controller action to handle rate limiting
     * 
     * @param RequestInterface $request The incoming request
     * @param mixed $arguments Optional throttle configuration [maxAttempts, decayMinutes]
     * @return RequestInterface|ResponseInterface Returns request if allowed, or throttle response if rate limited
     */
    public function before(RequestInterface $request, $arguments = null): RequestInterface|ResponseInterface
    {
        $throttleConfig = $this->parseThrottleConfig($arguments);
        $throttleKey = $this->generateThrottleKey($request);
        $throttler = service('throttler');
        
        if (!$this->isRequestAllowed($throttler, $throttleKey, $throttleConfig)) {
            return $this->createThrottleResponse($request, $throttler, $throttleConfig);
        }
        
        return $request;
    }

    /**
     * Executes after the controller action to add rate limit headers
     * 
     * @param RequestInterface $request The incoming request
     * @param ResponseInterface $response The outgoing response
     * @param mixed $arguments Optional throttle configuration [maxAttempts, decayMinutes]
     * @return ResponseInterface Returns the modified response with rate limit headers
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        if ($this->isApiRequest($request)) {
            $this->addRateLimitHeaders($response, $arguments);
        }
        
        return $response;
    }

    /**
     * Parses throttle configuration from arguments
     * 
     * @param array|null $arguments Optional throttle configuration [maxAttempts, decayMinutes]
     * @return array Returns parsed configuration with maxAttempts, decayMinutes and windowSeconds
     */
    private function parseThrottleConfig(?array $arguments): array
    {
        return [
            'maxAttempts' => isset($arguments[0]) ? (int)$arguments[0] : self::DEFAULT_MAX_ATTEMPTS,
            'decayMinutes' => isset($arguments[1]) ? (int)$arguments[1] : self::DEFAULT_DECAY_MINUTES,
            'windowSeconds' => (isset($arguments[1]) ? (int)$arguments[1] : self::DEFAULT_DECAY_MINUTES) * self::SECONDS_PER_MINUTE
        ];
    }

    /**
     * Generates a unique throttle key based on client IP and route
     * 
     * @param RequestInterface $request The incoming request
     * @return string Returns MD5 hash of client IP and route path
     */
    private function generateThrottleKey(RequestInterface $request): string
    {
        $clientIP = $request->getIPAddress();
        $route = $request->getUri()->getPath();
        
        return md5("{$clientIP}_{$route}");
    }

    /**
     * Checks if request is allowed based on rate limit configuration
     * 
     * @param mixed $throttler The throttler service instance
     * @param string $key The throttle key
     * @param array $config Throttle configuration
     * @return bool Returns true if request is allowed, false if rate limited
     */
    private function isRequestAllowed($throttler, string $key, array $config): bool
    {
        return $throttler->check($key, $config['maxAttempts'], $config['windowSeconds']) !== false;
    }

    /**
     * Creates appropriate throttle response based on request type
     * 
     * @param RequestInterface $request The incoming request
     * @param mixed $throttler The throttler service instance
     * @param array $config Throttle configuration
     * @return ResponseInterface Returns API or web throttle response
     */
    private function createThrottleResponse(RequestInterface $request, $throttler, array $config): ResponseInterface
    {
        $waitTime = $throttler->getTokentime();
        $minutesRemaining = ceil($waitTime / self::SECONDS_PER_MINUTE);
        
        if ($this->isApiRequest($request)) {
            return $this->createApiThrottleResponse($minutesRemaining, $waitTime);
        }
        
        return $this->createWebThrottleResponse($minutesRemaining);
    }

    /**
     * Creates API throttle response with JSON format
     * 
     * @param int $minutesRemaining Minutes until rate limit resets
     * @param int $waitTime Seconds until rate limit resets
     * @return ResponseInterface Returns JSON response with 429 status
     */
    private function createApiThrottleResponse(int $minutesRemaining, int $waitTime): ResponseInterface
    {
        return service('response')
            ->setStatusCode(429)
            ->setJSON([
                'error' => 'Too Many Requests',
                'message' => "Rate limit exceeded. Try again in {$minutesRemaining} minute(s).",
                'retry_after' => $waitTime
            ]);
    }

    /**
     * Creates web throttle response with flash message
     * 
     * @param int $minutesRemaining Minutes until rate limit resets
     * @return ResponseInterface Returns redirect response with flash message
     */
    private function createWebThrottleResponse(int $minutesRemaining): ResponseInterface
    {
        $errorMessage = "Too many attempts. Please try again in {$minutesRemaining} minute(s).";
        session()->setFlashdata('error', $errorMessage);
        
        return redirect()->back()->withInput();
    }

    /**
     * Adds rate limit headers to API responses
     * 
     * @param ResponseInterface $response The outgoing response
     * @param array|null $arguments Optional throttle configuration [maxAttempts, decayMinutes]
     */
    private function addRateLimitHeaders(ResponseInterface $response, ?array $arguments): void
    {
        $maxAttempts = isset($arguments[0]) ? (int)$arguments[0] : self::DEFAULT_MAX_ATTEMPTS;
        $decayMinutes = isset($arguments[1]) ? (int)$arguments[1] : self::DEFAULT_DECAY_MINUTES;
        $response->setHeader(self::RATE_LIMIT_HEADERS['LIMIT'], $maxAttempts);
        $resetTime = time() + $decayMinutes * self::SECONDS_PER_MINUTE;
        $response->setHeader(self::RATE_LIMIT_HEADERS['RESET'], $resetTime);
    }

    /**
     * Determines if request is an API request
     * 
     * @param RequestInterface $request The incoming request
     * @return bool Returns true if API path, JSON accept header or AJAX request
     */
    private function isApiRequest(RequestInterface $request): bool
    {
        return $this->hasApiPath($request) || 
               $this->hasJsonAcceptHeader($request) || 
               $request->isAJAX();
    }

    /**
     * Checks if request path contains API indicator
     * 
     * @param RequestInterface $request The incoming request
     * @return bool Returns true if path contains API indicator
     */
    private function hasApiPath(RequestInterface $request): bool
    {
        return str_contains($request->getUri()->getPath(), self::API_PATH_INDICATOR);
    }

    /**
     * Checks if request accepts JSON response
     * 
     * @param RequestInterface $request The incoming request
     * @return bool Returns true if Accept header contains application/json
     */
    private function hasJsonAcceptHeader(RequestInterface $request): bool
    {
        return str_contains($request->getHeaderLine('Accept'), self::JSON_CONTENT_TYPE);
    }
}