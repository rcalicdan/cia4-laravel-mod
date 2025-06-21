<?php

namespace Rcalicdan\Ci4Larabridge\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Rcalicdan\Ci4Larabridge\Facades\Auth;

class EmailVerificationFilter implements FilterInterface
{
    private const EMAIL_VERIFICATION_URL = '/email/verify';

    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config('LarabridgeAuthentication');

        if (! $config->emailVerification['required']) {
            return;
        }

        if (Auth::guest()) {
            return redirect()->to(site_url($config->filterLoginAuthUrl ?? '/login'));
        }

        $user = Auth::user();
        if (! $user->hasVerifiedEmail()) {
            return redirect()->to($config->emailVerificationUrl ?? self::EMAIL_VERIFICATION_URL)
                ->with('error', 'Please verify your email address')
            ;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing after
    }
}
