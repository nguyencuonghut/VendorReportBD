<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
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
            'code' => is_string($this->code) ? trim($this->code) : $this->code,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
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
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
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
            'code.required' => 'Mã phòng ban là bắt buộc',
            'code.unique' => 'Mã phòng ban đã tồn tại',
            'code.max' => 'Mã phòng ban không được vượt quá 50 ký tự',
            'name.required' => 'Tên phòng ban là bắt buộc',
            'name.max' => 'Tên phòng ban không được vượt quá 255 ký tự',
            'head_user_id.exists' => 'Trưởng phòng không tồn tại',
            'parent_id.exists' => 'Phòng ban cha không tồn tại',
        ];
    }
}
