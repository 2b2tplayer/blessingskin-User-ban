/**
 * 封禁状态检查脚本
 * 定期检查当前用户的封禁状态，如果被封禁则强制跳转到登录页面
 */
(function() {
    var baseUrl = (window.blessing && window.blessing.base_url) || window.location.origin;

    function checkBanStatus() {
        fetch(baseUrl + '/api/user-ban/check', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(response) {
            if (response && response.data && response.data.banned) {
                showBanModal(response.data);
            }
        })
        .catch(function() {});
    }

    function showBanModal(banInfo) {
        if (document.getElementById('banCheckModal')) return;

        var expiresText = banInfo.is_permanent ? '<p><strong>封禁类型：</strong>永久封禁</p>' : '<p><strong>解封时间：</strong>' + banInfo.expires_at + '</p>';

        var html = '<div class="modal fade" id="banCheckModal" tabindex="-1">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title"><i class="fas fa-ban text-danger mr-2"></i>账号已被封禁</h5>' +
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
            '<button type="button" class="btn btn-primary" id="banCheckConfirm">我已知晓</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', html);

        setTimeout(function() {
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#banCheckModal').modal('show');
                $('#banCheckConfirm').on('click', function() {
                    window.location.href = baseUrl + '/auth/login';
                });
            } else {
                var el = document.getElementById('banCheckModal');
                if (el) {
                    el.classList.add('show');
                    el.style.display = 'block';
                }
                var btn = document.getElementById('banCheckConfirm');
                if (btn) {
                    btn.addEventListener('click', function() {
                        window.location.href = baseUrl + '/auth/login';
                    });
                }
            }
        }, 100);
    }

    if (document.readyState === 'complete') {
        checkBanStatus();
    } else {
        window.addEventListener('load', checkBanStatus);
    }

    setInterval(checkBanStatus, 30000);
})();