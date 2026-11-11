<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Logs out accounts that are not (or no longer) activated.
 *
 * The password login checks `users.active`, but other ways in (magic-link
 * login, a remember-me cookie, a session that was already open when an
 * account was deactivated) do not. This filter runs on every request, so an
 * inactive account can never use the platform.
 */
class ActiveUser implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! auth()->loggedIn()) {
            return null;
        }

        $user = auth()->user();
        if ($user !== null && (int) $user->active === 1) {
            return null;
        }

        auth()->logout();

        return redirect()->to(site_url('login'))->with('error', 'Account is niet geactiveerd!');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
