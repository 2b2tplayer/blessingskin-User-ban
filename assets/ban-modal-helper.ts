/*
 * Ban modal helper
 * Exposes `window.showBanModal(banInfo)` to render a Bootstrap-like modal
 */

function createModalHtml(banInfo: any) {
  const id = 'userBanModal'
  let html = `
  <div class="modal fade" id="${id}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-ban text-danger mr-2"></i>账号已被封禁</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger mb-3">${escapeHtml(banInfo.reason || '您的账号已被封禁')}</div>
          <div class="text-muted">
            <p><strong>封禁时间：</strong>${escapeHtml(banInfo.banned_at || '')}</p>
            <p><strong>封禁操作人：</strong>${escapeHtml(banInfo.banned_by || '')}</p>
            ${banInfo.is_permanent ? '<p><strong>封禁类型：</strong>永久封禁</p>' : `<p><strong>解封时间：</strong>${escapeHtml(banInfo.expires_at || '')}</p>`}
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="userBanModalOk">我已知晓</button>
        </div>
      </div>
    </div>
  </div>`
  return html
}

function escapeHtml(str: string) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function removeExisting() {
  const existing = document.getElementById('userBanModal')
  if (existing) existing.parentElement?.removeChild(existing)
}

function showModal(banInfo: any) {
  removeExisting()
  const container = document.createElement('div')
  container.innerHTML = createModalHtml(banInfo)
  document.body.appendChild(container.firstElementChild!)

  const modalEl = document.getElementById('userBanModal')
  const okBtn = document.getElementById('userBanModalOk')
  if (typeof (window as any).$ !== 'undefined' && (window as any).$.fn && (window as any).$.fn.modal) {
    (window as any)('#userBanModal').modal('show')
    if (okBtn) okBtn.addEventListener('click', () => (window as any)('#userBanModal').modal('hide'))
  } else {
    // Simple fallback: show block
    if (modalEl) {
      modalEl.classList.add('show')
      ;(modalEl as HTMLElement).style.display = 'block'
    }
    if (okBtn) okBtn.addEventListener('click', () => {
      removeExisting()
    })
  }
}

// expose helper
(window as any).showBanModal = function (banInfo: any) {
  try {
    showModal(banInfo)
  } catch (e) {
    // no-op
    console.error('showBanModal error', e)
  }
}

export {}
