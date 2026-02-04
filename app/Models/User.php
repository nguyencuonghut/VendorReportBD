<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id', // ⭐ Mới
        'is_active',     // ⭐ Mới
        'last_login_at', // ⭐ Mới
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean', // ⭐ Mới
            'last_login_at' => 'datetime', // ⭐ Mới
        ];
    }

    /**
     * Relationships
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function createdReports()
    {
        return $this->hasMany(VendorReport::class, 'created_by');
    }

    public function assignedApprovalSteps()
    {
        return $this->hasMany(VendorReportApprovalStep::class, 'assignee_user_id');
    }

    public function uploadedFiles()
    {
        return $this->hasMany(VendorReportFile::class, 'uploaded_by');
    }

    /**
     * Check if user can view all reports in the system.
     * Management roles (BOD, dept head, etc.) can see all reports.
     * Regular requesters only see their own reports.
     */
    public function canViewAllReports(): bool
    {
        return $this->hasAnyRole([
            'admin_system',
            'purchasing_admin',
            'bod',
            'dept_head',
            'internal_control',
            'tech_board',
            'national_purchasing',
        ]);
    }

    /**
     * Get the options for activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([]) // Disable auto-logging, use custom logs in controllers instead
            ->dontSubmitEmptyLogs();
    }
}
