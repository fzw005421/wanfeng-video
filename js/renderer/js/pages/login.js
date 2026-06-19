/**
 * 晚风影视 - 登录/注册页面
 * 完全对照 python/client/ui/login_window.py
 */

const LoginPage = {
  _mode: 'login', // 'login' | 'register'
  _loading: false,

  render(container, _data) {
    this._mode = 'login';
    container.innerHTML = `
      <div class="login-container">
        <div class="login-hero">
          <div class="login-hero-icon">
            <svg viewBox="0 0 48 48" width="48" height="48" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 6 30 20 46 22 34 33 37 47 23 39 9 47 12 33 0 22 16 20"/></svg>
          </div>
          <h1>晚风影视</h1>
          <p>随时随地，想看就看</p>
        </div>
        <div class="login-card-wrap">
          <div class="login-card">
            <div class="login-card-header"><h2>欢迎回来</h2></div>
            <div class="login-tabs">
              <button id="tab-login" class="active-tab">登  录</button>
              <button id="tab-register">注  册</button>
            </div>
            <div class="login-form" id="login-form">
              <label>用户名</label>
              <input class="form-input" id="login-username" type="text" placeholder="请输入用户名" autocomplete="username">
              <label>密码</label>
              <input class="form-input" id="login-password" type="password" placeholder="请输入密码" autocomplete="current-password">
              <div class="remember-row">
                <label class="remember-label">
                  <input type="checkbox" id="login-remember">
                  <span>记住密码</span>
                </label>
              </div>
              <button class="btn btn-submit" id="btn-login-submit">登  录</button>
            </div>
            <div class="login-form hidden" id="register-form">
              <label>用户名</label>
              <input class="form-input" id="reg-username" type="text" placeholder="3-50个字符，支持中文">
              <label>密码</label>
              <input class="form-input" id="reg-password" type="password" placeholder="至少6位密码">
              <label>确认密码</label>
              <input class="form-input" id="reg-password2" type="password" placeholder="再次输入密码">
              <button class="btn btn-submit" id="btn-register-submit">注  册</button>
            </div>
            <div class="login-footer">
              <span class="status-text" id="login-status">晚风影视 PC 客户端</span>
            </div>
          </div>
        </div>
      </div>
    `;

    this._bindEvents(container);
    this._loadSavedCredentials();
  },

  _bindEvents(container) {
    // Tab 切换
    container.querySelector('#tab-login').addEventListener('click', () => this._switchTab('login'));
    container.querySelector('#tab-register').addEventListener('click', () => this._switchTab('register'));

    // 登录
    container.querySelector('#btn-login-submit').addEventListener('click', () => this._doLogin());
    container.querySelector('#login-password').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') this._doLogin();
    });

    // 注册
    container.querySelector('#btn-register-submit').addEventListener('click', () => this._doRegister());
    container.querySelector('#reg-password2').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') this._doRegister();
    });
  },

  _switchTab(mode) {
    this._mode = mode;
    const loginTab = document.querySelector('#tab-login');
    const regTab = document.querySelector('#tab-register');
    const loginForm = document.querySelector('#login-form');
    const regForm = document.querySelector('#register-form');

    if (mode === 'login') {
      loginTab.classList.add('active-tab');
      regTab.classList.remove('active-tab');
      loginForm.classList.remove('hidden');
      regForm.classList.add('hidden');
    } else {
      regTab.classList.add('active-tab');
      loginTab.classList.remove('active-tab');
      regForm.classList.remove('hidden');
      loginForm.classList.add('hidden');
    }
  },

  _loadSavedCredentials() {
    const username = AuthManager.get('remembered_username') || AuthManager.get('username');
    const pwdEncoded = AuthManager.get('remembered_password');
    if (username) {
      const userInput = document.getElementById('login-username');
      if (userInput) userInput.value = username;
    }
    if (pwdEncoded) {
      const pwdInput = document.getElementById('login-password');
      const chk = document.getElementById('login-remember');
      if (pwdInput) pwdInput.value = this._decodePassword(pwdEncoded);
      if (chk) chk.checked = true;
    }
  },

  _encodePassword(plain) {
    // 简单的 base64 编码防止明文泄露（桌面端本地存储，安全风险可接受）
    try { return btoa(plain); } catch (_) { return ''; }
  },

  _decodePassword(encoded) {
    try { return atob(encoded); } catch (_) { return ''; }
  },

  async _doLogin() {
    if (this._loading) return;
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;

    if (!username || !password) {
      App.toast('请输入用户名和密码', 'warning');
      return;
    }

    this._setLoading(true);
    document.getElementById('login-status').textContent = '正在连接服务器...';

    const result = await ApiClient.login(username, password);

    this._setLoading(false);
    document.getElementById('login-status').textContent = '晚风影视 v1.0';

    if (result.code === 200) {
      // 记住密码
      const remember = document.getElementById('login-remember').checked;
      if (remember) {
        AuthManager.set('remembered_username', username);
        AuthManager.set('remembered_password', this._encodePassword(password));
      } else {
        AuthManager.remove('remembered_username');
        AuthManager.remove('remembered_password');
      }
      App._onLoginSuccess(result.data);
    } else {
      App.toast(result.msg || '登录失败', 'error');
    }
  },

  async _doRegister() {
    if (this._loading) return;
    const username = document.getElementById('reg-username').value.trim();
    const password = document.getElementById('reg-password').value;
    const password2 = document.getElementById('reg-password2').value;

    if (!username || !password) {
      App.toast('请输入用户名和密码', 'warning');
      return;
    }
    if (username.length < 3) {
      App.toast('用户名至少3个字符', 'warning');
      return;
    }
    if (password.length < 6) {
      App.toast('密码至少6位', 'warning');
      return;
    }
    if (password !== password2) {
      App.toast('两次输入的密码不一致', 'warning');
      return;
    }

    this._setLoading(true);
    document.getElementById('login-status').textContent = '正在连接服务器...';

    const result = await ApiClient.register(username, password, username);

    this._setLoading(false);
    document.getElementById('login-status').textContent = '晚风影视 v1.0';

    if (result.code === 200) {
      App.toast('注册成功，欢迎加入晚风影视！', 'success');
      App._onLoginSuccess(result.data);
    } else {
      App.toast(result.msg || '注册失败', 'error');
    }
  },

  _setLoading(loading) {
    this._loading = loading;
    const loginBtn = document.getElementById('btn-login-submit');
    const regBtn = document.getElementById('btn-register-submit');
    if (loginBtn) {
      loginBtn.disabled = loading;
      loginBtn.textContent = loading ? '正在连接...' : '登  录';
    }
    if (regBtn) {
      regBtn.disabled = loading;
      regBtn.textContent = loading ? '正在连接...' : '注  册';
    }
  },
};
