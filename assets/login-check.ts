/**
 * User Ban Plugin - Login Check Script
 * Shows ban notification when user tries to login
 */

const blessing = globalThis.blessing || {}

// Check for ban info in session and show modal
function checkBanInfo(): void {
  // Check if there's ban info in the page (passed from backend)
  const banInfoElement = document.getElementById('ban-info')
  if (!banInfoElement) return

  const banInfo = JSON.parse(banInfoElement.textContent || '{}')
  if (!banInfo.banned) return

  // Build ban message
  let message = '您的账号已被封禁\n\n'
  message += '封禁原因: ' + banInfo.reason + '\n'
  message += '封禁时间: ' + banInfo.banned_at + '\n'
  
  if (banInfo.is_permanent) {
    message += '封禁类型: 永久封禁'
  } else {
    message += '封禁类型: 临时封禁\n'
    message += '解封时间: ' + banInfo.expires_at
  }

  // Show alert
  if (blessing.notify && blessing.notify.toast) {
    blessing.notify.toast.error(message)
  } else {
    alert(message)
  }
}

// Run when page is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', checkBanInfo)
} else {
  checkBanInfo()
}