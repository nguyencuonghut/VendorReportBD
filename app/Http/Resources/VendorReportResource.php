<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorReportResource extends JsonResource
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
            'title' => $this->title,

            // Enum raw values
            'workflow_type' => $this->workflow_type,
            'status' => $this->status,

            // ⭐ LABELS từ Model methods
            'workflow_type_label' => $this->getWorkflowTypeLabel(),
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Relationships
            'creator' => new UserResource($this->whenLoaded('creator')),
            'purchasing_admin' => new UserResource($this->whenLoaded('purchasingAdmin')),
            'current_step' => new VendorReportApprovalStepResource($this->whenLoaded('currentStep')),
            'approval_steps' => VendorReportApprovalStepResource::collection($this->whenLoaded('approvalSteps')),
            'files' => VendorReportFileResource::collection($this->whenLoaded('files')),
            'parent' => new VendorReportResource($this->whenLoaded('parent')),
            'root' => new VendorReportResource($this->whenLoaded('root')),
            'children' => VendorReportResource::collection($this->whenLoaded('children')),

            // Counts
            'approval_steps_count' => $this->when($this->approval_steps_count !== null, $this->approval_steps_count),
            'files_count' => $this->when($this->files_count !== null, $this->files_count),

            // Timestamps
            'submitted_at' => $this->submitted_at,
            'approved_at' => $this->approved_at,
            'rejected_at' => $this->rejected_at,
            'rejected_note' => $this->rejected_note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
