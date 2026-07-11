<?php

use App\Models\User;
use App\Services\Hook;
use App\Services\Plugin;
use Blessing\Filter;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;

return function (Dispatcher $events, Filter $filter, Plugin $plugin) {
    // 添加管理菜单项
    Hook::addMenuItem('admin', 100, [
        'title' => 'UserBan::general.title',
        'link' => 'admin/user-ban',
        'icon' => 'fa-ban',
    ]);

    // 注册路由
    Hook::addRoute(function () {
        Route::middleware(['web', 'auth', 'role:admin'])
            ->prefix('admin/user-ban')
            ->group(function () {
                Route::get('', '\\UserBan\\BanController@page');
                Route::post('settings', '\\UserBan\\BanController@saveSettings');
                Route::post('ban', '\\UserBan\\BanController@ban');
                Route::post('unban', '\\UserBan\\BanController@unban');
                Route::get('list', '\\UserBan\\BanController@list');
                Route::get('search', '\\UserBan\\BanController@search');
            });

        Route::middleware(['web'])
            ->prefix('api/user-ban')
            ->group(function () {
                Route::get('check', '\\UserBan\\BanController@checkStatus')
                    ->middleware('auth');
                Route::get('check-by-identification', '\\UserBan\\BanController@checkByIdentification');
            });
    });

    // 监听页面渲染事件，向登录页面添加封禁提示
    $events->listen(App\Events\RenderingFooter::class, function ($event) use ($plugin) {
        // 登录页面
        if (request()->is('auth/login')) {
            // 读取 session 中的封禁信息（OAuth 登录后存入）
            $banInfo = session('user_ban_info');
            $showSessionBan = $banInfo !== null;

            if ($showSessionBan) {
                session()->forget('user_ban_info');
                session()->forget('user_ban_error');
            }

            // 嵌入 ban-modal.js 内容（内联避免 404）
            $jsPath = __DIR__ . '/assets/ban-modal.js';
            $banModalJs = file_exists($jsPath) ? file_get_contents($jsPath) : '';

            $script = '<script>';
            if ($showSessionBan && $banInfo) {
                $script .= 'window.__banInfo = ' . json_encode($banInfo) . ';';
            }
            $script .= $banModalJs;
            $script .= '</script>';

            $event->addContent($script);
        }

        // 已登录用户的封禁状态检查
        if (Auth::check() && !request()->is('auth/login') && !request()->is('admin/*')) {
            $jsPath = __DIR__ . '/assets/ban-check.js';
            $banCheckJs = file_exists($jsPath) ? file_get_contents($jsPath) : '';

            $event->addContent('<script>' . $banCheckJs . '</script>');
        }
    });

    // 监听 Login 事件（账号密码登录成功时会触发）
    // 检查用户封禁状态，返回符合 Blessing Skin 前端的 JSON 格式
    $events->listen(Login::class, function ($event) {
        $user = $event->user;
        $ban = UserBan\BanRecord::getActiveBan($user->uid);

        if ($ban) {
            $bannedByUser = $ban->bannedBy;
            $bannedByName = $bannedByUser ? $bannedByUser->nickname : 'System';

            $banInfo = [
                'banned' => true,
                'reason' => $ban->reason,
                'banned_at' => $ban->banned_at->toDateTimeString(),
                'expires_at' => $ban->expires_at ? $ban->expires_at->toDateTimeString() : null,
                'is_permanent' => $ban->is_permanent,
                'banned_by' => $bannedByName,
            ];

            $errorMessage = trans('UserBan::general.login_banned');

            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            // 对 AJAX 请求返回 JSON（带 login_fails 防止前端崩溃）
            // 对非 AJAX 请求重定向到登录页面
            if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'code' => 1,
                        'message' => $errorMessage,
                        'data' => [
                            'login_fails' => [$errorMessage],
                            'ban_info' => $banInfo,
                        ],
                    ])
                );
            }

            // 非 AJAX：设 session 并重定向
            session()->put('user_ban_info', $banInfo);
            session()->put('user_ban_error', $errorMessage);
            session()->save();

            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                redirect()->route('auth.login')
            );
        }
    });

    // 监听 OAuth 登录准备事件（只在非 AJAX 请求时处理，避免干扰标准登录）
    $events->listen('auth.login.ready', function ($user) {
        // 如果是 AJAX 请求（标准登录流程），让 Login 事件处理
        // OAuth 回调是浏览器重定向，非 AJAX
        if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
            return;
        }

        $ban = UserBan\BanRecord::getActiveBan($user->uid);

        if ($ban) {
            $bannedByUser = $ban->bannedBy;
            $bannedByName = $bannedByUser ? $bannedByUser->nickname : 'System';

            $banInfo = [
                'banned' => true,
                'reason' => $ban->reason,
                'banned_at' => $ban->banned_at->toDateTimeString(),
                'expires_at' => $ban->expires_at ? $ban->expires_at->toDateTimeString() : null,
                'is_permanent' => $ban->is_permanent,
                'banned_by' => $bannedByName,
            ];

            $errorMessage = trans('UserBan::general.login_banned');

            session()->put('user_ban_info', $banInfo);
            session()->put('user_ban_error', $errorMessage);
            session()->save();

            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                redirect()->route('auth.login')
            );
        }
    });

    // 注册中间件检查已登录用户的封禁状态
    try {
        if (function_exists('app')) {
            $router = app()->make(\Illuminate\Routing\Router::class);
            if (method_exists($router, 'pushMiddlewareToGroup')) {
                $router->pushMiddlewareToGroup('web', \UserBan\CheckBanStatus::class);
            } else if (method_exists($router, 'aliasMiddleware')) {
                $router->aliasMiddleware('userban.check', \UserBan\CheckBanStatus::class);
            }
        }
    } catch (\Throwable $e) {
        // 注册失败不中断插件加载
    }
};