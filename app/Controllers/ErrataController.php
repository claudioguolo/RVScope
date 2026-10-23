<?php

namespace App\Controllers;

use App\Libraries\ErrataFileStore;
use App\Libraries\ErrataReportBuilder;
use App\Libraries\ErrataReportCriteria;
use App\Libraries\HostDetailData;
use App\Models\RvtoolsVmInventoryModel;
use CodeIgniter\Controller;
use InvalidArgumentException;
use Throwable;

class ErrataController extends Controller
{
    public function daily()
    {
        $criteria = new ErrataReportCriteria($this->request->getGet());
        $source = $this->source('daily');
        $error = $criteria->error() ?? $source['error'];
        $days = $error === null ? (new ErrataReportBuilder())->daily($source['records'], $criteria) : [];
        $pages = max(1, (int) ceil(count($days) / 50));
        $page = min($criteria->page, $pages);
        return view('erratas/daily', compact('criteria', 'source', 'error', 'days', 'pages', 'page')
            + ['visibleDays' => array_slice($days, ($page - 1) * 50, 50), 'chartDays' => array_reverse(array_slice($days, 0, 90))]);
    }

    public function hosts()
    {
        $source = $this->source('hosts');
        $dates = array_values(array_unique(array_column($source['records'], 'date')));
        rsort($dates, SORT_STRING);
        $criteria = new ErrataReportCriteria($this->request->getGet(), $dates[0] ?? '');
        $error = $criteria->error() ?? $source['error'];
        $inventory = [];
        if ($error === null && $criteria->date !== '') {
            if (! in_array($criteria->date, $dates, true)) {
                $error = 'Selecione uma data com coleta de erratas por host.';
            } else {
                // Inclua também VMs excluídas no índice para não criar vínculos ambíguos por IP reutilizado.
                $inventory = (new RvtoolsVmInventoryModel())->where('reference_date', $criteria->date)
                    ->select('vm, dns_name, primary_ip, os_name, os_name_raw, creation_date_raw, annotation, power_state, included_in_reports')
                    ->findAll();
            }
        }
        $result = (new ErrataReportBuilder())->hosts($error === null ? $source['records'] : [], $inventory, $criteria->date, $criteria->term, $criteria->page);
        $details = new HostDetailData();
        $result['rows'] = $details->enrich($result['rows']);
        $catalogs = $result['rows'] === [] ? ['managementUnits' => [], 'technicalResponsiblesByManagementUnit' => [], 'operatingSystems' => []] : $details->catalogs();
        return view('erratas/hosts', compact('source', 'dates', 'criteria', 'error', 'result') + $catalogs
            + ['hasInventory' => $inventory !== [], 'alert' => session()->getFlashdata('hostInfoAlert')]);
    }

    public function sources()
    {
        return view('erratas/sources', [
            'dailySource' => $this->source('daily'), 'hostSource' => $this->source('hosts'),
            'tokenConfigured' => strlen(trim((string) (env('security.errataImportToken') ?: getenv('RVSCOPE_ERRATA_IMPORT_TOKEN') ?: ''))) >= 32,
            'message' => session()->getFlashdata('errata_message'), 'error' => session()->getFlashdata('errata_error'),
        ]);
    }

    public function import()
    {
        try {
            $kind = $this->request->getPost('kind');
            $file = $this->request->getFile('file');
            if (! is_string($kind) || ! in_array($kind, ['daily', 'hosts'], true)) {
                throw new InvalidArgumentException('Selecione uma fonte válida para importar.');
            }
            if ($file === null || ! $file->isValid() || $file->hasMoved()) {
                throw new InvalidArgumentException('Selecione um arquivo válido e confira o limite de upload do servidor.');
            }
            if ($file->getSize() > \App\Libraries\ErrataDataParser::MAX_BYTES) {
                throw new InvalidArgumentException('O arquivo deve ter no máximo 10 MiB.');
            }
            $expected = $kind === 'daily' ? 'txt' : 'csv';
            if (strtolower($file->getClientExtension()) !== $expected) {
                throw new InvalidArgumentException('Selecione um arquivo .' . $expected . ' para essa fonte.');
            }
            $content = file_get_contents($file->getTempName());
            if ($content === false) {
                throw new InvalidArgumentException('Não foi possível ler o arquivo enviado.');
            }
            $result = (new ErrataFileStore())->merge($kind, $content);
            session()->setFlashdata('errata_message', 'Importação concluída: ' . $result['received'] . ' registro(s) recebido(s), '
                . $result['stored'] . ' registro(s) no histórico. Reenvios do mesmo host e horário não duplicam as contagens.');
        } catch (InvalidArgumentException $exception) {
            session()->setFlashdata('errata_error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Não foi possível atualizar os arquivos de histórico de erratas.');
            session()->setFlashdata('errata_error', 'Não foi possível salvar as erratas. Confira o espaço e as permissões do volume writable.');
        }
        return redirect()->to(site_url('admin/erratas'));
    }

    public function collect()
    {
        $date = $this->request->getPost('data');
        $data = $this->request->getPost('dados');
        if (! is_string($date) || ! is_string($data) || strlen($date) > 32 || strpbrk($date . $data, "\r\n") !== false) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Informe data e dados válidos para uma coleta.']);
        }
        return $this->collectContent('daily', $date . ' - ' . $data . "\n");
    }

    public function collectHosts()
    {
        return $this->collectContent('hosts', $this->request->getBody());
    }

    private function collectContent(string $kind, string $content)
    {
        try {
            $result = (new ErrataFileStore())->merge($kind, $content);
            return $this->response->setJSON(['message' => 'Coleta de erratas recebida.', ...$result]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            log_message('error', 'Falha ao gravar coleta autenticada de erratas.');
            return $this->response->setStatusCode(503)->setJSON(['error' => 'Não foi possível salvar a coleta. Confira o volume writable com o administrador.']);
        }
    }

    private function source(string $kind): array
    {
        try {
            return (new ErrataFileStore())->read($kind) + ['error' => null];
        } catch (Throwable $exception) {
            return ['records' => [], 'invalid_lines' => [], 'exists' => true,
                'error' => 'Não foi possível ler o histórico de erratas. O administrador deve conferir o formato, tamanho e permissões dos arquivos no volume writable.'];
        }
    }
}
