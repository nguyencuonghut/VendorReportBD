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
            'report_image' => ['nullable', 'image', 'max:10240'], // 10MB max, optional on update
            'quotation_files' => ['nullable', 'array'],
            'quotation_files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
            'boq_files' => ['nullable', 'array'],
            'boq_files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
            'delete_files' => ['nullable', 'array'],
            'delete_files.*' => ['integer', 'exists:vendor_report_files,id'],
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
            'report_image.image' => 'File ảnh báo cáo phải là định dạng hình ảnh',
            'report_image.max' => 'Ảnh báo cáo không được vượt quá 10MB',
            'quotation_files.*.file' => 'File báo giá không hợp lệ',
            'quotation_files.*.mimes' => 'File báo giá phải có định dạng: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG',
            'quotation_files.*.max' => 'Mỗi file báo giá không được vượt quá 10MB',
            'boq_files.*.file' => 'File BOQ không hợp lệ',
            'boq_files.*.mimes' => 'File BOQ phải có định dạng: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG',
            'boq_files.*.max' => 'Mỗi file BOQ không được vượt quá 10MB',
        ];
    }
}
