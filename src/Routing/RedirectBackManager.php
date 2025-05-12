<?php

namespace Rcalicdan\Ci4Larabridge\Routing;

if (! defined('REDIRECT_BACK_SESSION_KEY')) {
    define('REDIRECT_BACK_SESSION_KEY', '_redirect_back_history_array');
}
if (! defined('MAX_REDIRECT_HISTORY_SIZE')) {
    define('MAX_REDIRECT_HISTORY_SIZE', 5);
}

use Config\Services;

class RedirectBackManager
{
    protected $logger;
    protected $request;
    protected $session;
    protected static $generatedTokenForThisRequest = null;
    protected static $urlAssociatedWithGeneratedToken = null;

    public function __construct()
    {
        $this->logger = Services::logger();
        $this->request = Services::request();
        $this->session = Services::session();
    }

    /**
     * Generates or retrieves a redirect token and manages history.
     */
    public function ensureToken(): string
    {
        $tokenFromGet = $this->request->getGet('rb_token');

        if ($tokenFromGet) {
            return $this->handleExistingToken($tokenFromGet);
        }

        return $this->generateNewTokenIfNeeded();
    }

    /**
     * Retrieve the stored "back" URL for the current token from history, or fall back.
     */
    public function getRedirectBackUrl(string $default = ''): string
    {
        $tokenFromGet = $this->request->getGet('rb_token');
        $history = $this->getHistory();

        // Try to find URL by token
        if ($tokenFromGet) {
            $url = $this->findUrlByToken($tokenFromGet, $history);
            if ($url) {
                return $url;
            }
        }

        // Try fallbacks in sequence
        return $this->tryFallbacks($default, $history);
    }

    /**
     * Generates URL with redirect token parameter
     */
    public function urlWithBack(string $uri): string
    {
        $token = $this->ensureToken();
        $url = preg_match('#^https?://#', $uri) ? $uri : site_url($uri);

        return $this->appendTokenToUrl($url, $token);
    }

    /**
     * Generates route URL with redirect token parameter
     */
    public function routeWithBack(string $routeName, ...$params): string
    {
        $token = $this->ensureToken();
        $url = route_to($routeName, ...$params);

        return $this->appendTokenToUrl($url, $token);
    }

    /**
     * Handles an existing token from GET parameters
     */
    private function handleExistingToken(string $tokenFromGet): string
    {
        self::$generatedTokenForThisRequest = null;
        self::$urlAssociatedWithGeneratedToken = null;
        $this->logger->debug("[RB_CUSTOM] Token '{$tokenFromGet}' found in GET. Returning it sanitized. This token is for navigating BACK FROM this page.");

        return $this->sanitizeToken($tokenFromGet);
    }

    /**
     * Generates a new token if needed for the current URL
     */
    private function generateNewTokenIfNeeded(): string
    {
        $currentActualUrl = (string) current_url(true);

        // Check if we already have a token for this URL in this request
        if ($this->hasExistingTokenForUrl($currentActualUrl)) {
            return self::$generatedTokenForThisRequest;
        }

        // Generate new token and add to history
        $newToken = bin2hex(random_bytes(8));
        $this->logger->debug("[RB_CUSTOM] Generating new token '{$newToken}' to be associated with current URL '{$currentActualUrl}'.");

        $this->addToHistory($newToken, $currentActualUrl);

        // Save for reuse in this request
        self::$generatedTokenForThisRequest = $newToken;
        self::$urlAssociatedWithGeneratedToken = $currentActualUrl;

        return $newToken;
    }

    /**
     * Check if we already have a token for this URL in this request
     */
    private function hasExistingTokenForUrl(string $url): bool
    {
        if (self::$generatedTokenForThisRequest !== null && self::$urlAssociatedWithGeneratedToken === $url) {
            $this->logger->debug("[RB_CUSTOM] Reusing token '".self::$generatedTokenForThisRequest."' for current URL '{$url}' (already generated in this request).");

            return true;
        }

        return false;
    }

    /**
     * Add a new token and URL pair to history
     */
    private function addToHistory(string $token, string $url): void
    {
        $history = $this->getHistory();

        $newEntry = ['token' => $token, 'url' => $url];
        array_push($history, $newEntry);
        $this->logger->debug("[RB_CUSTOM] Pushed to history: Token '{$token}', URL '{$url}'. History size: ".count($history));

        $this->pruneHistory($history);

        $this->session->set(REDIRECT_BACK_SESSION_KEY, $history);
    }

