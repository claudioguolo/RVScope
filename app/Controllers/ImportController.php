<?php

namespace App\Controllers;

use App\Libraries\RvtoolsImporter;
use CodeIgniter\Controller;

class ImportController extends Controller
{
    public function form()
    {
        return view('reports/import', [
            'result' => null,
            'errorMessage' => null,
        ]);
    }

    public function index()
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->response
                ->setStatusCode(405)
                ->setJSON([
                    'error' => 'Use POST para executar a importacao.',
                ]);
        }

        $importer = new RvtoolsImporter();
        $result = $importer->importAll();

        if ($this->prefersHtmlResponse()) {
            return view('reports/import', [
                'result' => $result,
                'errorMessage' => null,
            ]);
        }

        return $this->response->setJSON($result);
    }

    private function prefersHtmlResponse(): bool
    {
        $accept = strtolower($this->request->getHeaderLine('Accept'));

        return $accept === ''
            || str_contains($accept, 'text/html')
            || str_contains($accept, 'application/xhtml+xml');
    }
}
