/**
 * 封禁状态检查脚本
 * 定期检查当前用户的封禁状态，如果被封禁则强制跳转到登录页面
 */

(function() {
    var blessing = globalThis.blessing || {};
    var baseUrl = blessing.base_url || window.location.origin;
    
    // 检查封禁状态
    function checkBanStatus() {
        // 使用 jQuery AJAX（如果可用）
        if (typeof $ !== 'undefined' && typeof $.ajax !== 'undefined') {
            $.ajax({
                url: baseUrl + '/api/user-ban/check',
                type: 'GET',
                success: function(response) {
                    handleResponse(response);
                },
                error: function(err) {
                    console.error('Failed to check ban status:', err);
                }
            });
        } else if (blessing.fetch && blessing.fetch.get) {
            blessing.fetch.get(baseUrl + '/api/user-ban/check').then(handleResponse);
        } else {
            fetch(baseUrl + '/api/user-ban/check', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(res) {
                return res.json();
            }).then(handleResponse).catch(function(err) {
                console.error('Failed to check ban status:', err);
            });
        }
    }
    
    // 处理响应
    function handleResponse(response) {
        // 如果被封禁，显示弹窗并跳转
        if (response && response.data && response.data.banned) {
            showBanModal(response.data);
        }
    }
    
    // 显示封禁弹窗
    function showBanModal(banInfo) {
        // 检查是否已经有模态框
        if (document.getElementById('banCheckModal')) {
            return;
        }
        
        var message = '您的账号已被封禁，无法登录。';
        var expiresText = banInfo.is_permanent ? '永久封禁' : banInfo.expires_at;
        
        var modalHtml = '<div class="modal fade" id="banCheckModal" tabindex="-1">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title"><i class="fas fa-ban text-danger mr-2"></i>账号已被封禁</h5>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="alert alert-danger mb-3">' + message + '</div>' +
            '<div class="text-muted">' +
            '<p><strong>封禁时间：</strong>' + banInfo.banned_at + '</p>' +
            '<p><strong>封禁原因：</strong>' + banInfo.reason + '</p>' +
            '<p><strong>封禁操作人：</strong>' + banInfo.banned_by + '</p>' +
            (banInfo.is_permanent ? '<p><strong>封禁类型：</strong>永久封禁</p>' : '<p><strong>解封时间：</strong>' + banInfo.expires_at + '</p>') +
            '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-primary" id="banCheckConfirm">我已知晓</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // 显示模态框
        setTimeout(function() {
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#banCheckModal').modal('show');
                
                $('#banCheckConfirm').on('click', function() {
                    window.location.href = baseUrl + '/auth/login';
                });
            } else {
                document.getElementById('banCheckModal').classList.add('show');
                document.getElementById('banCheckModal').style.display = 'block';
                
                document.getElementById('banCheckConfirm').addEventListener('click', function() {
                    window.location.href = baseUrl + '/auth/login';
                });
            }
        }, 100);
    }
    
    // 页面加载后立即检查一次
    if (document.readyState === 'complete') {
        checkBanStatus();
    } else {
        window.addEventListener('load', checkBanStatus);
    }
    
    // 每30秒检查一次封禁状态
    setInterval(checkBanStatus, 30000);
})();