<?php

/*
 |--------------------------------------------------------------------------
 | TESTING UTILIZADO COMO HOMOLOGAÇÃO
 |--------------------------------------------------------------------------
 | Neste projeto, o ambiente testing representa a homologação. Diferentemente
 | do padrão do CI4 para PHPUnit, detalhes de erros e recursos de debug não
 | devem ser enviados ao navegador.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

defined('SHOW_DEBUG_BACKTRACE') || define('SHOW_DEBUG_BACKTRACE', false);
defined('CI_DEBUG') || define('CI_DEBUG', false);
