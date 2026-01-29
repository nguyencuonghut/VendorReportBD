<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    protected $fillable = [
        'backup_configuration_id',
        'status',
        'started_at',
        'completed_at',
        'duration',
        'file_name',
        'file_size',
        'google_drive_file_id',
        'error_message',
        'backup_details'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'backup_details' => 'array'
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(BackupConfiguration::class, 'backup_configuration_id');
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) return 'N/A';

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) return 'N/A';

        $seconds = $this->duration;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d giờ %d phút %d giây', $hours, $minutes, $remainingSeconds);
        } elseif ($minutes > 0) {
            return sprintf('%d phút %d giây', $minutes, $remainingSeconds);
        } else {
            return sprintf('%d giây', $remainingSeconds);
        }
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'running' => 'blue',
            'success' => 'green',
            'failed' => 'red',
            default => 'gray'
        };
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'running' => 'Đang chạy',
            'success' => 'Thành công',
            'failed' => 'Thất bại',
            default => 'Không xác định'
        };
    }
}
