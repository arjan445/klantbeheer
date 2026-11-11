<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Limits how often one IP address may POST to a route:
 *
 *   ['filter' => 'throttle:5,3600']   // max 5 submissions per hour
 *
 * GET requests (showing the form) are never limited.
 */
class Throttle implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtolower($request->getMethod()) !== 'post') {
            return null;
        }

        $arguments = (array) $arguments;
        $capacity  = max(1, (int) ($arguments[0] ?? 5));
        $seconds   = max(1, (int) ($arguments[1] ?? HOUR));

        $key = 'throttle_' . md5($request->getIPAddress() . '|' . $request->getUri()->getPath());

        if (service('throttler')->check($key, $capacity, $seconds) === false) {
            return service('response')
                ->setStatusCode(429)
                ->setBody('Te veel aanvragen vanaf dit adres. Probeer het later opnieuw.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
