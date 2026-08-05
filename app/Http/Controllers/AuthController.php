<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function getLogin()
    {
        if (Auth::check()) {
            return $this->redirectForUser(Auth::user());
        }

        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function postLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $userByEmail = User::where('email', $credentials['email'])->first();
        if ($userByEmail && (int) $userByEmail->deleted === 1) {
            return back()->withErrors([
                'email' => 'Tài khoản này đã bị xóa.'
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if (isset($user->is_active) && !$user->is_active) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Tài khoản của bạn đã bị vô hiệu hóa.'
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return $this->redirectForUser($user);
        }

        return back()->withErrors([
            'email' => 'Thông tin đăng nhập không chính xác.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    private function redirectForUser($user)
    {
        if (($user->role ?? null) === 'manager') {
            return redirect()->intended('manager/dashboard');
        }

        return redirect()->intended('guard/dashboard');
    }
}
