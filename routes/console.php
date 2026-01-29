<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto Backup Scheduler
Schedule::command('backup:run-scheduled')
    ->everyMinute() // Chạy mỗi phút để check xem có backup nào cần chạy không
    ->withoutOverlapping() // Tránh chạy đồng thời nhiều backup
    ->runInBackground() // Chạy background để không block
    ->appendOutputTo(storage_path('logs/backup-scheduler.log'));
