<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user)
            ],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
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
            'name.required' => __('users.nameRequired'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string'),
            'email.required' => __('users.emailRequired'),
            'email.string' => __('validation.string'),
            'email.email' => __('users.emailInvalid'),
            'email.max' => __('validation.max.string'),
            'email.unique' => __('users.emailExists'),
            'roles.required' => 'Vai trò là bắt buộc',
            'roles.array' => 'Vai trò phải là một mảng',
            'roles.*.exists' => 'Vai trò được chọn không hợp lệ',
        ];
    }
}
