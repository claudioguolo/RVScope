<?php

namespace App\Filters;

use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PrivateEmailTestToolbar extends DebugToolbar
{
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $path = trim($request->getUri()->getPath(), '/');
        if (ENVIRONMENT !== 'development'
            || preg_match('~(?:^|/)(?:tools|admin)/email-test(?:/|$)~', $path)) {
            return null;
        }
        return parent::after($request, $response, $arguments);
    }
}
