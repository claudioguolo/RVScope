<?php

namespace App\Filters;

use App\Libraries\UserAuthorization;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleAuthorizationFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! UserAuthorization::isAuthenticated()) {
            session()->set('authenticated_reports_redirect', current_url());

            return redirect()->to(site_url('auth/login'));
        }

        $allowedRoles = is_array($arguments) && $arguments !== []
            ? array_map([UserAuthorization::class, 'normalizeRole'], $arguments)
            : [UserAuthorization::ROLE_ADMIN];

        if (! in_array(UserAuthorization::currentRole(), $allowedRoles, true)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody('Você não possui permissão para realizar esta operação.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
