<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class DatabaseAvailabilityFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            $query = db_connect()->query('SELECT 1');
            $query->freeResult();

            return null;
        } catch (Throwable $exception) {
            log_message('warning', 'Banco de dados indisponível: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return service('response')
                ->setStatusCode(503)
                ->setHeader('Retry-After', '30')
                ->setBody(view('errors/database_unavailable'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
