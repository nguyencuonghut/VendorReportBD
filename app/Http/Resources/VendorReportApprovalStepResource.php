<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorReportApprovalStepResource extends JsonResource
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
            'step_order' => $this->step_order,

            // Enum raw values
            'step_key' => $this->step_key,
            'status' => $this->status,

            // ⭐ LABELS từ Model methods
            'step_key_label' => $this->getStepKeyLabel(),
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Step info
            'requires_selection' => $this->requires_selection,
            'selection_role' => $this->selection_role,
            'assignee_role' => $this->assignee_role,

            // Action info
            'note' => $this->note,
            'acted_at' => $this->acted_at,

            // Relationships
            'report' => new VendorReportResource($this->whenLoaded('report')),
            'assignee_user' => new UserResource($this->whenLoaded('assigneeUser')),
            'acted_by_user' => new UserResource($this->whenLoaded('actedByUser')),
            'selected_next_approver' => new UserResource($this->whenLoaded('selectedNextApprover')),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
