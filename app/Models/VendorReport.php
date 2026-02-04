<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'workflow_type',
        'purchasing_admin_id',
        'created_by',
        'status',
        'current_step_id',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejected_note',
        'parent_id',
        'root_id',
        'revision_number',
    ];

    protected $casts = [
        'workflow_type' => 'string',
        'status' => 'string',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (VendorReport $report) {
            $report->files()->each(function (VendorReportFile $file) {
                $file->delete();
            });
        });
    }

    // ⭐ Enum Label Methods - Để Resource sử dụng
    public function getWorkflowTypeLabel(): string
    {
        return match($this->workflow_type) {
            'NORMAL' => 'Quy trình thông thường',
            'SPECIAL_1' => 'Quy trình qua 2 BOD',
            'SPECIAL_2' => 'Quy trình qua Khối Mua Hàng',
            'SPECIAL_3' => 'Quy trình qua Ban Kỹ thuật',
            'URGENT' => 'Quy trình khẩn cấp',
            default => $this->workflow_type,
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'DRAFT' => 'Nháp',
            'SUBMITTED' => 'Đã gửi',
            'IN_APPROVAL' => 'Đang duyệt',
            'APPROVED' => 'Đã duyệt',
            'REJECTED' => 'Từ chối',
            'CANCELED' => 'Hủy',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'DRAFT' => 'info',
            'SUBMITTED' => 'primary',
            'IN_APPROVAL' => 'warn',
            'APPROVED' => 'success',
            'REJECTED' => 'danger',
            'CANCELED' => 'secondary',
            default => 'secondary',
        };
    }

    public static function getWorkflowTypesWithLabels(): array
    {
        return [
            'NORMAL' => 'Quy trình thông thường',
            'SPECIAL_1' => 'Quy trình qua 2 BOD',
            'SPECIAL_2' => 'Quy trình qua Khối Mua Hàng',
            'SPECIAL_3' => 'Quy trình qua Ban Kỹ thuật',
            'URGENT' => 'Quy trình khẩn cấp',
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            'DRAFT' => 'Nháp',
            'SUBMITTED' => 'Đã gửi',
            'IN_APPROVAL' => 'Đang duyệt',
            'APPROVED' => 'Đã duyệt',
            'REJECTED' => 'Từ chối',
            'CANCELED' => 'Hủy',
        ];
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchasingAdmin()
    {
        return $this->belongsTo(User::class, 'purchasing_admin_id');
    }

    public function department()
    {
        return $this->hasOneThrough(
            Department::class,
            User::class,
            'id',           // Foreign key on users table
            'id',           // Foreign key on departments table
            'created_by',   // Local key on vendor_reports table
            'department_id' // Local key on users table
        );
    }

    public function currentStep()
    {
        return $this->belongsTo(VendorReportApprovalStep::class, 'current_step_id');
    }

    public function approvalSteps()
    {
        return $this->hasMany(VendorReportApprovalStep::class, 'report_id')->orderBy('step_order');
    }

    public function files()
    {
        return $this->hasMany(VendorReportFile::class, 'report_id');
    }

    public function parent()
    {
        return $this->belongsTo(VendorReport::class, 'parent_id');
    }

    public function root()
    {
        return $this->belongsTo(VendorReport::class, 'root_id');
    }

    public function children()
    {
        return $this->hasMany(VendorReport::class, 'parent_id');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'DRAFT');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'SUBMITTED');
    }

    public function scopeInApproval($query)
    {
        return $query->where('status', 'IN_APPROVAL');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }

    // Helper methods
    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function canBeCloned(): bool
    {
        // Chỉ clone được nếu: REJECTED và chưa có phiếu con
        // Dùng relationLoaded để tránh query lại nếu đã load
        $hasChildren = $this->relationLoaded('children')
            ? $this->children->count() > 0
            : $this->children()->exists();

        return $this->status === 'REJECTED' && !$hasChildren;
    }

    public function getAllAncestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }
}
