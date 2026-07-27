<?php

namespace App\Commands;

use App\Libraries\RvtoolsImporter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class BackfillCsvInventory extends BaseCommand
{
    protected $group = 'RVScope';
    protected $name = 'rvscope:backfill-csv';
    protected $description = 'Preenche snapshots completos do inventário a partir do histórico de CSVs.';
    protected $usage = 'rvscope:backfill-csv --path /app/arquivos_csv [--execute --confirm BACKFILL]';
    protected $options = [
        '--path' => 'Diretório somente leitura com os CSVs históricos.',
        '--execute' => 'Executa a gravação. Sem esta opção, funciona em modo de análise.',
        '--confirm' => 'Confirmação obrigatória BACKFILL para executar alterações.',
    ];

    public function run(array $params)
    {
        $path = trim((string) (CLI::getOption('path') ?? '/app/arquivos_csv'));
        $execute = CLI::getOption('execute') !== null;
        $confirmation = strtoupper(trim((string) (CLI::getOption('confirm') ?? '')));

        $importer = new RvtoolsImporter();
        $inspection = $importer->inspectBackfill($path);

        CLI::write('Diretório: ' . $path);
        CLI::write('Arquivos encontrados: ' . (string) ($inspection['total_files'] ?? 0));
        CLI::write('Datas compatíveis selecionadas: ' . count($inspection['files'] ?? []));
        CLI::write('Critério: arquivo de nome mais recente para cada data.');

        $incompatible = $inspection['incompatible'] ?? [];
        CLI::write('Arquivos incompatíveis ignorados: ' . count($incompatible));
        foreach ($incompatible as $warning) {
            CLI::write('AVISO: ' . (string) $warning, 'yellow');
        }

        if (($inspection['errors'] ?? []) !== []) {
            foreach ($inspection['errors'] as $error) {
                CLI::error((string) $error);
            }
            return EXIT_ERROR;
        }

        if (! $execute) {
            CLI::write('Análise concluída. Nenhum dado foi alterado.', 'yellow');
            CLI::write('Para executar: adicione --execute --confirm BACKFILL');
            return EXIT_SUCCESS;
        }

        if ($confirmation !== 'BACKFILL') {
            CLI::error('Execução recusada. Informe --confirm BACKFILL.');
            return EXIT_ERROR;
        }

        CLI::write('Iniciando backfill transacional por data...', 'yellow');
        $result = $importer->backfill(
            $path,
            static function (
                int $current,
                int $total,
                string $filename,
                string $status,
            ): void {
                CLI::write(sprintf(
                    '[%d/%d] %s: %s',
                    $current,
                    $total,
                    strtoupper($status),
                    $filename,
                ));
            },
        );

        CLI::write('Datas processadas: ' . (string) ($result['processed'] ?? 0), 'green');
        CLI::write('Datas já completas/ignoradas: ' . (string) ($result['skipped'] ?? 0));

        foreach (($result['errors'] ?? []) as $error) {
            CLI::error((string) $error);
        }

        foreach (($result['incompatible'] ?? []) as $warning) {
            CLI::write('IGNORADO: ' . (string) $warning, 'yellow');
        }

        if (($result['errors'] ?? []) !== []) {
            CLI::error('Backfill concluído com arquivos incompatíveis. As demais datas foram preservadas.');
            return EXIT_ERROR;
        }

        CLI::write('Backfill concluído com sucesso.', 'green');
        return EXIT_SUCCESS;
    }
}
