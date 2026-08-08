<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use App\Models\VehicleLog;

class ManagerController extends Controller
{
    public function dashboard()
    {
        if (auth()->user()->role !== 'manager') return redirect('/');
        
        $guards = User::where('role', 'guard')
            ->where('deleted', 0)
            ->get();
        $logs = VehicleLog::orderBy('created_at', 'desc')->get();
        return view('manager.dashboard', compact('guards', 'logs'));
    }

    public function storeGuard(Request $request)
    {
        if (auth()->user()->role !== 'manager') return redirect('/');
        
        $request->validate([
            'fullname' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'fullname')->where(function ($q) {
                    $q->where('deleted', 0)->where('role', 'guard');
                }),
            ],
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ], [
            'fullname.unique' => 'Họ và tên này đã được sử dụng.',
            'email.unique' => 'Email này đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        User::create([
            'fullname' => $request->fullname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'guard',
        ]);

        return redirect()->back()->with('success', 'Tạo tài khoản bảo vệ thành công.');
    }

    public function deleteGuard($id)
    {
        if (auth()->user()->role !== 'manager') return redirect('/');
        
        $guard = User::where('deleted', 0)->findOrFail($id);
        if ($guard->role === 'guard') {
            $guard->deleted = 1;
            $guard->save();
        }
        return redirect()->back()->with('success', 'Xóa tài khoản bảo vệ thành công.');
    }

    public function updateGuard(Request $request, $id)
    {
        if (auth()->user()->role !== 'manager') return redirect('/');

        $guard = User::where('deleted', 0)->findOrFail($id);
        if ($guard->role !== 'guard') return redirect()->back()->withErrors(['error' => 'Không hợp lệ']);

        $request->validate([
            'fullname' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'fullname')
                    ->ignore($guard->id)
                    ->where(function ($q) {
                        $q->where('deleted', 0)->where('role', 'guard');
                    }),
            ],
            'email' => 'required|string|email|max:255|unique:users,email,' . $guard->id,
            'password' => 'nullable|string|min:6',
        ], [
            'fullname.unique' => 'Họ và tên này đã được sử dụng.',
            'email.unique' => 'Email này đã được sử dụng.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        $guard->fullname = $request->fullname;
        $guard->email = $request->email;
        if ($request->filled('password')) {
            $guard->password = Hash::make($request->password);
        }
        $guard->save();

        return redirect()->back()->with('success', 'Cập nhật tài khoản bảo vệ thành công.');
    }

    public function toggleStatusGuard($id)
    {
        if (auth()->user()->role !== 'manager') return redirect('/');

        $guard = User::where('deleted', 0)->findOrFail($id);
        if ($guard->role === 'guard') {
            $guard->is_active = !$guard->is_active;
            $guard->save();
            $msg = $guard->is_active ? 'Đã kích hoạt tài khoản.' : 'Đã vô hiệu hóa tài khoản.';
            return redirect()->back()->with('success', $msg);
        }
        return redirect()->back();
    }

    public function vehicleLogs()
    {
        if (auth()->user()->role !== 'manager') return redirect('/');

        $pendingLogs = VehicleLog::with('guardIn')
            ->where(function ($query) {
                $query->where('status', 'in')->orWhere('is_valid', false);
            })
            ->orderBy('entry_time', 'desc')
            ->get();

        $completedLogs = VehicleLog::with(['guardIn', 'guardOut'])
            ->where('status', 'out')
            ->where(function ($query) {
                $query->whereNull('is_valid')->orWhere('is_valid', true);
            })
            ->orderBy('exit_time', 'desc')
            ->get();

        return view('manager.vehicle_logs', compact('pendingLogs', 'completedLogs'));
    }
}
