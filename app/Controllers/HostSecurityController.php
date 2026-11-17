<?php

namespace App\Controllers;

use App\Libraries\HostSecurityCollection;
use App\Libraries\HostSecurityStore;
use App\Models\AppSettingModel;
use App\Models\RvtoolsVmInventoryModel;
use CodeIgniter\Controller;
use InvalidArgumentException;
use Throwable;

class HostSecurityController extends Controller
{
    public function settings()
    {
        return view('admin/host_security', ['linkedToRvtools' => (new AppSettingModel())->hostSecurityLinkedToRvtools(),
            'message' => session()->getFlashdata('host_security_message'),
            'error' => session()->getFlashdata('host_security_error'),
            'tokenConfigured' => strlen(trim((string) (env('security.hostSecurityToken') ?: getenv('RVSCOPE_HOST_SECURITY_TOKEN') ?: ''))) >= 32]);
    }

    public function saveSettings()
    {
        try {
            $value = $this->request->getPost('linked_to_rvtools') ?? '0';
            if (! is_string($value) || ! in_array($value, ['0', '1'], true)) {
                throw new InvalidArgumentException('Selecione uma opção válida para o vínculo com o RVTools.');
            }
            (new AppSettingModel())->setHostSecurityLinkedToRvtools($value === '1');
            session()->setFlashdata('host_security_message', 'Configuração salva. Os relatórios de segurança ' . ($value === '1' ? 'agora usam o inventário RVTools da mesma data.' : 'agora são independentes do inventário RVTools.'));
        } catch (Throwable $exception) {
            log_message('error', 'Não foi possível salvar a configuração do vínculo dos relatórios de segurança.');
            session()->setFlashdata('host_security_error', 'Não foi possível salvar a configuração. Tente novamente.');
        }
        return redirect()->to(site_url('admin/coletas/seguranca'));
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
        $linkedToRvtools = (new AppSettingModel())->hostSecurityLinkedToRvtools();
        $hasInventory = false;
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
                $inventory = null;
                if ($linkedToRvtools) {
                    $inventory = (new RvtoolsVmInventoryModel())->where('reference_date', $date)
                        ->select('vm, dns_name, primary_ip, power_state, included_in_reports')->findAll();
                    $hasInventory = $inventory !== [];
                }
                $result = (new HostSecurityCollection())->report($store->read($date), $term, $inventory);
            }
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $date = $term = '';
        } catch (Throwable $exception) {
            $error = 'Não foi possível consultar a coleta. Confira o volume writable e, no modo vinculado, o inventário RVTools.';
        }
        $total = count($result['rows']);
        $pages = max(1, (int) ceil($total / 50));
        $requestedPage = $this->request->getGet('page');
        $page = min($pages, max(1, is_scalar($requestedPage) ? (int) $requestedPage : 1));
        $result['rows'] = array_slice($result['rows'], ($page - 1) * 50, 50);
        return view('reports/host_security', compact('dates', 'date', 'term', 'error', 'result', 'total', 'page', 'pages', 'linkedToRvtools', 'hasInventory'));
    }
}
