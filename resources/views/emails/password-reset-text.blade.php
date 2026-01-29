{{ __('email.passwordReset.greeting', ['name' => $user->name], $locale) }}

{{ __('email.passwordReset.message1', [], $locale) }}

{{ __('email.passwordReset.message2', [], $locale) }}

{{ __('email.passwordReset.button', [], $locale) }}: {{ $resetUrl }}

{{ __('email.passwordReset.warningTitle', [], $locale) }}
{{ __('email.passwordReset.warningText', [], $locale) }}

{{ __('email.passwordReset.expiryTitle', [], $locale) }}
{{ __('email.passwordReset.expiryText', [], $locale) }}

--
{{ __('email.passwordReset.footer1', [], $locale) }}
{{ __('email.passwordReset.footer2', [], $locale) }}

{{ config('app.name', 'Language Center') }}