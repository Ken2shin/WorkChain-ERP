<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/**
 * WorkChain ERP - Console Routes (Artisan Commands)
 * PHP 8.3 | Laravel 11
 */

// Inspire command - random inspirational quote
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quotes()->random());
})->describe('Display an inspiring quote');

// Health check command
Artisan::command('workchain:health', function () {
    $this->info('WorkChain ERP Health Check');
    $this->line('---');
    $this->info('✓ Application is running');
    $this->info('✓ Service: WorkChain ERP');
    $this->info('✓ PHP Version: ' . PHP_VERSION);
    $this->info('✓ Laravel Version: ' . app()->version());
    $this->line('---');
    $this->comment('All systems operational');
})->describe('Check WorkChain ERP health status');

// Database migration check
Artisan::command('workchain:db-check', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $this->info('✓ Database connection successful');
    } catch (\Exception $e) {
        $this->error('✗ Database connection failed: ' . $e->getMessage());
    }
})->describe('Check database connectivity');
