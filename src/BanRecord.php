<?php

namespace UserBan;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BanRecord extends Model
{
    protected $table = 'user_bans';

    protected $fillable = [
        'user_id',
        'reason',
        'banned_at',
        'expires_at',
        'is_permanent',
        'banned_by',
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
        'banned_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * 关联被封禁的用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uid');
    }

    /**
     * 关联执行封禁的管理员
     */
    public function bannedBy()
    {
        return $this->belongsTo(User::class, 'banned_by', 'uid');
    }

    /**
     * 检查用户是否被封禁
     */
    public static function isBanned($userId): bool
    {
        return self::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('is_permanent', true)
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * 获取用户的有效封禁记录
     */
    public static function getActiveBan($userId): ?BanRecord
    {
        return self::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('is_permanent', true)
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}