<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Display the user's profile page.
     */
    public function index()
    {
        $user = auth()->user()->load(['department', 'roles']);

        return Inertia::render('Profile/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department ? [
                    'id' => $user->department->id,
                    'name' => $user->department->name,
                    'code' => $user->department->code,
                ] : null,
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $this->getRoleDisplayName($role->name),
                ]),
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->format('d/m/Y H:i'),
                'created_at' => $user->created_at->format('d/m/Y'),
            ],
            'statistics' => [
                'my_created_reports' => $user->createdReports()->count(),
                'my_pending_approvals' => $user->assignedApprovalSteps()->where('status', 'PENDING')->count(),
                'my_approved_reports' => $user->assignedApprovalSteps()->where('status', 'APPROVED')->count(),
                'my_rejected_reports' => $user->assignedApprovalSteps()->where('status', 'REJECTED')->count(),
            ],
        ]);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Đổi mật khẩu thành công!');
    }

    /**
     * Get role display name in Vietnamese
     */
    private function getRoleDisplayName(string $roleName): string
    {
        return match($roleName) {
            'admin_system' => 'Quản trị hệ thống',
            'purchasing_admin' => 'Quản lý Thu Mua',
            'requester' => 'Người yêu cầu',
            'dept_head' => 'Trưởng phòng',
            'internal_control' => 'Kiểm soát nội bộ',
            'national_purchasing' => 'Khối Mua Hàng Toàn Quốc',
            'tech_board' => 'Ban Kỹ thuật',
            'bod' => 'Ban Giám Đốc',
            default => ucfirst(str_replace('_', ' ', $roleName)),
        };
    }
}
