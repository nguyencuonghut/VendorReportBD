<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VendorReportApprovalStep extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'report_id',
        'step_key',
        'step_order',
        'status',
        'assignee_user_id',
        'assignee_role',
        'acted_by',
        'acted_at',
        'note',
        'requires_selection',
        'selection_role',
        'selected_next_approver_id',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'acted_at' => 'datetime',
        'requires_selection' => 'boolean',
    ];

    // ⭐ Enum Label Methods
    public function getStepKeyLabel(): string
    {
        return match($this->step_key) {
            'DEPT_HEAD' => 'Trưởng phòng',
            'INTERNAL_CONTROL' => 'Kiểm soát nội bộ',
            'NATIONAL_PURCHASING' => 'Khối mua hàng toàn quốc',
            'TECH_BOARD' => 'Ban kỹ thuật',
            'BOD' => 'Ban giám đốc',
            'BOD_1' => 'Ban giám đốc (Lần 1)',
            'BOD_2' => 'Ban giám đốc (Lần 2)',
            default => $this->step_key,
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'PENDING' => 'Chờ duyệt',
            'APPROVED' => 'Đã duyệt',
            'REJECTED' => 'Từ chối',
            'SKIPPED' => 'Bỏ qua',
            null => 'N/A',
            default => $this->status ?? 'N/A',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'PENDING' => 'warn',
            'APPROVED' => 'success',
            'REJECTED' => 'danger',
            'SKIPPED' => 'secondary',
            default => 'secondary',
        };
    }

    // Relationships
    public function report()
    {
        return $this->belongsTo(VendorReport::class, 'report_id');
    }

    public function assigneeUser()
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function actedByUser()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function selectedNextApprover()
    {
        return $this->belongsTo(User::class, 'selected_next_approver_id');
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function canActOn(User $user): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        // Trường hợp 1: Đã được assign cụ thể cho user
        if ($this->assignee_user_id === $user->id) {
            return true;
        }

        // Trường hợp 2: Chưa assign user cụ thể, nhưng role khớp
        if (!$this->assignee_user_id && $this->assignee_role) {
            return $user->hasRole($this->assignee_role);
        }

        return false;
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['step_key', 'status', 'acted_by', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
