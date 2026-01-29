<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorReportFileResource extends JsonResource
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

            // Enum raw value
            'type' => $this->type,

            // ⭐ LABEL từ Model method
            'type_label' => $this->getTypeLabel(),

            // File info
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'path' => $this->path,
            'url' => $this->getUrl(),

            // Relationships
            'report' => new VendorReportResource($this->whenLoaded('report')),
            'uploader' => new UserResource($this->whenLoaded('uploader')),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
