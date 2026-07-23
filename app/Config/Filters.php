<?php

namespace Config;

use App\Filters\DatabaseAvailabilityFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'              => CSRF::class,
        'toolbar'           => DebugToolbar::class,
        'honeypot'          => Honeypot::class,
        'invalidchars'      => InvalidChars::class,
        'secureheaders'     => SecureHeaders::class,
        'cors'              => Cors::class,
        'forcehttps'        => ForceHTTPS::class,
        'pagecache'         => PageCache::class,
        'performance'       => PerformanceMetrics::class,
        'databaseAvailable' => DatabaseAvailabilityFilter::class,
    ];

    public array $globals = [
        'before' => [
            'databaseAvailable',
        ],
        'after' => [],
    ];

    public array $methods = [];

    public array $filters = [];

    public function __construct()
    {
        parent::__construct();

        if (ENVIRONMENT === 'development') {
            $this->globals['after'][] = 'toolbar';
        }
    }
}
