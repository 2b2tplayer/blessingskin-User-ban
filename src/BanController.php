<?php

namespace UserBan;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BanController extends Controller
{
    /**
     * 显示封禁管理页面
     */
    public function page(Request $request)
    {
        // 获取搜索关键词
        $keyword = $request->input('keyword', '');
        
        // 获取分页数量（默认20，可选10/20/50/100）
        $perPage = $request->input('per_page', 20);
        if (!in_array($perPage, [10, 20, 50, 100])) {
            $perPage = 20;
        }

        // 查询普通用户（排除管理员）
        $query = User::where('permission', '=', 'user');
        
        // 应用搜索
        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('uid', $keyword)
                    ->orWhere('nickname', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }
        
        // 分页查询
        $users = $query->orderBy('uid')
            ->paginate($perPage)
            ->through(function ($user) {
                $ban = BanRecord::getActiveBan($user->uid);
                return [
                    'uid' => $user->uid,
                    'email' => $user->email,
                    'nickname' => $user->nickname,
                    'banned' => $ban !== null,
                    'ban_info' => $ban,
                ];
            });

        // 获取所有被封禁的用户
        $bannedUsers = BanRecord::with('user')
            ->where(function ($query) {
                $query->where('is_permanent', true)
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('banned_at', 'desc')
            ->get();

        // 获取封禁样式配置
        $banStyle = option('user_ban_style', 'modal');

        return view('UserBan::manage', compact('users', 'bannedUsers', 'banStyle', 'keyword', 'perPage'));
    }

    /**
     * 保存配置
     */
    public function saveSettings(Request $request)
    {
        $banStyle = $request->input('ban_style', 'modal');
        
        // 验证配置值
        if (!in_array($banStyle, ['modal', 'alert'])) {
            return json(trans('UserBan::general.invalid_style'), 1);
        }
        
        option(['user_ban_style' => $banStyle]);
        
        return json(trans('UserBan::general.settings_saved'), 0);
    }

    /**
     * 获取封禁列表（AJAX）
     */
    public function list()
    {
        $bans = BanRecord::with('user')
            ->where(function ($query) {
                $query->where('is_permanent', true)
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('banned_at', 'desc')
            ->get()
            ->map(function ($ban) {
                return [
                    'id' => $ban->id,
                    'user_id' => $ban->user_id,
                    'user_email' => $ban->user ? $ban->user->email : 'N/A',
                    'user_nickname' => $ban->user ? $ban->user->nickname : 'N/A',
                    'reason' => $ban->reason,
                    'banned_at' => $ban->banned_at->toDateTimeString(),
                    'expires_at' => $ban->expires_at ? $ban->expires_at->toDateTimeString() : null,
                    'is_permanent' => $ban->is_permanent,
                ];
            });

        return json($bans);
    }

    /**
     * 搜索用户
     */
    public function search(Request $request)
    {
        $keyword = $request->input('keyword', '');
        
        if (empty($keyword)) {
            return json([]);
        }

        $users = User::where(function ($query) use ($keyword) {
                $query->where('email', 'like', "%{$keyword}%")
                    ->orWhere('nickname', 'like', "%{$keyword}%")
                    ->orWhere('uid', $keyword);
            })
            ->where(function ($query) {
                $query->where('permission', '!=', 'admin')
                    ->where('permission', '!=', 'super-admin');
            })
            ->limit(10)
            ->get()
            ->map(function ($user) {
                $ban = BanRecord::getActiveBan($user->uid);
                return [
                    'uid' => $user->uid,
                    'email' => $user->email,
                    'nickname' => $user->nickname,
                    'banned' => $ban !== null,
                    'ban_info' => $ban ? [
                        'reason' => $ban->reason,
                        'expires_at' => $ban->expires_at ? $ban->expires_at->toDateTimeString() : null,
                        'is_permanent' => $ban->is_permanent,
                    ] : null,
                ];
            });

        return json($users);
    }

    /**
     * 封禁用户
     */
    public function ban(Request $request)
    {
        $userId = $request->input('user_id');
        $reason = $request->input('reason');
        $duration = $request->input('duration', 7);
        $isPermanent = $request->input('is_permanent');

        // 验证
        if (!$userId || !is_numeric($userId)) {
            return json(trans('UserBan::general.invalid_user'), 1);
        }

        // 如果未填写原因，使用默认原因文本
        if (!$reason || trim($reason) === '') {
            $reason = trans('UserBan::general.default_reason', [], null);
            if (!$reason || trim($reason) === '') {
                $reason = '违反规定';
            }
        }

        // 检查用户是否存在
        $user = User::where('uid', $userId)->first();
        if (!$user) {
            return json(trans('UserBan::general.user_not_found'), 1);
        }

        // 不能封禁管理员
        if ($user->permission === 'admin' || $user->permission === 'super-admin') {
            return json(trans('UserBan::general.cannot_ban_admin'), 1);
        }

        // 检查是否已经封禁
        if (BanRecord::isBanned($userId)) {
            return json(trans('UserBan::general.already_banned'), 1);
        }

        // 处理永久封禁参数
        $isPermanentBool = $isPermanent === true || $isPermanent === 'true' || $isPermanent === 1 || $isPermanent === '1';

        $bannedAt = Carbon::now();
        $expiresAt = $isPermanentBool ? null : Carbon::now()->addDays((int)$duration);

        BanRecord::create([
            'user_id' => (int)$userId,
            'reason' => trim($reason),
            'banned_at' => $bannedAt,
            'expires_at' => $expiresAt,
            'is_permanent' => $isPermanentBool,
            'banned_by' => auth()->id(),
        ]);

        return json(trans('UserBan::general.ban_success'), 0);
    }

    /**
     * 解封用户
     */
    public function unban(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,uid',
        ]);

        $userId = $data['user_id'];

        // 删除所有封禁记录（包括过期的）
        BanRecord::where('user_id', $userId)->delete();

        return json(trans('UserBan::general.unban_success'), 0);
    }

    /**
     * 检查当前用户的封禁状态
     */
    public function checkStatus()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return json(['banned' => false], 0);
            }
            
            $ban = BanRecord::getActiveBan($user->uid);
            
            if ($ban) {
                // 获取封禁操作人信息
                $bannedByUser = $ban->bannedBy;
                $bannedByName = $bannedByUser ? $bannedByUser->nickname : 'System';
                
                return json([
                    'banned' => true,
                    'reason' => $ban->reason,
                    'banned_at' => $ban->banned_at->toDateTimeString(),
                    'expires_at' => $ban->expires_at ? $ban->expires_at->toDateTimeString() : null,
                    'is_permanent' => $ban->is_permanent,
                    'banned_by' => $bannedByName,
                ], 0);
            }
            
            return json(['banned' => false], 0);
        } catch (\Exception $e) {
            return json(['banned' => false, 'error' => $e->getMessage()], 0);
        }
    }
}