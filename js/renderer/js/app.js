/**
 * 晚风影视 - 应用主入口
 * 负责页面路由、全局状态管理
 * 完全对照 Python 版本的 main.py + main_window.py
 */

const App = {
  // 当前状态
  _currentPage: 'login',
  _userInfo: null,

  // ======================== 主题管理 ========================

  /** 初始化主题（从 localStorage 读取） */
  initTheme() {
    const saved = localStorage.getItem('wanfeng_theme');
    const theme = saved || 'light';
    document.documentElement.setAttribute('data-theme', theme);
  },

  /** 切换明暗主题 */
  toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('wanfeng_theme', next);
  },

  // ======================== 初始化 ========================

  async init() {
    // 初始化主题
    this.initTheme();

    // 绑定窗口控制按钮
    this._bindTitlebar();

    // 恢复用户信息
    const savedUrl = AuthManager.getServerUrl();
    if (savedUrl) ApiClient.setBaseUrl(savedUrl);

    const token = AuthManager.getToken();
    if (token) {
      ApiClient.setToken(token);
      this._userInfo = AuthManager.getUserInfo();
      // 后台验证 token 有效性
      const result = await ApiClient.getProfile();
      if (result.code === 200) {
        this._onLoginSuccess(this._userInfo);
        return;
      }
      // token 过期，清除
      AuthManager.logout();
      ApiClient.setToken('');
    }

    // 显示登录页
    this._showPage('login');
  },

  _bindTitlebar() {
    if (!window.electronAPI) return;
    document.getElementById('btn-minimize').addEventListener('click', () => window.electronAPI.minimize());
    document.getElementById('btn-maximize').addEventListener('click', () => window.electronAPI.maximize());
    document.getElementById('btn-close').addEventListener('click', () => window.electronAPI.close());
  },

  // ======================== 页面路由 ========================

  _showPage(pageName, data = null) {
    // 取消旧页面上所有进行中的请求
    ApiClient.abortPage();

    this._currentPage = pageName;
    const content = document.getElementById('content');

    // 如果已登录，显示侧边栏；否则隐藏
    const sidebar = document.getElementById('sidebar');
    const titlebar = document.getElementById('titlebar');

    if (pageName === 'login') {
      sidebar.classList.add('hidden');
      titlebar.querySelector('.titlebar-title').textContent = '晚风影视';
      content.innerHTML = '';
      LoginPage.render(content, data);
    } else {
      sidebar.classList.remove('hidden');
      // 更新侧边栏高亮
      document.querySelectorAll('.nav-item').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.page === pageName);
      });
      // 更新用户信息
      if (this._userInfo) {
        const nick = this._userInfo.nickname || this._userInfo.username || '';
        document.getElementById('sidebar-nickname').textContent = nick;
        document.getElementById('sidebar-avatar').textContent = nick.charAt(0).toUpperCase();
      }

      content.innerHTML = '';

      switch (pageName) {
        case 'home':
          HomePage.render(content, data);
          break;
        case 'history':
          HistoryPage.render(content, data);
          break;
        case 'favorites':
          FavoritesPage.render(content, data);
          break;
        case 'settings':
          SettingsPage.render(content, data);
          break;
        case 'detail':
          DetailPage.render(content, data);
          break;
        case 'player':
          PlayerPage.render(content, data);
          break;
      }
    }
  },

  // ======================== 登录相关 ========================

  _onLoginSuccess(userInfo) {
    this._userInfo = userInfo;
    ApiClient.setToken(userInfo.token);
    AuthManager.saveLogin(
      userInfo.token,
      userInfo.user_id,
      userInfo.username,
      userInfo.nickname || userInfo.username,
      ApiClient.getBaseUrl()
    );
    // 先检查版本，再进入主页
    this._checkVersion().then(() => this._showPage('home'));
  },

  /** 检查客户端版本更新 */
  async _checkVersion() {
    try {
      const result = await ApiClient.getServerSettings();
      if (result.code !== 200) return;
      const data = result.data;
      const latest = data.latest_version || '1.0.0';
      const forceUpdate = data.force_update === '1';
      const updateNotes = data.update_notes || '';
      const updateUrl = data.update_url || '';

      if (APP_VERSION >= latest) return;

      if (forceUpdate) {
        this._showUpdateModal({
          title: '🔔 重要更新',
          notes: updateNotes,
          latestVersion: latest,
          currentVersion: APP_VERSION,
          url: updateUrl,
          force: true,
        });
      } else {
        // 可选更新：有 url 时弹窗，没有则 toast
        if (updateUrl) {
          this._showUpdateModal({
            title: '🎉 发现新版本',
            notes: updateNotes,
            latestVersion: latest,
            currentVersion: APP_VERSION,
            url: updateUrl,
            force: false,
          });
        } else {
          App.toast('有新版本 v' + latest + ' 可用，建议更新', 'info');
        }
      }
    } catch (_) { /* 版本检查失败不影响使用 */ }
  },

  /** 显示版本更新弹窗 */
  _showUpdateModal(opts) {
    const overlay = document.getElementById('modal-overlay');

    const notesHtml = opts.notes
      ? `<div class="update-notes">${opts.notes.replace(/\n/g, '<br>')}</div>`
      : '';

    overlay.innerHTML = `
      <div class="modal-box update-modal">
        <h3>${opts.title}</h3>
        <div class="update-version-row">
          <span>当前版本：${opts.currentVersion}</span>
          <span>→</span>
          <span style="color:var(--primary);font-weight:600;">最新版本：${opts.latestVersion}</span>
        </div>
        ${notesHtml}
        <div class="modal-actions">
          ${opts.force
            ? `<button class="btn btn-sm cancel-btn">退出应用</button>`
            : `<button class="btn btn-outline btn-sm cancel-btn">暂不更新</button>`
          }
          <button class="btn btn-primary btn-sm confirm-btn">立即更新</button>
        </div>
      </div>
    `;
    overlay.classList.remove('hidden');

    const closeModal = () => { overlay.classList.add('hidden'); };

    overlay.querySelector('.cancel-btn').addEventListener('click', () => {
      closeModal();
      if (opts.force) {
        if (window.electronAPI) window.electronAPI.close();
      }
    });

    overlay.querySelector('.confirm-btn').addEventListener('click', () => {
      // 用系统默认浏览器打开下载链接
      const url = opts.url || 'https://github.com/fzw005421/wanfeng-video/releases';
      if (window.electronAPI && window.electronAPI.openExternal) {
        window.electronAPI.openExternal(url);
      } else {
        window.open(url, '_blank');
      }
      if (opts.force) {
        // 强制更新：给一点时间打开浏览器，然后关闭应用
        setTimeout(() => {
          if (window.electronAPI) window.electronAPI.close();
        }, 1500);
      } else {
        closeModal();
      }
    });
  },

  onLogout() {
    AuthManager.logout();
    ApiClient.setToken('');
    this._userInfo = null;
    this._showPage('login');
  },

  // ======================== 导航 ========================

  openDetail(vodId) {
    this._showPage('detail', { vodId });
  },

  openPlayer(data) {
    this._showPage('player', data);
  },

  goBack() {
    // 从详情页/播放页返回首页
    this._showPage('home');
  },

  // ======================== Toast 提示 ========================

  toast(msg, type = 'info') {
    const container = document.getElementById('toast-container');
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(() => { if (el.parentNode) el.remove(); }, 3000);
  },

  // ======================== 模态弹窗 ========================

  showConfirm(title, message) {
    return new Promise((resolve) => {
      const overlay = document.getElementById('modal-overlay');
      overlay.innerHTML = `
        <div class="modal-box">
          <h3>${title}</h3>
          <p>${message}</p>
          <div class="modal-actions">
            <button class="btn btn-outline btn-sm cancel-btn">取消</button>
            <button class="btn btn-sm confirm-btn">确定</button>
          </div>
        </div>
      `;
      overlay.classList.remove('hidden');
      overlay.querySelector('.cancel-btn').addEventListener('click', () => {
        overlay.classList.add('hidden');
        resolve(false);
      });
      overlay.querySelector('.confirm-btn').addEventListener('click', () => {
        overlay.classList.add('hidden');
        resolve(true);
      });
    });
  },

  showAlert(title, message) {
    return new Promise((resolve) => {
      const overlay = document.getElementById('modal-overlay');
      overlay.innerHTML = `
        <div class="modal-box">
          <h3>${title}</h3>
          <p>${message}</p>
          <div class="modal-actions">
            <button class="btn btn-sm ok-btn">确定</button>
          </div>
        </div>
      `;
      overlay.classList.remove('hidden');
      overlay.querySelector('.ok-btn').addEventListener('click', () => {
        overlay.classList.add('hidden');
        resolve(true);
      });
    });
  },

  // ======================== 公告弹窗 ========================

  /** 显示公告（如果用户没有选择"不再提示"） */
  async showAnnouncements() {
    const result = await ApiClient.getAnnouncements(1, 5);
    if (result.code !== 200) return;

    const list = (result.data && result.data.list) || [];
    if (list.length === 0) return;

    // 检查用户是否选择了不再提示
    const dismissedId = parseInt(localStorage.getItem('wanfeng_ann_dismissed') || '0');
    // 找出未读公告（ID > 已忽略ID）
    const unread = list.filter(a => a.id > dismissedId);
    if (unread.length === 0) return;

    // 显示最新一条未读公告
    const ann = unread[unread.length - 1];
    this._renderAnnouncement(ann);
  },

  _renderAnnouncement(ann) {
    // 移除旧的公告弹窗
    const old = document.querySelector('.announcement-overlay');
    if (old) old.remove();

    const overlay = document.createElement('div');
    overlay.className = 'announcement-overlay';
    overlay.innerHTML = `
      <div class="announcement-box">
        <div class="ann-header">
          <h3>📢 ${ann.title || '系统公告'}</h3>
          <span style="font-size:12px;color:var(--text-hint);">${ann.created_at || ''}</span>
        </div>
        <div class="ann-body">${ann.content || ''}</div>
        <div class="ann-footer">
          <button class="btn btn-text btn-sm ann-dismiss-btn">不再提示</button>
          <button class="btn btn-sm ann-close-btn">知道了</button>
        </div>
      </div>
    `;

    // 关闭
    overlay.querySelector('.ann-close-btn').addEventListener('click', () => {
      overlay.remove();
    });

    // 不再提示 — 记录最新公告ID
    overlay.querySelector('.ann-dismiss-btn').addEventListener('click', () => {
      if (ann.id) {
        localStorage.setItem('wanfeng_ann_dismissed', ann.id);
      }
      overlay.remove();
    });

    // 点击遮罩关闭
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.remove();
    });

    document.body.appendChild(overlay);
  },
};

// ======================== 侧边栏导航绑定 ========================

document.addEventListener('DOMContentLoaded', () => {
  // 导航按钮点击
  document.querySelectorAll('.nav-item').forEach(btn => {
    btn.addEventListener('click', () => {
      const page = btn.dataset.page;
      App._showPage(page);
    });
  });

  // 退出登录按钮
  document.getElementById('btn-logout').addEventListener('click', async () => {
    const ok = await App.showConfirm('退出登录', '确定要退出当前账号吗？');
    if (ok) App.onLogout();
  });

  // 主题切换按钮
  document.getElementById('theme-toggle').addEventListener('click', () => {
    App.toggleTheme();
  });

  // 启动应用
  App.init();
});
