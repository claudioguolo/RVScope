<?php

namespace App\Controllers;

use App\Models\ManagementUnitModel;
use App\Models\ManagementUnitTechnicalResponsibleModel;
use App\Models\TechnicalResponsibleModel;
use CodeIgniter\Controller;

class CatalogController extends Controller
{
    public function index()
    {
        $managementUnits = (new ManagementUnitModel())
            ->where('is_deleted', false)
            ->orderBy('name', 'ASC')
            ->findAll();
        $technicalResponsibles = (new TechnicalResponsibleModel())->orderBy('name', 'ASC')->findAll();
        $relationships = (new ManagementUnitTechnicalResponsibleModel())->findAll();

        $managementIdsByResponsible = [];
        foreach ($relationships as $relationship) {
            $responsibleId = (int) ($relationship['technical_responsible_id'] ?? 0);
            $managementId = (int) ($relationship['management_unit_id'] ?? 0);
            if ($responsibleId > 0 && $managementId > 0) {
                $managementIdsByResponsible[$responsibleId][] = $managementId;
            }
        }

        return view('admin/catalogs', [
            'managementUnits' => $managementUnits,
            'technicalResponsibles' => $technicalResponsibles,
            'managementIdsByResponsible' => $managementIdsByResponsible,
            'message' => session()->getFlashdata('catalog_message'),
            'error' => session()->getFlashdata('catalog_error'),
        ]);
    }

    public function saveManagementUnit(?int $id = null)
    {
        $name = trim((string) ($this->request->getPost('name') ?? ''));
        $department = trim((string) ($this->request->getPost('department') ?? ''));
        $managerName = trim((string) ($this->request->getPost('manager_name') ?? ''));
        $managerPhone = trim((string) ($this->request->getPost('manager_phone') ?? ''));
        $email = strtolower(trim((string) ($this->request->getPost('management_email') ?? '')));
        $isActive = $this->request->getPost('is_active') !== null;

        if ($name === '') {
            return $this->catalogError('Preencha o nome da gerência.');
        }
        if ($isActive && ($department === '' || $managerName === '' || $email === '')) {
            return $this->catalogError(
                'Para uma gerência ativa, preencha departamento, gerente e e-mail da gerência.'
            );
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->catalogError('Informe um e-mail válido para a gerência.');
        }
        if (mb_strlen($name) > 160
            || mb_strlen($department) > 160
            || mb_strlen($managerName) > 160
            || mb_strlen($managerPhone) > 40
            || mb_strlen($email) > 254) {
            return $this->catalogError('Um dos campos da gerência excede o tamanho permitido.');
        }

        $model = new ManagementUnitModel();
        if ($id !== null) {
            $existingManagementUnit = $model->find($id);
            if (! is_array($existingManagementUnit)
                || $this->isTruthy($existingManagementUnit['is_deleted'] ?? false)) {
                return $this->catalogError('Gerência não encontrada.');
            }
        }

        $duplicate = $model->where('name', $name)->first();
        if (is_array($duplicate) && (int) ($duplicate['id'] ?? 0) !== (int) $id) {
            return $this->catalogError('Já existe uma gerência com esse nome.');
        }

        $data = [
            'name' => $name,
            'department' => $department,
            'manager_name' => $managerName,
            'manager_phone' => $managerPhone,
            'management_email' => $email,
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id === null) {
            $data['created_at'] = date('Y-m-d H:i:s');
        } else {
            $data['id'] = $id;
        }

        try {
            $model->save($data);
        } catch (\Throwable $exception) {
            log_message('error', 'Falha ao salvar gerência: {message}', ['message' => $exception->getMessage()]);
            return $this->catalogError('Não foi possível salvar a gerência.');
        }

        session()->setFlashdata('catalog_message', 'Gerência salva com sucesso.');
        return redirect()->to(site_url('admin/catalogs'));
    }

