<?php

namespace UserBan;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckBanStatus
{
    public function handle(Request $request, Closure $next)
    {
        // 只检查已认证的用户
        if (Auth::check()) {
            $user = Auth::user();

            // 检查封禁状态
            $ban = BanRecord::getActiveBan($user->uid);

            if ($ban) {
                // 获取封禁操作人信息
                $bannedByUser = $ban->bannedBy;
                $bannedByName = $bannedByUser ? $bannedByUser->nickname : 'System';
                
                // 强制登出
                Auth::logout();
                
                // 使用 regenerate 而非 invalidate，保留封禁信息
                $request->session()->regenerate();

                // 存储封禁信息到 session（使用 put 确保重定向后可用）
                $request->session()->put('user_ban_info', [
                    'banned' => true,
                    'reason' => $ban->reason,
                    'banned_at' => $ban->banned_at->toDateTimeString(),
                    'expires_at' => $ban->expires_at ? $ban->expires_at->toDateTimeString() : null,
                    'is_permanent' => $ban->is_permanent,
                    'banned_by' => $bannedByName,
                ]);
                
                $request->session()->put('user_ban_error', trans('UserBan::general.login_banned'));
                $request->session()->save();

                // 重定向到登录页面
                return redirect()->route('auth.login');
            }
        }

        return $next($request);
    }
}