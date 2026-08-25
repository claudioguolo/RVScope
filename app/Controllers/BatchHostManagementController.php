<?php

namespace App\Controllers;

use App\Libraries\BatchHostManagementPlanner;
use App\Models\ManagementUnitModel;
use CodeIgniter\Controller;
use Throwable;

class BatchHostManagementController extends Controller
{
    public function index()
    {
        $sourceId = max(0, (int) ($this->request->getGet('source_management_unit_id') ?? 0));
        $managementUnits = (new ManagementUnitModel())
            ->where('is_deleted', false)
            ->orderBy('is_active', 'DESC')
            ->orderBy('name', 'ASC')
            ->findAll();
        $activeManagementUnits = array_values(array_filter(
            $managementUnits,
            fn (array $row): bool => $this->isTruthy($row['is_active'] ?? false)
        ));

        $hosts = [];
        if ($sourceId > 0) {
            $hosts = db_connect()->table('hosts_info h')
                ->select('h.vm, h.technical_responsible_id, r.name AS technical_responsible_name')
                ->join('technical_responsibles r', 'r.id = h.technical_responsible_id', 'left')
                ->where('h.management_unit_id', $sourceId)
                ->orderBy('h.vm', 'ASC')
                ->get()
                ->getResultArray();
        }

        return view('admin/batch_host_management', [
            'managementUnits' => $managementUnits,
            'activeManagementUnits' => $activeManagementUnits,
            'sourceId' => $sourceId,
            'hosts' => $hosts,
            'message' => session()->getFlashdata('batch_management_message'),
            'error' => session()->getFlashdata('batch_management_error'),
        ]);
    }

    public function migrate()
    {
        $sourceId = max(0, (int) ($this->request->getPost('source_management_unit_id') ?? 0));
        $destinationId = max(0, (int) ($this->request->getPost('destination_management_unit_id') ?? 0));
        $vms = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $vm): string => trim((string) $vm),
                (array) ($this->request->getPost('vms') ?? [])
            ),
            static fn (string $vm): bool => $vm !== '' && mb_strlen($vm) <= 255
        )));

        if ($sourceId <= 0 || $destinationId <= 0 || $sourceId === $destinationId) {
            return $this->batchError('Selecione gerências de origem e destino diferentes.', $sourceId);
        }
        if ($vms === []) {
            return $this->batchError('Selecione pelo menos um host.', $sourceId);
        }

        $managementModel = new ManagementUnitModel();
        $source = $managementModel->find($sourceId);
        $destination = $managementModel->find($destinationId);
        if (! is_array($source) || $this->isTruthy($source['is_deleted'] ?? false)) {
            return $this->batchError('Gerência de origem não encontrada.', $sourceId);
        }
        if (! is_array($destination)
            || $this->isTruthy($destination['is_deleted'] ?? false)
            || ! $this->isTruthy($destination['is_active'] ?? false)) {
            return $this->batchError('A gerência de destino precisa estar ativa.', $sourceId);
        }

        $db = db_connect();
        $hosts = $db->table('hosts_info')
            ->select('vm, technical_responsible_id')
            ->where('management_unit_id', $sourceId)
            ->whereIn('vm', $vms)
            ->get()
            ->getResultArray();
        if ($hosts === []) {
            return $this->batchError('Nenhum dos hosts selecionados pertence à gerência de origem.', $sourceId);
        }

        $allowedResponsibleIds = array_column(
            $db->table('management_unit_technical_responsibles rel')
                ->select('rel.technical_responsible_id')
                ->join('technical_responsibles r', 'r.id = rel.technical_responsible_id')
                ->where('rel.management_unit_id', $destinationId)
                ->where('r.is_active', true)
                ->get()
                ->getResultArray(),
            'technical_responsible_id'
        );
        $plan = (new BatchHostManagementPlanner())->plan($hosts, $destinationId, $allowedResponsibleIds);
        $updates = $plan['updates'];
        $updatedAt = date('Y-m-d H:i:s');
        foreach ($updates as &$update) {
            $update['updated_at'] = $updatedAt;
        }
        unset($update);

        try {
            $db->transStart();
            foreach (array_chunk($updates, 500) as $batch) {
                $db->table('hosts_info')->updateBatch($batch, 'vm');
            }
            $db->transComplete();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Falha na migração em lote de hosts: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return $this->batchError('Não foi possível migrar os hosts.', $sourceId);
        }

        if (! $db->transStatus()) {
            return $this->batchError('Não foi possível migrar os hosts.', $sourceId);
        }

        $message = count($updates) . ' host(s) migrado(s) com sucesso.';
        $clearedCount = (int) ($plan['cleared_responsible_count'] ?? 0);
        if ($clearedCount > 0) {
            $message .= ' O responsável técnico foi removido de ' . $clearedCount
                . ' host(s) por não estar vinculado à gerência de destino.';
        }
        session()->setFlashdata('batch_management_message', $message);

        return redirect()->to(site_url(
            'admin/host-management-migration?source_management_unit_id=' . $sourceId
        ));
    }

    private function batchError(string $message, int $sourceId)
    {
        session()->setFlashdata('batch_management_error', $message);
        $query = $sourceId > 0 ? '?source_management_unit_id=' . $sourceId : '';

        return redirect()->to(site_url('admin/host-management-migration' . $query));
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
