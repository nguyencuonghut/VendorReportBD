<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên vai trò là bắt buộc',
            'name.string' => 'Tên vai trò phải là chuỗi ký tự',
            'name.max' => 'Tên vai trò không được vượt quá 255 ký tự',
            'name.unique' => 'Tên vai trò đã tồn tại',
            'permissions.array' => 'Quyền hạn phải là một mảng',
            'permissions.*.exists' => 'Quyền hạn không tồn tại',
        ];
    }
}
