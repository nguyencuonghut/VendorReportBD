<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Role extends SpatieRole
{
    use LogsActivity;

    /**
     * Get role labels mapping
     */
    public static function getRoleLabels(): array
    {
        return [
            'admin_system' => 'Quản trị hệ thống',
            'requester' => 'Người mua hàng',
            'purchasing_admin' => 'Admin mua hàng',
            'accountant' => 'Kế toán',
            'internal_control' => 'Kiểm soát nội bộ',
            'national_purchasing' => 'Mua hàng toàn quốc',
            'tech_board' => 'Ban kỹ thuật',
            'bod' => 'Ban Giám đốc',
        ];
    }

    /**
     * Get the label attribute (Vietnamese display name)
     */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn () => self::getRoleLabels()[$this->name] ?? $this->name,
        );
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
