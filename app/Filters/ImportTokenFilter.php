<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ImportTokenFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $hostSecurity = in_array('hostSecurity', (array) $arguments, true);
        $erratas = in_array('erratas', (array) $arguments, true);
        $configuredToken = trim((string) (
            env($hostSecurity ? 'security.hostSecurityToken' : ($erratas ? 'security.errataImportToken' : 'security.importToken'))
            ?: getenv($hostSecurity ? 'RVSCOPE_HOST_SECURITY_TOKEN' : ($erratas ? 'RVSCOPE_ERRATA_IMPORT_TOKEN' : 'RVSCOPE_IMPORT_TOKEN'))
            ?: ''
        ));

        if (strlen($configuredToken) < 32) {
            return service('response')
                ->setStatusCode(503)
                ->setJSON([
                    'error' => $hostSecurity ? 'Coleta de segurança não configurada.' : ($erratas ? 'Coleta de erratas não configurada.' : 'Importação automatizada não configurada.'),
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
