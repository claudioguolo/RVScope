<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ImportTokenFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $configuredToken = trim((string) (
            env('security.importToken')
            ?: getenv('RVSCOPE_IMPORT_TOKEN')
            ?: ''
        ));

        if (strlen($configuredToken) < 32) {
            return service('response')
                ->setStatusCode(503)
                ->setJSON([
                    'error' => 'Importação automatizada não configurada.',
                ]);
        }

        $authorization = trim($request->getHeaderLine('Authorization'));
        $providedToken = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1
            ? trim($matches[1])
            : '';

        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'error' => 'Token de importação inválido.',
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
