<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'properties' => 'collection',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get formatted causer name.
     */
    public function getCauserNameAttribute(): string
    {
        return $this->causer?->name ?? 'System';
    }

    /**
     * Get formatted subject type name.
     */
    public function getSubjectTypeNameAttribute(): string
    {
        if (!$this->subject_type) {
            return '';
        }

        return class_basename($this->subject_type);
    }

    /**
     * Get formatted created at date.
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i:s');
    }

    /**
     * Scope to get recent activities.
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->latest()->limit($limit);
    }

    /**
     * Scope to get activities by causer.
     */
    public function scopeByCauser($query, int $userId)
    {
        return $query->where('causer_id', $userId);
    }

    /**
     * Scope to get activities by subject type.
     */
    public function scopeBySubjectType($query, string $subjectType)
    {
        return $query->where('subject_type', $subjectType);
    }

    /**
     * Scope to search in description.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('description', 'like', '%' . $search . '%');
    }
}
