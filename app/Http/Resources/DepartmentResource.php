<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,

            // Relationships
            'head_user' => $this->when(
                $this->relationLoaded('headUser') && $this->headUser,
                fn() => [
                    'id' => $this->headUser->id,
                    'name' => $this->headUser->name,
                    'email' => $this->headUser->email,
                ]
            ),
            'parent' => $this->when(
                $this->relationLoaded('parent') && $this->parent,
                fn() => [
                    'id' => $this->parent->id,
                    'code' => $this->parent->code,
                    'name' => $this->parent->name,
                ]
            ),
            'children' => DepartmentResource::collection($this->whenLoaded('children')),
            'users_count' => $this->when($this->users_count !== null, $this->users_count),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
