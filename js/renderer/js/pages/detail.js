/**
 * 晚风影视 - 影片详情页
 * 对照 python/client/ui/detail_page.py
 */

const DetailPage = {
  _vodData: null,
  _sources: [],
  _episodes: [],
  _parseApis: [],
  _selectedEpIdx: 1,
  _selectedSourceIdx: 0,
  _selectedParseId: 0,
  _isFavorited: false,
  _pageId: 0,

  render(container, data) {
    this._pageId = Date.now();
    const vodId = data && data.vodId ? data.vodId : 0;
    container.innerHTML = `
      <div class="page active" id="detail-page">
        <div class="page-topbar">
          <button class="btn-text btn-sm" id="detail-back">← 返回</button>
          <div class="page-title" id="detail-page-title">影片详情</div>
          <div class="spacer"></div>
          <button class="btn btn-gold btn-sm" id="detail-fav">收藏</button>
        </div>
        <div class="page-scroll" id="detail-scroll">
          <div class="loading-placeholder">正在加载影片信息...</div>
        </div>
      </div>
    `;

    document.getElementById('detail-back').addEventListener('click', () => App.goBack());
    document.getElementById('detail-fav').addEventListener('click', () => this._toggleFavorite());

    if (vodId) this._load(vodId);
  },

  async _load(vodId) {
    const pageId = this._pageId;
    const scroll = document.getElementById('detail-scroll');
    if (!scroll) return;

    const [vodResult, parseResult, favResult] = await Promise.all([
      ApiClient.getVodDetail(vodId),
      ApiClient.getParseApis(),
      ApiClient.checkFavorite(vodId),
    ]);

    if (this._pageId !== pageId) return;

    if (vodResult.code !== 200) {
      scroll.innerHTML = `<div class="empty-state">${vodResult.msg || '加载失败'}</div>`;
      return;
    }

    this._vodData = vodResult.data;
    this._sources = this._vodData.sources || [];
    this._episodes = this._vodData.episodes || [];
    this._selectedSourceIdx = 0;
    this._parseApis = (parseResult.code === 200 ? parseResult.data : []) || [];
    if (this._parseApis.length > 0) this._selectedParseId = this._parseApis[0].id || 0;
    this._isFavorited = favResult.code === 200 && favResult.data && favResult.data.favorited;

    // 更新 topbar
    document.getElementById('detail-page-title').textContent = this._vodData.vod_name || '影片详情';
    this._updateFavButton();

    this._renderContent(scroll);
  },

  _renderContent(scroll) {
    const v = this._vodData;
    scroll.innerHTML = '';

    // 主信息卡片：海报 + 信息
    const infoCard = document.createElement('div');
    infoCard.className = 'detail-main-card';

    // 海报
    const poster = document.createElement('div');
    poster.className = 'detail-poster';
    poster.textContent = (v.vod_name || '').slice(0, 4);
    if (v.vod_pic) {
      const img = new Image();
      img.onload = () => { poster.textContent = ''; poster.appendChild(img); };
      img.onerror = () => { poster.textContent = (v.vod_name || '').slice(0, 4); };
      img.src = v.vod_pic;
    }
    infoCard.appendChild(poster);

    // 右侧信息
    const infoRight = document.createElement('div');
    infoRight.className = 'detail-info-right';
    infoRight.innerHTML = `<div class="detail-name">${v.vod_name || ''}</div>`;

    // 标签行
    const tags = document.createElement('div');
    tags.className = 'detail-tags';
    const tagItems = [];
    if (v.vod_score && v.vod_score !== '0.0') tagItems.push('★ ' + v.vod_score + '分');
    if (v.vod_year) tagItems.push(v.vod_year);
    if (v.vod_area) tagItems.push(v.vod_area);
    if (v.type_name) tagItems.push(v.type_name);
    if (v.vod_remarks) tagItems.push(v.vod_remarks);
    if (v.vod_lang) tagItems.push(v.vod_lang);
    tags.innerHTML = tagItems.map(t => `<span class="detail-tag">${t}</span>`).join('');
    infoRight.appendChild(tags);

    // 导演/演员
    if (v.vod_director) {
      const dir = document.createElement('div');
      dir.className = 'detail-field';
      dir.innerHTML = `<span class="label">导演:</span> ${v.vod_director}`;
      infoRight.appendChild(dir);
    }
    if (v.vod_actor) {
      const act = document.createElement('div');
      act.className = 'detail-field';
      act.innerHTML = `<span class="label">主演:</span> ${v.vod_actor}`;
      infoRight.appendChild(act);
    }

    // 简介
    if (v.vod_blurb && v.vod_blurb !== v.vod_content) {
      const blurb = document.createElement('p');
      blurb.className = 'detail-blurb';
      blurb.textContent = v.vod_blurb;
      infoRight.appendChild(blurb);
    }

    infoCard.appendChild(infoRight);
    scroll.appendChild(infoCard);

    // 剧情简介
    if (v.vod_content) {
      const intro = document.createElement('div');
      intro.className = 'detail-intro';
      intro.innerHTML = `<h3>剧情简介</h3><p>${v.vod_content.trim()}</p>`;
      scroll.appendChild(intro);
    }

    // 剧集选择区
    this._renderEpisodeSection(scroll);
  },

  _renderEpisodeSection(scroll) {
    // 如果多来源且 sources 有数据，使用 sources；否则回退到 episodes
    const useSources = this._sources.length > 0;
    const currentEpisodes = useSources
      ? (this._sources[this._selectedSourceIdx]?.episodes || [])
      : this._episodes;

    if (currentEpisodes.length === 0 && this._episodes.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'empty-state';
      empty.textContent = '暂无播放资源';
      scroll.appendChild(empty);
      return;
    }

    const section = document.createElement('div');
    section.className = 'episode-section';

    const header = document.createElement('div');
    header.className = 'episode-header';
    header.innerHTML = `<h3>播放列表 (共${currentEpisodes.length}集)</h3>`;

    // 解析接口选择
    if (this._parseApis.length > 0) {
      const selectWrap = document.createElement('div');
      selectWrap.className = 'episode-parse-wrap';
      selectWrap.innerHTML = '<label>播放接口:</label>';
      const select = document.createElement('select');
      select.id = 'detail-parse-select';
      this._parseApis.forEach(api => {
        const opt = document.createElement('option');
        opt.value = api.id;
        opt.textContent = api.name;
        select.appendChild(opt);
      });
      select.addEventListener('change', () => {
        this._selectedParseId = parseInt(select.value) || 0;
      });
      selectWrap.appendChild(select);
      header.appendChild(selectWrap);
    }
    section.appendChild(header);

    // 播放源选择器（多个来源时显示）
    if (useSources && this._sources.length > 1) {
      const sourceTabs = document.createElement('div');
      sourceTabs.className = 'source-tabs';
      sourceTabs.id = 'source-tabs';
      this._sources.forEach((src, idx) => {
        const tab = document.createElement('button');
        tab.className = 'source-tab';
        if (idx === this._selectedSourceIdx) tab.classList.add('active-src');
        tab.textContent = src.name || ('播放源' + (idx + 1));
        tab.addEventListener('click', () => {
          this._selectedSourceIdx = idx;
          this._selectedEpIdx = 1;
          // 重新渲染剧集区
          const oldSection = document.querySelector('.episode-section');
          if (oldSection) oldSection.remove();
          this._renderEpisodeSection(scroll);
        });
        sourceTabs.appendChild(tab);
      });
      section.appendChild(sourceTabs);
    }

    // 剧集按钮网格
    const grid = document.createElement('div');
    grid.className = 'episode-grid';
    grid.id = 'episode-grid';

    currentEpisodes.forEach(ep => {
      const btn = document.createElement('button');
      btn.className = 'episode-btn';
      btn.textContent = ep.name || ep.index || '';
      const epIdx = ep.index || 1;
      if (epIdx === this._selectedEpIdx) btn.classList.add('active-ep');
      btn.addEventListener('click', () => this._playEpisode(epIdx));
      grid.appendChild(btn);
    });

    section.appendChild(grid);
    scroll.appendChild(section);
  },

  async _playEpisode(epIndex) {
    this._selectedEpIdx = epIndex;
    const sourceIdx = this._sources.length > 1 ? this._selectedSourceIdx : 0;
    const result = await ApiClient.play(
      this._vodData.vod_id, epIndex, this._selectedParseId, sourceIdx
    );

    if (result.code !== 200) {
      App.toast(result.msg || '无法获取播放地址，请切换解析接口', 'error');
      return;
    }

    const d = result.data;
    // 取当前播放源的剧集列表，传给播放页用于"下一集"导航
    const currentSource = this._sources.length > 1
      ? (this._sources[this._selectedSourceIdx] || null)
      : null;
    const episodesAll = currentSource ? currentSource.episodes : this._episodes;

    App.openPlayer({
      vod_id: d.vod_id,
      episode_index: d.episode_index,
      source_index: sourceIdx,
      video_url: d.video_url,
      video_type: d.type || 'hls',
      vod_name: d.vod_name,
      episode_name: d.episode_name,
      parse_api_id: d.parse_api_id,
      parse_api_name: d.parse_api_name,
      episodes_all: episodesAll,
    });
  },

  async _toggleFavorite() {
    if (!this._vodData) return;
    const result = await ApiClient.toggleFavorite(this._vodData.vod_id, {
      vod_name: this._vodData.vod_name || '',
      vod_pic: this._vodData.vod_pic || '',
      vod_remarks: this._vodData.vod_remarks || '',
      vod_score: this._vodData.vod_score || '',
      vod_year: this._vodData.vod_year || '',
      vod_area: this._vodData.vod_area || '',
    });
    if (result.code === 200) {
      this._isFavorited = result.data && result.data.favorited;
      this._updateFavButton();
      App.toast(this._isFavorited ? '已收藏' : '已取消收藏', 'success');
    }
  },

  _updateFavButton() {
    const btn = document.getElementById('detail-fav');
    if (!btn) return;
    if (this._isFavorited) {
      btn.textContent = '已收藏';
      btn.className = 'btn btn-gold btn-sm active';
    } else {
      btn.textContent = '收藏';
      btn.className = 'btn btn-gold btn-sm';
    }
  },
};
