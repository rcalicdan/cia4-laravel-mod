<?php

namespace Rcalicdan\Ci4Larabridge\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Rcalicdan\Ci4Larabridge\Facades\Auth;

class EmailVerificationFilter implements FilterInterface
{
    private const EMAIL_VERIFICATION_URL = '/email/verify';

    public function before(RequestInterface $request, $arguments = null)
    {
        $config = Services::config('LarabridgeAuthentication');

        if (!$config->emailVerification['required']) {
            return;
        }

        if (Auth::guest()) {
            return redirect()->to($config->loginUrl);
        }

        $user = Auth::user();
        if (!$user->hasVerifiedEmail()) {
            return redirect()->to($config->verificationUrl ?? self::EMAIL_VERIFICATION_URL)
                ->with('error', 'Please verify your email address');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing after
    }
}
