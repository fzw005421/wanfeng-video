/**
 * 晚风影视 - 本地认证管理器
 * 使用 localStorage 存储 Token 和用户信息
 * 完全对照 python/client/auth_manager.py
 */

const AuthManager = {
  _prefix: 'wf_',

  // ======================== 基础存储 ========================

  _key(k) { return this._prefix + k; },

  set(key, value) {
    try {
      const v = typeof value === 'string' ? value : JSON.stringify(value);
      localStorage.setItem(this._key(key), v);
    } catch (e) {
      console.error('AuthManager.set error:', e);
    }
  },

  get(key, defaultValue = null) {
    try {
      const raw = localStorage.getItem(this._key(key));
      if (raw === null) return defaultValue;
      try { return JSON.parse(raw); } catch (e) { return raw; }
    } catch (e) {
      return defaultValue;
    }
  },

  remove(key) {
    try {
      localStorage.removeItem(this._key(key));
    } catch (e) { /* ignore */ }
  },

  // ======================== 登录信息 ========================

  saveLogin(token, userId, username, nickname, serverUrl) {
    this.set('token', token);
    this.set('user_id', userId);
    this.set('username', username);
    this.set('nickname', nickname);
    if (serverUrl) this.set('server_url', serverUrl);
  },

  getToken() {
    return this.get('token');
  },

  getServerUrl() {
    return this.get('server_url', 'http://pc.snjsy.de');
  },

  isLoggedIn() {
    return !!this.getToken();
  },

  logout() {
    this.remove('token');
    this.remove('user_id');
    this.remove('username');
    this.remove('nickname');
    // 保留 server_url，方便下次登录
  },

  getUserInfo() {
    return {
      user_id: this.get('user_id'),
      username: this.get('username'),
      nickname: this.get('nickname', ''),
      token: this.getToken(),
      server_url: this.getServerUrl(),
    };
  },

  // ======================== 离线播放记录 ========================

  saveOfflineHistory(vodId, vodName, vodPic, episodeIndex, episodeName, playPosition = 0) {
    const list = this.get('offline_history', []);
    // 去重更新
    const idx = list.findIndex(r => r.vod_id === vodId && r.episode_index === episodeIndex);
    const record = { vod_id: vodId, vod_name: vodName, vod_pic: vodPic, episode_index: episodeIndex, episode_name: episodeName, play_position: playPosition, updated_at: new Date().toISOString() };
    if (idx >= 0) {
      list[idx] = record;
    } else {
      list.unshift(record);
    }
    // 最多保留 50 条
    if (list.length > 50) list.length = 50;
    this.set('offline_history', list);
  },

  getOfflineHistory(limit = 50) {
    return this.get('offline_history', []).slice(0, limit);
  },
};
