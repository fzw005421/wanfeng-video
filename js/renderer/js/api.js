/**
 * 晚风影视 - API 客户端
 * 负责所有与后端的 HTTP 通信
 * 支持请求取消、自动重试、页面级竞态防护
 */

const ApiClient = {
  // 服务器地址（不暴露在 UI 中）
  _baseUrl: 'http://127.0.0.1:38971',  // 修改为你的后端服务器地址
  _token: '',
  _timeout: 15000,
  _maxRetries: 2,

  // 页面级请求控制
  _pageId: 0,
  _pageAbortController: null,
  _pendingRequests: new Map(),

  // ======================== 基础请求 ========================

  setBaseUrl(url) {
    if (url) this._baseUrl = url.replace(/\/+$/, '');
  },

  getBaseUrl() {
    return this._baseUrl;
  },

  setToken(token) {
    this._token = token || '';
  },

  /** 页面切换时调用：取消旧页面的所有进行中的请求 */
  abortPage() {
    this._pageId++;
    if (this._pageAbortController) {
      this._pageAbortController.abort();
      this._pageAbortController = null;
    }
    this._pendingRequests.clear();
  },

  /** 获取当前页面的 AbortController */
  _getPageController() {
    if (!this._pageAbortController || this._pageAbortController.signal.aborted) {
      this._pageAbortController = new AbortController();
    }
    return this._pageAbortController;
  },

  async _request(method, path, data = null, params = null, retryCount = 0) {
    const pageId = this._pageId;
    let url = this._baseUrl + path;

    // 拼接查询参数
    if (params) {
      const qs = Object.entries(params)
        .filter(([_, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v))
        .join('&');
      if (qs) url += '?' + qs;
    }

    const headers = {
      'Content-Type': 'application/json',
      'User-Agent': 'WanFengVideo/1.0 (Windows NT 10.0)',
    };

    if (this._token) {
      headers['Authorization'] = 'Bearer ' + this._token;
    }

    const options = {
      method: method,
      headers: headers,
    };

    if (data && (method === 'POST' || method === 'PUT')) {
      options.body = JSON.stringify(data);
    }

    try {
      const timeoutController = new AbortController();
      const timeoutId = setTimeout(() => timeoutController.abort(), this._timeout);

      // 合并页面级取消 + 超时取消
      const pageCtrl = this._getPageController();
      const combinedSignal = timeoutController.signal;

      // 监听页面级取消
      const onPageAbort = () => { timeoutController.abort(); };
      pageCtrl.signal.addEventListener('abort', onPageAbort, { once: true });

      options.signal = combinedSignal;

      const resp = await fetch(url, options);
      clearTimeout(timeoutId);
      pageCtrl.signal.removeEventListener('abort', onPageAbort);

      // 如果页面已切换，丢弃响应
      if (this._pageId !== pageId) {
        return { code: -2, msg: '请求已取消' };
      }

      // HTTP 5xx 或网络层错误 → 重试
      if (resp.status >= 500 && retryCount < this._maxRetries) {
        const delay = Math.pow(2, retryCount) * 500 + Math.random() * 300;
        await new Promise(r => setTimeout(r, delay));
        return this._request(method, path, data, params, retryCount + 1);
      }

      try {
        const result = await resp.json();
        return result;
      } catch (e) {
        return {
          code: resp.status,
          msg: `服务器响应格式错误 (HTTP ${resp.status})`
        };
      }
    } catch (e) {
      if (e.name === 'AbortError') {
        if (this._pageId !== pageId) {
          return { code: -2, msg: '请求已取消' };
        }
        // 超时 → 重试
        if (retryCount < this._maxRetries) {
          const delay = Math.pow(2, retryCount) * 800 + Math.random() * 500;
          await new Promise(r => setTimeout(r, delay));
          return this._request(method, path, data, params, retryCount + 1);
        }
        return { code: -1, msg: '连接服务器超时，请稍后重试' };
      }
      if (e.message === 'Failed to fetch' || e.name === 'TypeError') {
        if (retryCount < this._maxRetries) {
          const delay = Math.pow(2, retryCount) * 1000 + Math.random() * 500;
          await new Promise(r => setTimeout(r, delay));
          return this._request(method, path, data, params, retryCount + 1);
        }
        return { code: -1, msg: '无法连接到服务器，请检查网络连接' };
      }
      return { code: -1, msg: '网络请求失败，请检查网络设置' };
    }
  },

  _get(path, params) { return this._request('GET', path, null, params); },
  _post(path, data) { return this._request('POST', path, data); },
  _put(path, data) { return this._request('PUT', path, data); },
  _delete(path) { return this._request('DELETE', path); },

  // ======================== 认证 ========================

  login(username, password) {
    return this._post('/api/login', { username, password });
  },

  register(username, password, nickname = '') {
    return this._post('/api/register', { username, password, nickname });
  },

  // ======================== 用户 ========================

  getProfile() {
    return this._get('/api/user/profile');
  },

  updateProfile(nickname = '', avatar = '') {
    return this._post('/api/user/profile', { nickname, avatar });
  },

  changePassword(old_password, new_password) {
    return this._post('/api/user/change-password', { old_password, new_password });
  },

  // ======================== 影视 ========================

  getVodList(page = 1, pageSize = 20, typeId = 0, order = 'vod_time') {
    return this._get('/api/vod/list', {
      page, page_size: pageSize, type_id: typeId, order
    });
  },

  getVodDetail(vodId) {
    return this._get(`/api/vod/${vodId}`);
  },

  getRecommend(page = 1, pageSize = 10, position = 'home') {
    return this._get('/api/vod/recommend', {
      page, page_size: pageSize, position
    });
  },

  getBanners() {
    return this._get('/api/vod/recommend', {
      page: 1, page_size: 8, position: 'banner'
    });
  },

  search(keyword, page = 1, pageSize = 20) {
    return this._get('/api/vod/search', {
      kw: keyword, page, page_size: pageSize
    });
  },

  // ======================== 播放 ========================

  async play(vodId, episodeIndex = 1, parseApiId = 0, sourceIndex = 0) {
    console.log('[API.play] vod_id       =', vodId);
    console.log('[API.play] episode_index=', episodeIndex);
    console.log('[API.play] parse_api_id =', parseApiId);
    console.log('[API.play] source_index =', sourceIndex);
    const result = await this._post('/api/play', {
      vod_id: vodId,
      episode_index: episodeIndex,
      parse_api_id: parseApiId,
      source_index: sourceIndex
    });
    console.log('[API.play] 响应 code =', result.code);
    console.log('[API.play] 响应 msg  =', result.msg || '(无)');
    if (result.data) {
      console.log('[API.play] 响应 data.video_url =', result.data.video_url ? result.data.video_url.substring(0, 150) + '...' : '(空!!!)');
      console.log('[API.play] 响应 data.type      =', result.data.type || '(未返回!!!)');
      console.log('[API.play] 响应 data.parse_api_name =', result.data.parse_api_name || '(无)');
    } else {
      console.error('[API.play] ⚠️ 响应中没有 data 字段！');
    }
    return result;
  },

  getParseApis() {
    return this._get('/api/parse-apis');
  },

  // ======================== 播放记录 ========================

  getHistory(page = 1, pageSize = 20) {
    return this._get('/api/history/list', { page, page_size: pageSize });
  },

  saveHistory(vodId, episodeIndex, playPosition = 0, duration = 0,
              vodName = '', vodPic = '', episodeName = '', parseApiId = 0) {
    return this._post('/api/history/save', {
      vod_id: vodId,
      episode_index: episodeIndex,
      play_position: playPosition,
      duration: duration,
      vod_name: vodName,
      vod_pic: vodPic,
      episode_name: episodeName,
      parse_api_id: parseApiId
    });
  },

  deleteHistory(historyId) {
    return this._delete(`/api/history/${historyId}`);
  },

  // ======================== 收藏 ========================

  getFavorites(page = 1, pageSize = 20) {
    return this._get('/api/favorites/list', { page, page_size: pageSize });
  },

  toggleFavorite(vodId, vodInfo = {}) {
    return this._post('/api/favorite/toggle', {
      vod_id: vodId,
      vod_name: vodInfo.vod_name || '',
      vod_pic: vodInfo.vod_pic || '',
      vod_remarks: vodInfo.vod_remarks || '',
      vod_score: String(vodInfo.vod_score || ''),
      vod_year: String(vodInfo.vod_year || ''),
      vod_area: String(vodInfo.vod_area || ''),
    });
  },

  checkFavorite(vodId) {
    return this._get('/api/favorites/check', { vod_id: vodId });
  },

  // ======================== 公告 ========================

  getAnnouncements(page = 1, pageSize = 10) {
    return this._get('/api/announcements', { page, page_size: pageSize });
  },

  // ======================== 设置 ========================

  getServerSettings() {
    return this._get('/api/settings');
  },
};
