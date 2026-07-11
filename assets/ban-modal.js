/**
 * 用户封禁模态框脚本
 * 在登录页面拦截登录请求，显示封禁信息
 */
(function() {
    var baseUrl = (window.blessing && window.blessing.base_url) || window.location.origin;
    var modalShown = false;

    function showBanModal(banInfo) {
        if (modalShown || document.getElementById('banModal')) return;
        modalShown = true;

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

    // 方案1: 拦截 Response.prototype.json()
    // ky 库会 clone response 再调用 .json()，clone() 不保留 url 属性
    // 所以不检查 URL，直接检查返回数据中是否包含 ban_info
    if (typeof Response !== 'undefined' && Response.prototype && Response.prototype.json) {
        var origJson = Response.prototype.json;
        Response.prototype.json = function() {
            return origJson.call(this).then(function(result) {
                try {
                    if (result && result.data && result.data.ban_info) {
                        showBanModal(result.data.ban_info);
                    }
                } catch(e) {}
                return result;
            });
        };
    }

    // 方案2: 拦截 fetch（备用）
    if (typeof window.fetch === 'function') {
        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            return originalFetch.apply(this, arguments).then(function(response) {
                try {
                    var urlStr = typeof url === 'string' ? url : (url && url.url ? url.url : '');
                    if (urlStr.indexOf('/auth/login') !== -1) {
                        return response.clone().json().then(function(result) {
                            if (result && result.data && result.data.ban_info) {
                                showBanModal(result.data.ban_info);
                            }
                            return response;
                        }).catch(function() { return response; });
                    }
                } catch(e) {}
                return response;
            });
        };
    }

    // 方案3: 拦截 XMLHttpRequest（备用）
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
                    var result = JSON.parse(xhr.responseText);
                    if (result && result.data && result.data.ban_info) {
                        showBanModal(result.data.ban_info);
                    }
                } catch(e) {}
            });
            return origSend.apply(this, arguments);
        };
    }

    // 方案4: MutationObserver 监听 DOM 中的错误消息（兜底）
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var addedNodes = mutations[i].addedNodes;
                for (var j = 0; j < addedNodes.length; j++) {
                    var node = addedNodes[j];
                    if (node.nodeType === 1) {
                        // 检查节点本身和所有子节点
                        var alerts = node.classList && node.classList.contains('alert') ? [node] : node.querySelectorAll ? node.querySelectorAll('.alert') : [];
                        for (var k = 0; k < alerts.length; k++) {
                            if (alerts[k].textContent.indexOf('封禁') !== -1) {
                                fetchBanInfo();
                                return;
                            }
                        }
                    }
                }
            }
        });

        function fetchBanInfo() {
            var idInput = document.querySelector('.login-box input[type="text"], input[name="identification"]');
            if (idInput && idInput.value) {
                fetch(baseUrl + '/api/user-ban/check-by-identification?identification=' + encodeURIComponent(idInput.value))
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res && res.data && res.data.banned) {
                            showBanModal(res.data);
                        }
                    })
                    .catch(function() {});
            }
        }

        // 延迟启动 observer，确保页面完全加载
        if (document.readyState === 'complete') {
            observer.observe(document.body, { childList: true, subtree: true });
        } else {
            window.addEventListener('load', function() {
                observer.observe(document.body, { childList: true, subtree: true });
            });
        }
    }

    // 页面加载时检查 session 中的封禁信息（用于 OAuth 重定向后）
    if (window.__banInfo && window.__banInfo.banned) {
        setTimeout(function() { showBanModal(window.__banInfo); }, 300);
    }
})();