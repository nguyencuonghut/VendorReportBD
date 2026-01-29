<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->title) ? trim($this->title) : $this->title,
            'content' => is_string($this->content) ? trim($this->content) : $this->content,
            'notes' => is_string($this->notes) ? trim($this->notes) : $this->notes,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'workflow_type' => ['sometimes', 'required', 'in:NORMAL,SPECIAL_1,SPECIAL_2,SPECIAL_3,URGENT'],
            'purchasing_admin_id' => ['nullable', 'exists:users,id'],
        ];
    }

    /**
     * Get custom validation messages in Vietnamese.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề phiếu là bắt buộc',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự',
            'workflow_type.required' => 'Loại quy trình là bắt buộc',
            'workflow_type.in' => 'Loại quy trình không hợp lệ',
            'purchasing_admin_id.exists' => 'Người quản lý mua sắm không tồn tại',
        ];
    }
}
