<!DOCTYPE html>
<html lang="{{ $locale ?? 'vi' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('email.passwordReset.subject', [], $locale ?? 'vi') }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }

        .header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 10px 0 0 0;
            font-size: 16px;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .message {
            margin-bottom: 30px;
            line-height: 1.8;
            color: #555555;
        }

        .button-container {
            text-align: center;
            margin: 40px 0;
        }

        .reset-button {
            display: inline-block;
            padding: 16px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .reset-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .divider {
            border: none;
            border-top: 1px solid #e9ecef;
            margin: 30px 0;
        }

        .alternative-link {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #17a2b8;
        }

        .alternative-link p {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #6c757d;
        }

        .alternative-link code {
            background-color: #e9ecef;
            padding: 8px 12px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
            color: #495057;
            display: block;
            margin-top: 5px;
        }

        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .footer p {
            margin: 0;
            color: #6c757d;
            font-size: 14px;
        }

        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }

        .warning p {
            margin: 0;
            color: #856404;
            font-size: 14px;
        }

        .security-note {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }

        .security-note p {
            margin: 0;
            color: #0c5460;
            font-size: 14px;
        }

        @media only screen and (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }

            .header, .content, .footer {
                padding: 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .reset-button {
                padding: 14px 28px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ config('app.name', 'Language Center') }}</h1>
            <p>{{ __('email.passwordReset.header', [], $locale ?? 'vi') }}</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                {{ __('email.passwordReset.greeting', ['name' => $user->name], $locale ?? 'vi') }}
            </div>

            <div class="message">
                <p>{{ __('email.passwordReset.message1', [], $locale ?? 'vi') }}</p>
                <p>{{ __('email.passwordReset.message2', [], $locale ?? 'vi') }}</p>
            </div>

            <!-- Reset Button -->
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button">
                    {{ __('email.passwordReset.button', [], $locale ?? 'vi') }}
                </a>
            </div>

            <hr class="divider">

            <!-- Alternative Link -->
            <div class="alternative-link">
                <p>{{ __('email.passwordReset.alternativeText', [], $locale ?? 'vi') }}</p>
                <code>{{ $resetUrl }}</code>
            </div>

            <!-- Security Warning -->
            <div class="warning">
                <p>
                    <strong>{{ __('email.passwordReset.warningTitle', [], $locale ?? 'vi') }}</strong><br>
                    {{ __('email.passwordReset.warningText', [], $locale ?? 'vi') }}
                </p>
            </div>

            <!-- Expiry Notice -->
            <div class="security-note">
                <p>
                    <strong>{{ __('email.passwordReset.expiryTitle', [], $locale ?? 'vi') }}</strong><br>
                    {{ __('email.passwordReset.expiryText', [], $locale ?? 'vi') }}
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>{{ __('email.passwordReset.footer1', [], $locale ?? 'vi') }}</p>
            <p>{{ __('email.passwordReset.footer2', [], $locale ?? 'vi') }}</p>
        </div>
    </div>
</body>
</html>