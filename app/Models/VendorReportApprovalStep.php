<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        return match ($this->step_key) {
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
        return match ($this->status) {
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
        return match ($this->status) {
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

    // Alias for assigneeUser to match blade template naming
    public function assignee()
    {
        return $this->assigneeUser();
    }

    public function actedByUser()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    // Alias for actedByUser to match blade template naming
    public function actedBy()
    {
        return $this->actedByUser();
    }

    public function selectedNextApprover()
    {
        return $this->belongsTo(User::class, 'selected_next_approver_id');
    }

    /** Match the saved assignment; a selected user keeps access after a role change. */
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active || $user->trashed()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $assignment) use ($user) {
            $assignment->where('assignee_user_id', $user->id)
                ->orWhere(fn (Builder $role) => $role->whereNull('assignee_user_id')
                    ->whereIn('assignee_role', $user->getRoleNames()));
        });
    }

    /** Only the report's current, pending step is actionable. */
    public function scopePendingFor(Builder $query, User $user): Builder
    {
        return $query->assignedTo($user)->where('status', 'PENDING')
            ->whereHas('report', fn (Builder $report) => $report->inApproval()
                ->whereColumn('vendor_reports.current_step_id', 'vendor_report_approval_steps.id'));
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
        if (! $this->isPending() || ! $user->is_active || $user->trashed()) {
            return false;
        }

        // Trường hợp 1: Đã được assign cụ thể cho user
        if ($this->assignee_user_id === $user->id) {
            return true;
        }

        // Trường hợp 2: Chưa assign user cụ thể, nhưng role khớp
        if (! $this->assignee_user_id && $this->assignee_role) {
            return $user->hasRole($this->assignee_role);
        }

        return false;
    }

    public function pendingApprovers(): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query()->where('is_active', true);

        if (! $this->isPending()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->assignee_user_id) {
            return $query->whereKey($this->assignee_user_id);
        }

        return $this->assignee_role
            ? User::activeWithRole($this->assignee_role)
            : $query->whereRaw('1 = 0');
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
