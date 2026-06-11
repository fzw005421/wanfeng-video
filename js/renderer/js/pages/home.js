/**
 * 晚风影视 - 首页
 */

const HomePage = {
  _listPage: 1,
  _typeId: 0,
  _searchMode: false,
  _searchKeyword: '',
  _searchPage: 1,
  _searchTotalPages: 0,
  _allVods: [],
  _pageId: 0,
  _loadingMore: false,
  _totalPages: 0,
  _scrollEl: null,
  _searchHistory: [],

  render(container, _data) {
    this._pageId = Date.now();
    this._loadingMore = false;
    // 加载搜索历史
    this._loadHistory();
    container.innerHTML = `
      <div class="page active" id="home-page">
        <div class="page-topbar">
          <div class="page-title">晚风影视</div>
          <div class="spacer"></div>
          <div class="search-box" id="search-box-wrap">
            <input type="text" id="search-input" placeholder="搜索影片名称、演员..." autocomplete="off">
            <button class="btn btn-sm" id="btn-search">搜索</button>
          </div>
        </div>
        <div class="page-scroll" id="home-scroll">
          <div class="loading-placeholder" id="home-loading">正在加载首页内容...</div>
        </div>
      </div>
    `;

    this._scrollEl = document.getElementById('home-scroll');
    this._bindEvents();
    this._loadData();
  },

  _bindEvents() {
    const searchInput = document.getElementById('search-input');
    document.getElementById('btn-search').addEventListener('click', () => this._doSearch());
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') this._doSearch();
    });
    // 搜索框聚焦时显示历史
    searchInput.addEventListener('focus', () => this._showHistory());
    // 点击空白处关闭历史弹窗
    document.addEventListener('click', (e) => {
      const popup = document.getElementById('search-history-popup');
      const wrap = document.getElementById('search-box-wrap');
      if (popup && wrap && !wrap.contains(e.target)) {
        popup.remove();
      }
    });
    // 滚动监听：距底部 200px 时自动加载更多
    this._scrollEl.addEventListener('scroll', () => this._onScroll());
  },

  /** 加载搜索历史 */
  _loadHistory() {
    try {
      this._searchHistory = JSON.parse(localStorage.getItem('wanfeng_search_hist') || '[]');
    } catch (_) {
      this._searchHistory = [];
    }
  },

  /** 保存搜索历史（去重，最多10条） */
  _saveHistory(keyword) {
    this._searchHistory = this._searchHistory.filter(h => h !== keyword);
    this._searchHistory.unshift(keyword);
    if (this._searchHistory.length > 10) this._searchHistory.pop();
    localStorage.setItem('wanfeng_search_hist', JSON.stringify(this._searchHistory));
  },

  /** 显示搜索历史弹窗（追加到 body，避免被 overflow:hidden 裁掉） */
  _showHistory() {
    // 先移除旧弹窗
    const old = document.getElementById('search-history-popup');
    if (old) old.remove();

    if (this._searchHistory.length === 0) return;

    const input = document.getElementById('search-input');
    if (!input) return;

    const rect = input.getBoundingClientRect();

    const popup = document.createElement('div');
    popup.className = 'search-history-popup';
    popup.id = 'search-history-popup';
    popup.style.top = (rect.bottom + 6) + 'px';
    popup.style.left = rect.left + 'px';
    popup.style.width = rect.width + 'px';
    popup.innerHTML = `
      <div class="search-hist-header">
        <span>搜索历史</span>
        <button class="btn-text" style="font-size:12px;" id="hist-clear">清空</button>
      </div>
      <div class="search-hist-list">
        ${this._searchHistory.map(kw => `
          <div class="search-hist-item" data-kw="${kw.replace(/"/g, '&quot;')}">${kw}</div>
        `).join('')}
      </div>
    `;

    // 点击历史项
    popup.querySelectorAll('.search-hist-item').forEach(el => {
      el.addEventListener('click', () => {
        const kw = el.dataset.kw;
        document.getElementById('search-input').value = kw;
        popup.remove();
        this._doSearch();
      });
    });

    // 清空按钮
    popup.querySelector('#hist-clear').addEventListener('click', () => {
      this._searchHistory = [];
      localStorage.removeItem('wanfeng_search_hist');
      popup.remove();
    });

    document.body.appendChild(popup);
  },

  /** 滚动到底部附近时自动加载 */
  _onScroll() {
    if (this._loadingMore) return;
    const el = this._scrollEl;
    if (!el) return;
    const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 200;
    if (!nearBottom) return;

    if (this._searchMode) {
      if (this._searchPage < this._searchTotalPages) {
        this._loadMoreSearch();
      }
    } else {
      if (this._listPage < this._totalPages) {
        this._loadMoreHome();
      }
    }
  },

  async _loadData() {
    this._searchMode = false;
    this._listPage = 1;
    this._allVods = [];
    const pageId = this._pageId;
    document.getElementById('search-input').value = '';

    const scroll = this._scrollEl;
    if (!scroll) return;
    scroll.innerHTML = '<div class="loading-placeholder">正在加载首页内容...</div>';

    const [bannersResult, recommendResult, vodResult] = await Promise.all([
      ApiClient.getBanners(),
      ApiClient.getRecommend(1, 10, 'home'),
      ApiClient.getVodList(1, 20, 0, 'vod_time'),
    ]);

    if (this._pageId !== pageId) return;

    scroll.innerHTML = '';

    // Banner
    if (bannersResult.code === 200) {
      const banners = (bannersResult.data && bannersResult.data.list) || [];
      if (banners.length > 0) this._renderBanner(scroll, banners[0]);
    }

    // 推荐
    if (recommendResult.code === 200) {
      const recList = (recommendResult.data && recommendResult.data.list) || [];
      if (recList.length > 0) {
        scroll.appendChild(this._createSection('推荐影视'));
        scroll.appendChild(this._createGrid(recList));
      }
    }

    // 最新影视
    if (vodResult.code === 200) {
      const data = vodResult.data || {};
      const vodList = data.list || [];
      this._allVods = vodList;
      this._totalPages = data.total_pages || 1;
      if (vodList.length > 0) {
        scroll.appendChild(this._createSection('最新影视'));
        scroll.appendChild(this._createGrid(vodList));
        // 如果还有更多，插入加载标记（不显示按钮）
        if (this._totalPages > 1) {
          this._appendLoader(scroll);
        }
      }
    }

    // 检查公告
    App.showAnnouncements();
  },

  /** 首页自动加载更多 */
  async _loadMoreHome() {
    this._loadingMore = true;
    this._listPage++;
    const scroll = this._scrollEl;
    if (!scroll) { this._loadingMore = false; return; }

    // 移除旧 loader，显示加载中
    this._removeLoader();
    this._appendLoader(scroll, true);

    const result = await ApiClient.getVodList(this._listPage, 20, this._typeId, 'vod_time');
    this._removeLoader();

    if (result.code === 200) {
      const vodList = (result.data && result.data.list) || [];
      if (vodList.length > 0) {
        this._allVods = this._allVods.concat(vodList);
        scroll.appendChild(this._createGrid(vodList));
      }
      // 还有更多就再插 loader
      if (this._listPage < (result.data && result.data.total_pages || this._totalPages)) {
        this._appendLoader(scroll);
      }
    }
    this._loadingMore = false;
  },

  /** 搜索自动加载更多 */
  async _loadMoreSearch() {
    this._loadingMore = true;
    this._searchPage++;
    const scroll = this._scrollEl;
    if (!scroll) { this._loadingMore = false; return; }

    this._removeLoader();
    this._appendLoader(scroll, true);

    const result = await ApiClient.search(this._searchKeyword, this._searchPage);
    this._removeLoader();

    if (result.code === 200) {
      const list = (result.data && result.data.list) || [];
      if (list.length > 0) {
        scroll.appendChild(this._createGrid(list));
      }
      this._searchTotalPages = result.data && result.data.total_pages || 0;
      if (this._searchPage < this._searchTotalPages) {
        this._appendLoader(scroll);
      }
    }
    this._loadingMore = false;
  },

  _appendLoader(scroll, active) {
    const el = document.createElement('div');
    el.className = 'scroll-loader';
    el.id = 'scroll-loader';
    if (active) el.classList.add('loading');
    scroll.appendChild(el);
  },

  _removeLoader() {
    const el = document.getElementById('scroll-loader');
    if (el) el.remove();
  },

  _renderBanner(container, banner) {
    const div = document.createElement('div');
    div.className = 'banner';
    if (banner.vod_pic) {
      div.style.backgroundImage = `url(${banner.vod_pic})`;
    } else {
      div.classList.add('fallback');
    }
    const score = banner.vod_score ? '★ ' + banner.vod_score + '分' : '';
    const year = banner.vod_year || '';
    const meta = [score, year].filter(Boolean).join('  ·  ');
    div.innerHTML = `
      <div class="banner-content">
        <h2>${banner.vod_name || ''}</h2>
        ${meta ? `<div class="banner-meta"><span>${meta}</span></div>` : ''}
        <button class="btn-watch" data-vod-id="${banner.vod_id || 0}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg>
          立即观看
        </button>
      </div>
    `;
    div.querySelector('.btn-watch').addEventListener('click', (e) => {
      const id = parseInt(e.currentTarget.dataset.vodId);
      if (id) App.openDetail(id);
    });
    container.appendChild(div);
  },

  async _doSearch() {
    const keyword = document.getElementById('search-input').value.trim();
    if (!keyword) {
      this._loadData();
      return;
    }

    // 保存搜索历史
    this._saveHistory(keyword);

    this._searchMode = true;
    this._searchKeyword = keyword;
    this._searchPage = 1;
    this._searchTotalPages = 0;

    const scroll = this._scrollEl;
    scroll.innerHTML = '<div class="loading-placeholder">搜索中...</div>';

    const result = await ApiClient.search(keyword, 1);
    scroll.innerHTML = '';

    const data = result.data || {};
    const list = data.list || [];
    const total = data.total || 0;
    this._searchTotalPages = data.total_pages || 0;

    scroll.appendChild(this._createSection(`搜索 "${keyword}" 的结果 (共${total}部)`));

    if (list.length > 0) {
      scroll.appendChild(this._createGrid(list));
      if (this._searchPage < this._searchTotalPages) {
        this._appendLoader(scroll);
      }
    } else {
      const empty = document.createElement('div');
      empty.className = 'empty-state';
      empty.textContent = '未找到相关影片';
      scroll.appendChild(empty);
    }
  },

  _createSection(title) {
    const el = document.createElement('div');
    el.className = 'section-title';
    el.textContent = title;
    return el;
  },

  _createGrid(vodList) {
    const grid = document.createElement('div');
    grid.className = 'vod-grid';
    vodList.forEach(vod => grid.appendChild(VodCard.create(vod)));
    return grid;
  },
};
