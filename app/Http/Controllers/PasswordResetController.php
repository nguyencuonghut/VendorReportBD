<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Notifications\PasswordResetSuccessNotification;
use App\Traits\HasValidationKeys;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    use HasValidationKeys;
    /**
     * Show forgot password form
     */
    public function showForgotPasswordForm(Request $request)
    {
        // Set locale from session
        $locale = $request->session()->get('locale', config('app.locale', 'vi'));
        app()->setLocale($locale);

        return inertia('Auth/ForgotPassword');
    }

    /**
     * Handle forgot password request
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        // Check if email exists manually and return custom error key
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors([
                'email' => 'validation.emailNotExists'
            ]);
        }

        $email = $request->email;

        // Check if there's a recent reset request (within 5 minutes)
        $recentRequest = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('created_at', '>', Carbon::now()->subMinutes(5))
            ->first();

        if ($recentRequest) {
            return back()->withErrors([
                'email' => 'validation.resetTokenCooldown'
            ]);
        }

        // Delete old tokens for this email
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Generate new token
        $token = Str::random(64);

        // Store token in database
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now()
        ]);

        // Generate reset URL
        $resetUrl = url("/reset-password?token={$token}&email=" . urlencode($email));

        try {
            // Send notification using Notification (use default locale from config)
            $user->notify(new PasswordResetNotification($resetUrl, config('app.locale', 'vi')));

            Log::info("Password reset notification sent successfully to: {$email}");

        } catch (\Exception $e) {
            Log::error("Failed to send password reset notification to {$email}: " . $e->getMessage());

            // Delete the token if notification sending failed
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return back()->withErrors([
                'email' => 'validation.emailSendFailed'
            ]);
        }

        return back()->with([
            'message' => 'auth.passwordResetSent',
            'type' => 'success'
        ]);
    }

    /**
     * Show reset password form
     */
    public function showResetPasswordForm(Request $request)
    {
        $token = $request->get('token');
        $email = $request->get('email');

        if (!$token || !$email) {
            return redirect('/login');
        }

        return inertia('Auth/ResetPassword', [
            'token' => $token,
            'email' => $email
        ]);
    }

    /**
     * Handle password reset
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        // Find the password reset token
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset) {
            return back()->withErrors([
                'email' => 'validation.invalidResetToken'
            ]);
        }

        // Check if token is valid (not expired - 60 minutes)
        if (Carbon::parse($passwordReset->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors([
                'token' => 'validation.expiredResetToken'
            ]);
        }

        // Verify token
        if (!Hash::check($request->token, $passwordReset->token)) {
            return back()->withErrors([
                'token' => 'validation.invalidResetToken'
            ]);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Send success notification (use default locale from config)
        try {
            $user->notify(new PasswordResetSuccessNotification(config('app.locale', 'vi')));
            Log::info("Password reset success notification sent to: {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send password reset success notification to {$user->email}: " . $e->getMessage());
            // Continue anyway since the password was reset successfully
        }

        return redirect('/login');
    }
}
