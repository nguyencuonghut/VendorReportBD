<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\LoginRequest;

class LoginController extends Controller
{
    public function showLoginForm(Request $request)
    {
        return inertia('Auth/Login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/')->with([
                'message' => 'auth.loginSuccess',
                'type' => 'success'
            ]);
        }

        return back()->withErrors([
            'email' => 'validation.invalidCredentials',
        ])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with([
            'message' => 'auth.logoutSuccess',
            'type' => 'success'
        ]);
    }
}
