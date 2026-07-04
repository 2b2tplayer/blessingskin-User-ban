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

    // 注册路由（使用完全限定类名以兼容不同 Laravel 版本）
    Hook::addRoute(function () {
        // 管理页面路由（仅管理员可访问）
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

        // 检查封禁状态的 API（需要认证）
        Route::middleware(['web', 'auth'])
            ->prefix('api/user-ban')
            ->group(function () {
                Route::get('check', '\\UserBan\\BanController@checkStatus');
            });
    });

    // 监听页面渲染事件，向登录页面添加封禁提示
    $events->listen(App\Events\RenderingFooter::class, function ($event) {
        // 登录页面显示封禁信息
        if (request()->is('auth/login') && session()->has('user_ban_info')) {
            $banInfo = session('user_ban_info');
            
            // 获取管理员配置的封禁样式
            $banStyle = option('user_ban_style', 'modal'); // modal 或 alert
            
            // 构建封禁消息（简化版）
            $message = trans('UserBan::general.login_banned');
            
            if ($banStyle === 'modal') {
                // 使用 Bootstrap 模态框
                $modalId = 'banModal';
                $script = '<script>';
                $script .= '(function() {';
                $script .= '  var modalHtml = \'';
                $script .= '    <div class="modal fade" id="' . $modalId . '" tabindex="-1">';
                $script .= '      <div class="modal-dialog">';
                $script .= '        <div class="modal-content">';
                $script .= '          <div class="modal-header">';
                $script .= '            <h5 class="modal-title"><i class="fas fa-ban text-danger mr-2"></i>账号已被封禁</h5>';
                $script .= '            <button type="button" class="close" data-dismiss="modal">&times;</button>';
                $script .= '          </div>';
                $script .= '          <div class="modal-body">';
                $script .= '            <div class="alert alert-danger mb-3">' . addslashes($message) . '</div>';
                $script .= '            <div class="text-muted">';
                $script .= '              <p><strong>封禁时间：</strong>' . addslashes($banInfo['banned_at']) . '</p>';
                $script .= '              <p><strong>封禁原因：</strong>' . addslashes($banInfo['reason']) . '</p>';
                $script .= '              <p><strong>封禁操作人：</strong>' . addslashes($banInfo['banned_by']) . '</p>';
                if ($banInfo['is_permanent']) {
                    $script .= '              <p><strong>封禁类型：</strong>永久封禁</p>';
                } else {
                    $script .= '              <p><strong>解封时间：</strong>' . addslashes($banInfo['expires_at']) . '</p>';
                }
                $script .= '            </div>';
                $script .= '          </div>';
                $script .= '          <div class="modal-footer">';
                $script .= '            <button type="button" class="btn btn-primary" data-dismiss="modal">我已知晓</button>';
                $script .= '          </div>';
                $script .= '        </div>';
                $script .= '      </div>';
                $script .= '    </div>';
                $script .= '  \';';
                $script .= '  document.body.insertAdjacentHTML("beforeend", modalHtml);';
                $script .= '  setTimeout(function() {';
                $script .= '    if (typeof $ !== "undefined" && $.fn.modal) {';
                $script .= '      $("#' . $modalId . '").modal("show");';
                $script .= '    } else {';
                $script .= '      document.getElementById("' . $modalId . '").classList.add("show");';
                $script .= '      document.getElementById("' . $modalId . '").style.display = "block";';
                $script .= '    }';
                $script .= '  }, 300);';
                $script .= '})();';
                $script .= '</script>';
            } else {
                // 使用普通 alert
                $script = '<script>';
                $script .= 'setTimeout(function() {';
                $script .= '  alert("' . addslashes($message) . '");';
                $script .= '}, 500);';
                $script .= '</script>';
            }
            
            $event->addContent($script);
        }
        
        // 对已登录用户添加封禁状态检查脚本（排除登录页面和管理页面）
        if (Auth::check() && !request()->is('auth/login') && !request()->is('admin/*')) {
            $baseUrl = url('/');
            $script = '<script>';
            $script .= '(function() {';
            $script .= '  var baseUrl = "' . $baseUrl . '";';
            $script .= '  function checkBanStatus() {';
            $script .= '    if (typeof $ !== "undefined" && typeof $.ajax !== "undefined") {';
            $script .= '      $.ajax({';
            $script .= '        url: baseUrl + "/api/user-ban/check",';
            $script .= '        type: "GET",';
            $script .= '        success: function(response) {';
            $script .= '          if (response && response.data && response.data.banned) {';
            $script .= '            showBanModal(response.data);';
            $script .= '          }';
            $script .= '        }';
            $script .= '      });';
            $script .= '    }';
            $script .= '  }';
            $script .= '  function showBanModal(banInfo) {';
            $script .= '    if (document.getElementById("banCheckModal")) return;';
            $script .= '    var modalHtml = "<div class=\"modal fade\" id=\"banCheckModal\" tabindex=\"-1\"><div class=\"modal-dialog\"><div class=\"modal-content\"><div class=\"modal-header\"><h5 class=\"modal-title\"><i class=\"fas fa-ban text-danger mr-2\"></i>账号已被封禁</h5></div><div class=\"modal-body\"><div class=\"alert alert-danger mb-3\">您的账号已被封禁，无法登录。</div><div class=\"text-muted\"><p><strong>封禁时间：</strong>" + banInfo.banned_at + "</p><p><strong>封禁原因：</strong>" + banInfo.reason + "</p><p><strong>封禁操作人：</strong>" + banInfo.banned_by + "</p>" + (banInfo.is_permanent ? "<p><strong>封禁类型：</strong>永久封禁</p>" : "<p><strong>解封时间：</strong>" + banInfo.expires_at + "</p>") + "</div></div><div class=\"modal-footer\"><button type=\"button\" class=\"btn btn-primary\" id=\"banCheckConfirm\">我已知晓</button></div></div></div></div>";';
            $script .= '    document.body.insertAdjacentHTML("beforeend", modalHtml);';
            $script .= '    setTimeout(function() {';
            $script .= '      if (typeof $ !== "undefined" && $.fn.modal) {';
            $script .= '        $("#banCheckModal").modal("show");';
            $script .= '        $("#banCheckConfirm").on("click", function() { window.location.href = baseUrl + "/auth/login"; });';
            $script .= '      } else {';
            $script .= '        document.getElementById("banCheckModal").classList.add("show");';
            $script .= '        document.getElementById("banCheckModal").style.display = "block";';
            $script .= '        document.getElementById("banCheckConfirm").addEventListener("click", function() { window.location.href = baseUrl + "/auth/login"; });';
            $script .= '      }';
            $script .= '    }, 100);';
            $script .= '  }';
            $script .= '  if (document.readyState === "complete") { checkBanStatus(); }';
            $script .= '  else { window.addEventListener("load", checkBanStatus); }';
            $script .= '  setInterval(checkBanStatus, 30000);';
            $script .= '})();';
            $script .= '</script>';
            
            $event->addContent($script);
        }
    });

    // 监听标准登录事件
    $events->listen(Login::class, function ($event) {
        $user = $event->user;
        $ban = UserBan\BanRecord::getActiveBan($user->uid);

        if ($ban) {
            // 获取封禁操作人信息
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
            
            auth()->logout();
            session()->invalidate();
            session()->regenerateToken();
            session()->flash('user_ban_info', $banInfo);
            session()->flash('error', $errorMessage);
        }
    });

    // 监听 OAuth 登录准备事件（在登录之前）
    $events->listen('auth.login.ready', function ($user) {
        $ban = UserBan\BanRecord::getActiveBan($user->uid);

        if ($ban) {
            // 获取封禁操作人信息
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
            
            // 存储封禁信息到 session
            session()->flash('user_ban_info', $banInfo);
            session()->flash('error', $errorMessage);
            
            // 使用 abort 并指定重定向到正确的路由
            abort(redirect()->route('auth.login'));
        }
    });

    // 将 CheckBanStatus 中间件尝试注册到 `web` 中间件组，确保已登录用户的每次请求都会被检查
    try {
        if (function_exists('app')) {
            $router = app()->make(\Illuminate\Routing\Router::class);
            if (method_exists($router, 'pushMiddlewareToGroup')) {
                $router->pushMiddlewareToGroup('web', \UserBan\CheckBanStatus::class);
            } else if (method_exists($router, 'aliasMiddleware')) {
                // 在不支持 pushMiddlewareToGroup 的 Laravel 版本中，先注册别名（部分版本需要修改 Kernel 才能把别名加到组）
                $router->aliasMiddleware('userban.check', \UserBan\CheckBanStatus::class);
            }
        }
    } catch (\Throwable $e) {
        // 如果注册失败，不要中断插件加载；客户端的轮询仍作为备用方案
    }
};