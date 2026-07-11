/**
 * 用户封禁模态框脚本
 * 在登录页面拦截登录请求，显示封禁信息
 */
(function() {
    var baseUrl = (window.blessing && window.blessing.base_url) || window.location.origin;

    function showBanModal(banInfo) {
        if (document.getElementById('banModal')) return;

        var expiresText = banInfo.is_permanent ? '<p><strong>封禁类型：</strong>永久封禁</p>' : '<p><strong>解封时间：</strong>' + banInfo.expires_at + '</p>';

        var html = '<div class="modal fade" id="banModal" tabindex="-1">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title"><i class="fas fa-ban text-danger mr-2"></i>账号已被封禁</h5>' +
            '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="alert alert-danger mb-3">您的账号已被封禁，无法登录。</div>' +
            '<div class="text-muted">' +
            '<p><strong>封禁时间：</strong>' + banInfo.banned_at + '</p>' +
            '<p><strong>封禁原因：</strong>' + banInfo.reason + '</p>' +
            '<p><strong>封禁操作人：</strong>' + banInfo.banned_by + '</p>' +
            expiresText +
            '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-primary" data-dismiss="modal">我已知晓</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', html);

        setTimeout(function() {
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#banModal').modal('show');
            } else {
                var el = document.getElementById('banModal');
                if (el) {
                    el.classList.add('show');
                    el.style.display = 'block';
                }
            }
        }, 100);
    }

    // 拦截 fetch 请求 - 从响应中直接提取 ban_info
    if (typeof window.fetch === 'function') {
        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            return originalFetch.apply(this, arguments).then(function(response) {
                var urlStr = typeof url === 'string' ? url : (url.url || '');
                if (urlStr.indexOf('/auth/login') !== -1 && options && options.method && options.method.toUpperCase() === 'POST') {
                    return response.clone().json().then(function(result) {
                        // 检查响应中是否包含 ban_info
                        if (result && result.data && result.data.ban_info) {
                            showBanModal(result.data.ban_info);
                        }
                        return response;
                    }).catch(function() { return response; });
                }
                return response;
            });
        };
    }

    // 拦截 XMLHttpRequest - 从响应中直接提取 ban_info
    if (typeof XMLHttpRequest !== 'undefined') {
        var origOpen = XMLHttpRequest.prototype.open;
        var origSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function(method, url) {
            this._ub_url = url;
            this._ub_method = method;
            return origOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function(body) {
            var xhr = this;
            xhr.addEventListener('load', function() {
                try {
                    if (!xhr._ub_url || xhr._ub_url.indexOf('/auth/login') === -1) return;
                    if (xhr._ub_method && xhr._ub_method.toUpperCase() !== 'POST') return;
                    var result = JSON.parse(xhr.responseText);
                    if (result && result.data && result.data.ban_info) {
                        showBanModal(result.data.ban_info);
                    }
                } catch(e) {}
            });
            return origSend.apply(this, arguments);
        };
    }

    // 页面加载时检查 session 中的封禁信息（用于 OAuth 重定向后）
    if (window.__banInfo && window.__banInfo.banned) {
        setTimeout(function() { showBanModal(window.__banInfo); }, 300);
    }
})();