<?php

return [
    'passwordReset' => [
        'subject' => 'Reset Password - ' . config('app.name', 'Language Center'),
        'header' => 'Language Center Management System',
        'greeting' => 'Hello :name,',
        'message1' => 'You are receiving this email because we received a password reset request for your account.',
        'message2' => 'Please click the button below to reset your password:',
        'button' => 'Reset Password',
        'alternativeText' => 'If you\'re having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:',
        'warningTitle' => '⚠️ Security Notice:',
        'warningText' => 'If you did not request a password reset, please ignore this email. Do not share this link with anyone.',
        'expiryTitle' => '⏰ Expiration:',
        'expiryText' => 'This password reset link will expire in 60 minutes from the time it was sent.',
        'footer1' => 'If you have any questions, please contact our support team.',
        'footer2' => 'Thank you for using our service!',
    ],

    'passwordResetSuccess' => [
        'subject' => 'Password Reset Successful - ' . config('app.name', 'Language Center'),
        'greeting' => 'Hello :name,',
        'message1' => 'Your password has been successfully reset at ' . now()->format('m/d/Y H:i:s') . '.',
        'message2' => 'You can now log in with your new password.',
        'loginButton' => 'Login Now',
        'securityNote' => 'If you did not make this change, please contact our support team immediately.',
        'signature' => 'Best regards,<br>' . config('app.name', 'Language Center') . ' Team',
    ],
];