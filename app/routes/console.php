<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Crawl settimanale dei siti web dei fornitori (catalog_format=web)
// Ogni lunedì alle 07:00 — dispatcha DiscoverWebsiteJob per ogni fornitore web attivo
Schedule::command('suppliers:crawl')
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/crawl-suppliers.log'));
