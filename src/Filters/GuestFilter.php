<?php

namespace Rcalicdan\Ci4Larabridge\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Rcalicdan\Ci4Larabridge\Facades\Auth;

class GuestFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authConfig = config('LarabridgeAuthentication');

        if (Auth::check()) {
            return redirect()->to($authConfig->filterLoginGuestRedirect ?? '/');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing after
    }
}