    /**
     * Ensure history doesn't exceed maximum size
     */
    private function pruneHistory(array &$history): void
    {
        while (count($history) > MAX_REDIRECT_HISTORY_SIZE) {
            $removed = array_shift($history);
            $this->logger->debug("[RB_CUSTOM] History limit exceeded. Shifted: Token '{$removed['token']}', URL '{$removed['url']}'. New size: ".count($history));
        }
    }

    /**
     * Get the redirect history from session
     */
    private function getHistory(): array
    {
        $history = $this->session->get(REDIRECT_BACK_SESSION_KEY) ?? [];

        if (! is_array($history)) {
            $this->logger->warning('[RB_CUSTOM] Session data for '.REDIRECT_BACK_SESSION_KEY.' was not an array. Resetting.');
            $history = [];
        }

        return $history;
    }

    /**
     * Sanitize a token
     */
    private function sanitizeToken(string $token): string
    {
        return preg_replace('/[^A-Za-z0-9-]/', '', $token);
    }

    /**
     * Find a URL associated with a token in history
     */
    private function findUrlByToken(string $token, array $history): ?string
    {
        $sanitizedToken = $this->sanitizeToken($token);
        $this->logger->debug("[RB_CUSTOM] Attempting to find URL for token '{$sanitizedToken}' from GET.");

        foreach ($history as $item) {
            if (isset($item['token']) && $item['token'] === $sanitizedToken) {
                $this->logger->info("[RB_CUSTOM] Found URL '{$item['url']}' for token '{$sanitizedToken}' in history.");

                return $item['url'];
            }
        }

        $this->logger->debug("[RB_CUSTOM] Token '{$sanitizedToken}' not found in history. Proceeding to fallbacks.");

        return null;
    }

    /**
     * Try various fallback strategies to find a redirect URL
     */
    private function tryFallbacks(string $default, array $history): string
    {
        // Fallback 1: Explicit default URL
        if (str_starts_with($default, 'http://') || str_starts_with($default, 'https://')) {
            $this->logger->debug("[RB_CUSTOM] Default is a full URL: '{$default}'. Returning it.");

            return $default;
        }

        // Fallback 2: Named route
        if ($default !== '') {
            try {
                $url = route_to($default);
                $this->logger->debug("[RB_CUSTOM] Default is a route name '{$default}'. Resolved to '{$url}'. Returning it.");

                return $url;
            } catch (\Throwable $e) {
                $this->logger->error("[RB_CUSTOM] Invalid Route Name '{$default}' provided as default. Error: ".$e->getMessage());
            }
        }

        // Fallback 3: Latest from history
        $latestUrl = $this->getLatestUrlFromHistory($history);
        if ($latestUrl) {
            return $latestUrl;
        }

        // Fallback 4: HTTP Referer
        $refererUrl = $this->getValidRefererUrl();
        if ($refererUrl) {
            return $refererUrl;
        }

        // Fallback 5: Base site URL
        return $this->getBaseSiteUrl();
    }

    /**
     * Get the latest URL from history if available
     */
    private function getLatestUrlFromHistory(array $history): ?string
    {
        if (! empty($history)) {
            $latestEntry = end($history);
            if ($latestEntry && isset($latestEntry['url'])) {
                $this->logger->debug("[RB_CUSTOM] No default, using latest URL from history: '{$latestEntry['url']}'.");

                return $latestEntry['url'];
            }
        }

        return null;
    }

    /**
     * Get a valid referer URL if available
     */
    private function getValidRefererUrl(): ?string
    {
        $referer = $this->request->getServer('HTTP_REFERER');
        if ($referer) {
            $siteHost = parse_url(site_url(), PHP_URL_HOST);
            $refererHost = parse_url($referer, PHP_URL_HOST);

            if ($refererHost && $refererHost === $siteHost) {
                $safeReferer = filter_var($referer, FILTER_SANITIZE_URL);
                $this->logger->debug("[RB_CUSTOM] Using same-host referer: '{$safeReferer}'.");

                return $safeReferer;
            }
            $this->logger->debug("[RB_CUSTOM] Referer '{$referer}' is external or invalid. Ignoring.");
        } else {
            $this->logger->debug('[RB_CUSTOM] No referer available.');
        }

        return null;
    }

    /**
     * Get the base site URL as last fallback
     */
    private function getBaseSiteUrl(): string
    {
        $finalUrl = site_url('/');
        $this->logger->debug("[RB_CUSTOM] All fallbacks failed. Returning site_url('/'): '{$finalUrl}'.");

        return $finalUrl;
    }

    /**
     * Append token to URL as a query parameter
     */
    private function appendTokenToUrl(string $url, string $token): string
    {
        $sep = str_contains($url, '?') ? '&' : '?';

        return "{$url}{$sep}rb_token={$token}";
    }
}