    public function deleteManagementUnit(int $id)
    {
        $model = new ManagementUnitModel();
        $managementUnit = $model->find($id);
        if (! is_array($managementUnit)
            || $this->isTruthy($managementUnit['is_deleted'] ?? false)) {
            return $this->catalogError('Gerência não encontrada.');
        }

        try {
            $model->update($id, [
                'is_deleted' => true,
                'is_active' => false,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Falha ao excluir gerência: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return $this->catalogError('Não foi possível excluir a gerência.');
        }

        session()->setFlashdata('catalog_message', 'Gerência marcada como excluída com sucesso.');
        return redirect()->to(site_url('admin/catalogs'));
    }

    public function saveTechnicalResponsible(?int $id = null)
    {
        $name = trim((string) ($this->request->getPost('name') ?? ''));
        $phone = trim((string) ($this->request->getPost('phone') ?? ''));
        $email = strtolower(trim((string) ($this->request->getPost('email') ?? '')));
        $managementIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($this->request->getPost('management_unit_ids') ?? [])),
            static fn (int $value): bool => $value > 0
        )));

        $isActive = $this->request->getPost('is_active') !== null;

        if ($name === '' || $email === '') {
            return $this->catalogError('Preencha nome e e-mail do responsável técnico.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->catalogError('Informe um e-mail válido para o responsável técnico.');
        }
        if (mb_strlen($name) > 160 || mb_strlen($phone) > 40 || mb_strlen($email) > 254) {
            return $this->catalogError('Um dos campos do responsável técnico excede o tamanho permitido.');
        }
        if ($managementIds === []) {
            return $this->catalogError('Vincule o responsável técnico a pelo menos uma gerência.');
        }

        $managementModel = new ManagementUnitModel();
        foreach ($managementIds as $managementId) {
            $managementUnit = $managementModel->find($managementId);
            if (! is_array($managementUnit)
                || $this->isTruthy($managementUnit['is_deleted'] ?? false)) {
                return $this->catalogError('Uma das gerências selecionadas não existe.');
            }
        }

        $model = new TechnicalResponsibleModel();
        if ($id !== null && ! is_array($model->find($id))) {
            return $this->catalogError('Responsável técnico não encontrado.');
        }
        if ($id !== null) {
            $assignedHosts = db_connect()->table('hosts_info')
                ->select('management_unit_id')
                ->where('technical_responsible_id', $id)
                ->get()
                ->getResultArray();
            foreach ($assignedHosts as $assignedHost) {
                $assignedManagementId = (int) ($assignedHost['management_unit_id'] ?? 0);
                if ($assignedManagementId > 0
                    && ! in_array($assignedManagementId, $managementIds, true)) {
                    return $this->catalogError(
                        'Não é possível remover um vínculo de gerência enquanto existirem hosts associados a ele.'
                    );
                }
            }
        }
        $duplicate = $model->where('name', $name)->first();
        if (is_array($duplicate) && (int) ($duplicate['id'] ?? 0) !== (int) $id) {
            return $this->catalogError('Já existe um responsável técnico com esse nome.');
        }

        $db = db_connect();
        try {
            $db->transStart();
            $data = [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'is_active' => $isActive,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($id === null) {
                $data['created_at'] = date('Y-m-d H:i:s');
                $model->insert($data);
                $id = (int) $model->getInsertID();
            } else {
                $model->update($id, $data);
            }

            $relationshipModel = new ManagementUnitTechnicalResponsibleModel();
            $relationshipModel->where('technical_responsible_id', $id)->delete();
            foreach ($managementIds as $managementId) {
                $relationshipModel->insert([
                    'management_unit_id' => $managementId,
                    'technical_responsible_id' => $id,
                ]);
            }
            $db->transComplete();
        } catch (\Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Falha ao salvar responsável técnico: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return $this->catalogError('Não foi possível salvar o responsável técnico.');
        }

        if (! $db->transStatus()) {
            return $this->catalogError('Não foi possível salvar o responsável técnico.');
        }

        session()->setFlashdata('catalog_message', 'Responsável técnico salvo com sucesso.');
        return redirect()->to(site_url('admin/catalogs'));
    }

    private function catalogError(string $message)
    {
        session()->setFlashdata('catalog_error', $message);
        return redirect()->to(site_url('admin/catalogs'));
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
