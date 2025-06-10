<?php

namespace Rcalicdan\Ci4Larabridge\Authentication;

use Config\Services;
use Rcalicdan\Ci4Larabridge\Contracts\RememberTokenHandlerInterface;

/**
 * Handles remember token functionality
 */
class RememberTokenHandler implements RememberTokenHandlerInterface
{
    protected $config;
    protected $response;
    protected $request;
    protected $userModel;

    public function __construct($config, string $userModel)
    {
        $this->config = $config;
        $this->userModel = $userModel;
        $this->response = Services::response();
        $this->request = Services::request();
    }

    /**
     * Check if remember me is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config->rememberMe['enabled'];
    }

    /**
     * Set remember token for user
     */
    public function setRememberToken($user): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $token = $user->generateRememberToken();

        $this->setCookie($user->id, $token);
    }

    /**
     * Check and authenticate user via remember token
     */
    public function checkRememberToken(): ?object
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cookieValue = $this->getCookieValue();
        if (! $cookieValue) {
            return null;
        }

        $userData = $this->parseCookieValue($cookieValue);
        if (! $userData) {
            $this->clearCookie();

            return null;
        }

        $user = $this->findUserByToken($userData['userId'], $userData['token']);
        if (! $user) {
            $this->clearCookie();

            return null;
        }

        return $user;
    }

    /**
     * Clear remember token cookie
     */
    public function clearCookie(): void
    {
        helper('cookie');
        delete_cookie($this->config->rememberMe['cookieName']);
    }

    /**
     * Set remember token cookie
     */
    protected function setCookie(int $userId, string $token): void
    {
        $name = $this->config->rememberMe['cookieName'];
        $value = "{$userId}|{$token}";
        $expire = time() + $this->config->rememberMe['tokenExpiry'];
        $domain = '';
        $path = '/';
        $secure = $this->config->rememberMe['cookieSecure'];
        $httponly = $this->config->rememberMe['cookieHttpOnly'];

        $this->response->setCookie(
            $name,
            $value,
            $expire,
            $domain,
            $path,
            '',
            $secure,
            $httponly,
            'Lax'
        )->send();
    }

    /**
     * Get cookie value
     */
    protected function getCookieValue(): ?string
    {
        return $this->request->getCookie($this->config->rememberMe['cookieName']);
    }

    /**
     * Parse cookie value
     */
    protected function parseCookieValue(string $cookieValue): ?array
    {
        $parts = explode('|', $cookieValue, 2);

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'userId' => $parts[0],
            'token' => $parts[1],
        ];
    }

    /**
     * Find user by remember token
     */
    protected function findUserByToken(string $userId, string $token): ?object
    {
        $model = $this->userModel;

        return $model::where('id', $userId)
            ->where('remember_token', hash('sha256', $token))
            ->first()
        ;
    }
}
