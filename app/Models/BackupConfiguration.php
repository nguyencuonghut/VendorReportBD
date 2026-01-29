<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;

class BackupConfiguration extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'is_active',
        'schedule',
        'backup_options',
        'google_drive_enabled',
        'google_drive_config',
        'email_notification',
        'notification_emails',
        'retention_days',
        'last_run_at',
        'next_run_at',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'schedule' => 'array',
        'backup_options' => 'array',
        'google_drive_enabled' => 'boolean',
        'google_drive_config' => 'array',
        'email_notification' => 'boolean',
        'notification_emails' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }

    public function getScheduleDescriptionAttribute(): string
    {
        $schedule = $this->schedule;

        if (!$schedule) return 'Không có lịch trình';

        switch ($schedule['type']) {
            case 'daily':
                return "Hằng ngày lúc {$schedule['time']}";
            case 'weekly':
                $days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
                return "Hằng tuần vào {$days[$schedule['day_of_week']]} lúc {$schedule['time']}";
            case 'monthly':
                return "Hằng tháng ngày {$schedule['day_of_month']} lúc {$schedule['time']}";
            default:
                return $schedule['cron_expression'] ?? 'Không xác định';
        }
    }

    public function getLastBackupAttribute()
    {
        return $this->logs()->latest()->first();
    }

    public function updateNextRunTime(): void
    {
        $schedule = $this->schedule;
        if (!$schedule) return;

        $now = Carbon::now();
        $nextRun = null;

        switch ($schedule['type']) {
            case 'daily':
                // Set thời gian hôm nay trước
                $todayAtScheduleTime = $now->copy()->setTimeFromTimeString($schedule['time']);

                // Nếu chưa chạy lần nào và thời gian hôm nay chưa qua, schedule cho hôm nay
                // Ngược lại, schedule cho ngày mai
                if (!$this->last_run_at && $now->lessThanOrEqualTo($todayAtScheduleTime)) {
                    $nextRun = $todayAtScheduleTime;
                } else {
                    $nextRun = $now->copy()->addDay()->setTimeFromTimeString($schedule['time']);
                }
                break;
            case 'weekly':
                $nextRun = $now->copy()->next($schedule['day_of_week'])->setTimeFromTimeString($schedule['time']);
                break;
            case 'monthly':
                $nextRun = $now->copy()->addMonth()->day($schedule['day_of_month'])->setTimeFromTimeString($schedule['time']);
                break;
        }

        if ($nextRun) {
            $this->update(['next_run_at' => $nextRun]);
        }
    }

    // Formatted dates for frontend
    public function getFormattedLastRunAtAttribute(): string
    {
        return $this->last_run_at ? $this->last_run_at->format('d/m/Y H:i') : 'Chưa có';
    }

    public function getFormattedNextRunAtAttribute(): string
    {
        return $this->next_run_at ? $this->next_run_at->format('d/m/Y H:i') : 'Chưa xác định';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([]) // Disable auto-logging, use custom logs in controllers instead
            ->dontSubmitEmptyLogs();
    }
}
