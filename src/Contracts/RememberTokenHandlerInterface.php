<?php

namespace Rcalicdan\Ci4Larabridge\Contracts;

interface RememberTokenHandlerInterface
{
    public function isEnabled(): bool;
    public function setRememberToken($user): void;
    public function checkRememberToken(): ?object;
    public function clearCookie(): void;
}
