<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VendorReportFile extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'report_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // ⭐ Enum Label Methods
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'REPORT_IMAGE' => 'Hình ảnh báo cáo',
            'QUOTATION' => 'Báo giá',
            'BOQ' => 'Bảng kê khối lượng',
            default => $this->type,
        };
    }

    // Relationships
    public function report()
    {
        return $this->belongsTo(VendorReport::class, 'report_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Helper methods
    public function getUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function delete(): ?bool
    {
        Storage::disk($this->disk)->delete($this->path);
        return parent::delete();
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'original_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
