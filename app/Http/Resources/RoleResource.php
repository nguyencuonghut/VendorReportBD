<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'permissions' => $this->when(
                $this->relationLoaded('permissions'),
                fn() => PermissionResource::collection($this->permissions)->resolve()
            ),
            'permissions_count' => $this->when(
                isset($this->permissions_count),
                fn() => $this->permissions_count
            ),
            'users_count' => $this->when(
                isset($this->users_count),
                fn() => $this->users_count
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
