<?php

namespace Rcalicdan\Ci4Larabridge\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Throttle Filter
 *
 * Implements rate limiting functionality for CodeIgniter 4 routes.
 * Supports different throttling strategies: by IP, user, or route.
 *
 * Usage:
 * - In routes: ['filter' => 'throttle:60,3600,ip'] (60 requests per hour by IP)
 * - In controller: protected $filters = ['throttle:10,60,user' => ['before' => 'method']]
 */
class ThrottleFilter implements FilterInterface
{
    /**
     * Execute filter before request processing
     *
     * @param  array|null  $arguments  [maxAttempts, timeWindow, keyType]
     * @return RequestInterface|ResponseInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = $this->parseArguments($arguments);
        $key = $this->generateKey($request, $config['keyType']);
        $throttleData = $this->getThrottleData($key, $config['timeWindow']);

        if ($this->isRateLimitExceeded($throttleData, $config['maxAttempts'])) {
            return $this->createRateLimitResponse($throttleData, $config['maxAttempts']);
        }

        $this->updateThrottleData($key, $throttleData, $config['timeWindow']);
        $this->addRateLimitHeaders($config['maxAttempts'], $throttleData);

        return $request;
    }

    /**
     * Execute filter after request processing
     *
     * @param  array|null  $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    /**
     * Parse filter arguments and set defaults
     *
     * @param  array|null  $arguments
     * @return array Configuration array
     */
    private function parseArguments($arguments): array
    {
        return [
            'maxAttempts' => isset($arguments[0]) ? (int) $arguments[0] : 60,
            'timeWindow' => isset($arguments[1]) ? (int) $arguments[1] : 60,
            'keyType' => isset($arguments[2]) ? $arguments[2] : 'ip',
        ];
    }

    /**
     * Generate unique throttle key based on key type
     *
     * @param  string  $keyType  ('ip', 'user', 'route')
     * @return string Unique cache key
     */
    private function generateKey(RequestInterface $request, string $keyType): string
    {
        switch ($keyType) {
            case 'user':
                $userId = $this->getUserId();

                return 'throttle_user_'.$userId;

            case 'route':
                return 'throttle_route_'.md5($request->getUri()->getPath());

            case 'ip':
            default:
                return 'throttle_ip_'.md5($request->getIPAddress());
        }
    }

    /**
     * Get current throttle data from cache
     *
     * @param  string  $key  Cache key
     * @param  int  $timeWindow  Time window in seconds
     * @return array Throttle data with count and reset_time
     */
    private function getThrottleData(string $key, int $timeWindow): array
    {
        $cache = \Config\Services::cache();
        $data = $cache->get($key) ?: ['count' => 0, 'reset_time' => time() + $timeWindow];

        // Reset if time window has passed
        if (time() > $data['reset_time']) {
            $data = ['count' => 0, 'reset_time' => time() + $timeWindow];
        }

        return $data;
    }

    /**
     * Check if rate limit has been exceeded
     *
     * @param  array  $throttleData  Current throttle data
     * @param  int  $maxAttempts  Maximum allowed attempts
     * @return bool True if rate limit exceeded
     */
    private function isRateLimitExceeded(array $throttleData, int $maxAttempts): bool
    {
        return $throttleData['count'] >= $maxAttempts;
    }

    /**
     * Update throttle data in cache
     *
     * @param  string  $key  Cache key
     * @param  array  $throttleData  Current throttle data
     * @param  int  $timeWindow  Time window in seconds
     */
    private function updateThrottleData(string $key, array &$throttleData, int $timeWindow): void
    {
        $cache = \Config\Services::cache();
        $throttleData['count']++;
        $cache->save($key, $throttleData, $timeWindow);
    }

    /**
     * Create rate limit exceeded response
     *
     * @param  array  $throttleData  Current throttle data
     * @param  int  $maxAttempts  Maximum allowed attempts
     * @return ResponseInterface 429 Too Many Requests response
     */
    private function createRateLimitResponse(array $throttleData, int $maxAttempts): ResponseInterface
    {
        $resetIn = $throttleData['reset_time'] - time();

        return \Config\Services::response()
            ->setStatusCode(429)
            ->setHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->setHeader('X-RateLimit-Remaining', '0')
            ->setHeader('X-RateLimit-Reset', (string) $throttleData['reset_time'])
            ->setHeader('Retry-After', (string) $resetIn)
            ->setJSON([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $resetIn,
                'reset_time' => $throttleData['reset_time'],
            ])
        ;
    }

    /**
     * Add rate limit headers to response
     *
     * @param  int  $maxAttempts  Maximum allowed attempts
     * @param  array  $throttleData  Current throttle data
     */
    private function addRateLimitHeaders(int $maxAttempts, array $throttleData): void
    {
        $response = \Config\Services::response();
        $remaining = max(0, $maxAttempts - $throttleData['count']);

        $response->setHeader('X-RateLimit-Limit', (string) $maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string) $remaining);
        $response->setHeader('X-RateLimit-Reset', (string) $throttleData['reset_time']);
    }

    /**
     * Get user ID for user-based throttling
     *
     * @return string User ID or 'anonymous' if not authenticated
     */
    private function getUserId(): string
    {
        if (function_exists('auth') && auth()->check()) {
            return (string) auth()->user()->id();
        }

        $session = session();

        if ($session->has('user_id')) {
            return (string) $session->get('auth_user_id');
        }

        return 'anonymous';
    }
}
