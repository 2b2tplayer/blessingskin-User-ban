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
                
                // 清除 session
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // 存储封禁信息
                $request->session()->flash('user_ban_info', [
                    'banned' => true,
                    'reason' => $ban->reason,
                    'banned_at' => $ban->banned_at->toDateTimeString(),
                    'expires_at' => $ban->expires_at ? $ban->expires_at->toDateTimeString() : null,
                    'is_permanent' => $ban->is_permanent,
                    'banned_by' => $bannedByName,
                ]);
                
                // 存储错误消息（简化版）
                $request->session()->flash('error', trans('UserBan::general.login_banned'));

                // 重定向到登录页面
                return redirect()->route('auth.login');
            }
        }

        return $next($request);
    }
}