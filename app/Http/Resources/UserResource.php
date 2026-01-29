<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->when(
                $this->relationLoaded('roles'),
                fn() => RoleResource::collection($this->roles)->resolve()
            ),
            'roles_count' => $this->when(
                isset($this->roles_count),
                fn() => $this->roles_count
            ),
            'deleted_at' => $this->deleted_at,
            'is_deleted' => $this->trashed(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
