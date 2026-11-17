<?php

namespace App\Controllers;

use App\Libraries\HostSecurityCollection;
use App\Libraries\HostSecurityStore;
use CodeIgniter\Controller;
use InvalidArgumentException;
use Throwable;

class HostSecurityController extends Controller
{
    public function settings()
    {
        return view('admin/host_security', ['tokenConfigured' => strlen(trim((string) (env('security.hostSecurityToken') ?: getenv('RVSCOPE_HOST_SECURITY_TOKEN') ?: ''))) >= 32]);
    }

    public function collect()
    {
        if (strlen($this->request->getBody()) > 4096) {
            return $this->response->setStatusCode(413)->setJSON(['error' => 'Coleta excede 4 KiB.']);
        }
        try {
            $input = $this->request->getPost();
            $record = (new HostSecurityCollection())->validate($input);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $exception->getMessage()]);
        }
        try {
            $record = (new HostSecurityStore())->save($record);
            return $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['message' => 'Coleta recebida.', 'collection' => $record]);
        } catch (Throwable $exception) {
            log_message('error', 'Não foi possível salvar a coleta de segurança dos hosts.');
            return $this->response->setStatusCode(503)->setJSON(['error' => 'Não foi possível salvar a coleta. Confira o volume writable.']);
        }
    }

    public function index()
    {
        $dates = [];
        $date = $term = '';
        $error = null;
        $result = (new HostSecurityCollection())->report([]);
        try {
            $store = new HostSecurityStore();
            $dates = $store->dates();
            $date = $this->request->getGet('date') ?? ($dates[0] ?? '');
            $term = $this->request->getGet('q') ?? '';
            if (! is_string($date) || ! is_string($term) || strlen($term) > 254 || ($date !== '' && ! in_array($date, $dates, true))) {
                throw new InvalidArgumentException('Selecione uma data disponível e uma busca com até 254 caracteres.');
            }
            $term = trim($term);
            if ($date !== '') {
                $result = (new HostSecurityCollection())->report($store->read($date), $term);
            }
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $date = $term = '';
        } catch (Throwable $exception) {
            $error = 'Não foi possível consultar a coleta. Confira as permissões do volume writable.';
        }
        $total = count($result['rows']);
        $pages = max(1, (int) ceil($total / 50));
        $requestedPage = $this->request->getGet('page');
        $page = min($pages, max(1, is_scalar($requestedPage) ? (int) $requestedPage : 1));
        $result['rows'] = array_slice($result['rows'], ($page - 1) * 50, 50);
        return view('reports/host_security', compact('dates', 'date', 'term', 'error', 'result', 'total', 'page', 'pages'));
    }
}
