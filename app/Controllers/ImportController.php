<?php

namespace App\Controllers;

use App\Libraries\RvtoolsImporter;
use CodeIgniter\Controller;

class ImportController extends Controller
{
    public function index()
    {
        $importer = new RvtoolsImporter();
        $result = $importer->importAll();

        return $this->response->setJSON($result);
    }
}
