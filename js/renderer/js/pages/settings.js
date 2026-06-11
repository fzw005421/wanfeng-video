/**
 * 晚风影视 - 软件设置页
 * 企业版：卡片式布局
 */

const SettingsPage = {

  render(container, _data) {
    const userInfo = AuthManager.getUserInfo();
    const nick = userInfo.nickname || userInfo.username || '';

    container.innerHTML = `
      <div class="page active" id="settings-page">
        <div class="page-topbar">
          <div class="page-title">软件设置</div>
          <div class="spacer"></div>
        </div>
        <div class="page-scroll" id="settings-scroll">
          <!-- 账户信息 -->
          <div class="settings-card">
            <h3>账户信息</h3>
            <div class="settings-avatar-row">
              <div class="settings-avatar">${nick.charAt(0).toUpperCase()}</div>
              <div>
                <div style="font-weight:600;font-size:16px;color:var(--text-primary);">${nick}</div>
                <div style="font-size:13px;color:var(--text-hint);">用户名: ${userInfo.username || ''}</div>
              </div>
            </div>
          </div>

          <!-- 修改密码 -->
          <div class="settings-card">
            <h3>修改密码</h3>
            <input class="form-input mb-16" id="settings-old-pwd" type="password" placeholder="旧密码">
            <input class="form-input mb-16" id="settings-new-pwd" type="password" placeholder="新密码 (至少6位)">
            <input class="form-input mb-16" id="settings-confirm-pwd" type="password" placeholder="确认新密码">
            <button class="btn btn-sm" id="settings-change-pwd">修改密码</button>
          </div>

          <!-- 关于 -->
          <div class="settings-card">
            <h3>关于</h3>
            <div class="settings-info-row">晚风影视 Windows 客户端</div>
            <div class="settings-info-row"><span class="label">版本:</span> 1.0.0</div>
            <div class="settings-info-row"><span class="label">技术:</span> Electron + hls.js</div>
          </div>
        </div>
      </div>
    `;

    this._bindEvents();
  },

  _bindEvents() {
    document.getElementById('settings-change-pwd').addEventListener('click', async () => {
      const oldPwd = document.getElementById('settings-old-pwd').value;
      const newPwd = document.getElementById('settings-new-pwd').value;
      const confirmPwd = document.getElementById('settings-confirm-pwd').value;

      if (!oldPwd || !newPwd) {
        App.toast('请填写所有字段', 'warning');
        return;
      }
      if (newPwd.length < 6) {
        App.toast('新密码至少6位', 'warning');
        return;
      }
      if (newPwd !== confirmPwd) {
        App.toast('两次输入的新密码不一致', 'warning');
        return;
      }

      const result = await ApiClient.changePassword(oldPwd, newPwd);
      if (result.code === 200) {
        App.toast('密码修改成功，下次登录生效', 'success');
        document.getElementById('settings-old-pwd').value = '';
        document.getElementById('settings-new-pwd').value = '';
        document.getElementById('settings-confirm-pwd').value = '';
      } else {
        App.toast(result.msg || '修改失败', 'error');
      }
    });
  },
};
