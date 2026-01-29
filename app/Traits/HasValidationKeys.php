<?php

namespace App\Traits;

trait HasValidationKeys
{
    /**
     * Get validation rules with error keys instead of messages
     *
     * @param array $rules
     * @param array $keyMappings
     * @return array
     */
    protected function getValidationRules(array $rules, array $keyMappings = [])
    {
        return $rules;
    }

    /**
     * Get validation error keys instead of translated messages
     *
     * @param array $keyMappings
     * @return array
     */
    protected function getValidationErrorKeys(array $keyMappings = [])
    {
        $defaultMappings = [
            'email.required' => 'validation.emailRequired',
            'email.email' => 'validation.email',
            'email.exists' => 'validation.emailNotExists',
            'password.required' => 'validation.passwordRequired',
            'password.confirmed' => 'validation.passwordConfirmed',
            'password.min' => 'validation.passwordMin',
            'token.required' => 'validation.tokenRequired',
        ];

        return array_merge($defaultMappings, $keyMappings);
    }

    /**
     * Return validation errors with keys
     *
     * @param \Illuminate\Http\Request $request
     * @param array $rules
     * @param array $keyMappings
     * @return \Illuminate\Http\Response
     */
    protected function validateWithKeys($request, array $rules, array $keyMappings = [])
    {
        try {
            return $request->validate($rules, $this->getValidationErrorKeys($keyMappings));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Transform error messages to keys for frontend
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[$field] = $messages[0]; // Take first error message (which is our key)
            }

            return back()->withErrors($errors)->withInput();
        }
    }
}