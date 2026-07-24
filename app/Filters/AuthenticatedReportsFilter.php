<?php

namespace App\Filters;

use App\Libraries\UserAuthorization;
use App\Models\AppSettingModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthenticatedReportsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $settings = new AppSettingModel();
        if (! $settings->authenticatedReportsEnabled()) {
            return null;
        }

        $session = session();
        if (UserAuthorization::isAuthenticated()) {
            return null;
        }

        if (strtoupper($request->getMethod()) === 'GET') {
            $uri = $request->getUri();
            $reportRedirect = site_url(ltrim($uri->getPath(), '/'));
            if ($uri->getQuery() !== '') {
                $reportRedirect .= '?' . $uri->getQuery();
            }
            $session->set('authenticated_reports_redirect', $reportRedirect);
        }

        return redirect()->to(site_url('auth/login'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
