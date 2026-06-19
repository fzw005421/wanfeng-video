/**
 * 晚风影视 - 播放页面 (DEBUG版)
 */
const PlayerPage = {
  _data: null,
  _parseApis: [],
  _saveInterval: null,
  _pageId: 0,

  render(container, data) {
    this._pageId = Date.now();
    this._data = data || {};

    console.log('');
    console.log('═══════════════════════════════════════');
    console.log('[PlayerPage.render] 进入播放页');
    console.log('[PlayerPage.render] data.vod_id        =', data.vod_id);
    console.log('[PlayerPage.render] data.episode_index =', data.episode_index);
    console.log('[PlayerPage.render] data.video_url     =', data.video_url ? (data.video_url.substring(0, 150) + (data.video_url.length > 150 ? '...' : '')) : '(空!!!)');
    console.log('[PlayerPage.render] data.video_url 协议 =', data.video_url ? data.video_url.split('://')[0] : 'N/A');
    console.log('[PlayerPage.render] data.video_type    =', data.video_type || '(未传)');
    console.log('[PlayerPage.render] data.parse_api_id  =', data.parse_api_id);
    console.log('[PlayerPage.render] data.parse_api_name=', data.parse_api_name);
    console.log('[PlayerPage.render] data.vod_name      =', data.vod_name);
    console.log('[PlayerPage.render] data.episode_name  =', data.episode_name);

    container.innerHTML = `
      <div class="page active" id="player-page">
        <div class="player-topbar">
          <button class="btn-text btn-sm" id="player-back">&lt; 返回详情</button>
          <div class="player-title" id="player-page-title">${data.vod_name || ''} - ${data.episode_name || ''}</div>
          <div class="spacer"></div>
          <label>播放接口:</label>
          <select id="player-parse-select"></select>
        </div>
        <div class="player-container" id="player-container-wrap"></div>
      </div>
    `;

    // 挂载播放器
    this._currentEpisodes = data.episodes_all || [];
    VideoPlayer.mount(
      document.getElementById('player-container-wrap'),
      {
        onError: (msg) => {
          console.error('[PlayerPage.onError] 播放器报错:', msg);
          App.toast(msg, 'error');
        },
        onReady: () => {
          console.log('[PlayerPage.onReady] 播放器就绪回调');
          // 恢复播放进度
          const pos = data.resume_position;
          if (pos && pos > 0) {
            setTimeout(() => VideoPlayer.seek(pos), 1000);
          }
          // 显示下一集按钮
          this._updateNextBtn();
        },
        onEnded: () => this._onVideoEnded(),
        onNext: () => this._onVideoEnded(),
      }
    );

    // 开始播放
    console.log('[PlayerPage.render] 调用 VideoPlayer.play()...');
    VideoPlayer.play(data.video_url, data.video_type);

    // 如果剧集列表为空（从播放记录进入），异步加载剧集
    if (this._currentEpisodes.length === 0 && data.vod_id) {
      this._loadEpisodes(data.vod_id);
    }

    // 加载解析接口
    this._loadParseApis();

    // 定时保存进度
    this._saveInterval = setInterval(() => this._saveProgress(), 10000);

    // 返回按钮
    document.getElementById('player-back').addEventListener('click', () => {
      this._saveProgress();
      clearInterval(this._saveInterval);
      VideoPlayer.stop();
      App.openDetail(data.vod_id);
    });

    // 解析接口切换
    document.getElementById('player-parse-select').addEventListener('change', (e) => {
      this._switchParseApi(parseInt(e.target.value) || 0);
    });
  },

  /** 从播放记录进入时，异步加载剧集列表 */
  async _loadEpisodes(vodId) {
    try {
      const result = await ApiClient.getVodDetail(vodId);
      if (result.code !== 200) return;
      const v = result.data;
      const sources = v.sources || [];
      const episodes = v.episodes || [];
      // 优先用当前播放源的剧集
      const srcIdx = this._data.source_index || 0;
      const src = sources[srcIdx];
      this._currentEpisodes = (src && src.episodes && src.episodes.length > 0)
        ? src.episodes
        : episodes;
      this._updateNextBtn();
    } catch (_) { /* 静默失败，不影响播放 */ }
  },

  async _loadParseApis() {
    const pageId = this._pageId;
    const result = await ApiClient.getParseApis();
    console.log('[PlayerPage] 解析接口列表:', result.code, result.data ? result.data.length + '个' : '无数据');
    if (this._pageId !== pageId || result.code !== 200) return;
    this._parseApis = result.data || [];

    const select = document.getElementById('player-parse-select');
    if (!select) return;
    select.innerHTML = '';
    this._parseApis.forEach(api => {
      const opt = document.createElement('option');
      opt.value = api.id;
      opt.textContent = api.name;
      if (api.id === this._data.parse_api_id) opt.selected = true;
      select.appendChild(opt);
    });
  },

  async _switchParseApi(newParseId) {
    if (newParseId === this._data.parse_api_id) return;
    console.log('[PlayerPage] 切换解析接口:', this._data.parse_api_id, '→', newParseId);

    const savedPos = VideoPlayer.getCurrentTime();
    this._saveProgress();

    const result = await ApiClient.play(
      this._data.vod_id,
      this._data.episode_index,
      newParseId,
      this._data.source_index || 0
    );

    console.log('[PlayerPage] 切换接口 API 返回:', result.code, result.msg || '');
    if (result.code !== 200) {
      App.toast('切换接口失败: ' + (result.msg || '未知错误'), 'error');
      return;
    }

    const d = result.data;
    console.log('[PlayerPage] 新接口数据:');
    console.log('  video_url =', d.video_url ? d.video_url.substring(0, 150) + '...' : '(空)');
    console.log('  type      =', d.type);
    console.log('  api_name  =', d.parse_api_name);

    this._data.video_url = d.video_url;
    this._data.parse_api_id = d.parse_api_id;
    this._data.parse_api_name = d.parse_api_name;

    VideoPlayer.play(d.video_url, d.type);

    if (savedPos > 0) {
      setTimeout(() => VideoPlayer.seek(savedPos), 2000);
    }
  },

  _saveProgress() {
    if (!this._data || !this._data.vod_id) return;
    ApiClient.saveHistory(
      this._data.vod_id,
      this._data.episode_index || 1,
      Math.floor(VideoPlayer.getCurrentTime()),
      Math.floor(VideoPlayer.getDuration()),
      this._data.vod_name || '',
      this._data.vod_pic || '',
      this._data.episode_name || '',
      this._data.parse_api_id || 0
    );
  },

  /** 更新下一集按钮显隐 */
  _updateNextBtn() {
    const currentIdx = this._data.episode_index || 1;
    const nextEp = this._currentEpisodes.find(ep => ep.index === currentIdx + 1);
    VideoPlayer.showNextBtn(!!nextEp);
  },

  /** 视频播放完毕 → 直接播下一集 */
  async _onVideoEnded() {
    const currentIdx = this._data.episode_index || 1;
    const episodes = this._currentEpisodes;
    const nextEp = episodes.find(ep => ep.index === currentIdx + 1);

    if (!nextEp) {
      App.toast('已播放完全部剧集', 'info');
      return;
    }

    this._saveProgress();
    await this._playNextEpisode(nextEp.index);
  },

  /** 播放指定集数 */
  async _playNextEpisode(epIndex) {
    const result = await ApiClient.play(
      this._data.vod_id,
      epIndex,
      this._data.parse_api_id || 0,
      this._data.source_index || 0
    );

    if (result.code !== 200) {
      App.toast('切换剧集失败: ' + (result.msg || '未知错误'), 'error');
      return;
    }

    const d = result.data;
    this._data.video_url = d.video_url;
    this._data.episode_index = d.episode_index;
    this._data.episode_name = d.episode_name;
    this._data.parse_api_id = d.parse_api_id;
    this._data.parse_api_name = d.parse_api_name;

    // 更新标题
    const title = document.getElementById('player-page-title');
    if (title) title.textContent = `${this._data.vod_name || ''} - ${d.episode_name || ''}`;

    this._updateNextBtn();
    VideoPlayer.play(d.video_url, d.type);
  },
};
