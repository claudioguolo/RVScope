<?php

namespace App\Controllers;

use App\Libraries\OperatingSystemPolicyManager;
use CodeIgniter\Controller;

class OperatingSystemPolicyController extends Controller
{
    public function index()
    {
        return view('admin/operating_system_policies', [
            'operatingSystems' => (new OperatingSystemPolicyManager())->list(),
            'message' => session()->getFlashdata('os_policy_message'),
            'error' => session()->getFlashdata('os_policy_error'),
        ]);
    }

    public function update()
    {
        try {
            (new OperatingSystemPolicyManager())->update((array) ($this->request->getPost('ignored_os') ?? []));
            session()->setFlashdata(
                'os_policy_message',
                'Configuração salva. O inventário histórico e os relatórios foram recalculados.',
            );
        } catch (\Throwable $exception) {
            log_message('error', 'Falha ao atualizar filtros de sistemas operacionais: {message}', [
                'message' => $exception->getMessage(),
            ]);
            session()->setFlashdata('os_policy_error', 'Não foi possível atualizar os filtros de sistemas operacionais.');
        }

        return redirect()->to(site_url('admin/operating-systems'));
    }
}
