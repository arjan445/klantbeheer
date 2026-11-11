<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Rights on a route, so the controller does not have to check them:
 *
 *   $routes->get('organisations/overview', [...], ['filter' => 'can:organisations.manage']);
 *   $routes->get('users/overview', [...], ['filter' => 'can:users.view,users.manage']);  // one of these
 *   $routes->get('product/overview', [...], ['filter' => 'can:admin']);                  // admin role
 *
 * Uses user_can() from the access helper, so admin may always.
 * Without the right: redirect to /norights.
 */
class Can implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('access');

        foreach ((array) $arguments as $permission) {
            if ($permission === 'admin' ? is_admin() : user_can($permission)) {
                return null;
            }
        }

        return redirect()->to(base_url('/norights'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
