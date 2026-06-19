/**
 * 晚风影视 - 播放记录页
 * 企业版：缩略图 + 进度条 + 继续播放
 */

const HistoryPage = {
  _records: [],

  render(container, _data) {
    container.innerHTML = `
      <div class="page active" id="history-page">
        <div class="page-topbar">
          <div class="page-title">播放记录</div>
          <div class="spacer"></div>
          <button class="btn btn-danger btn-sm" id="history-clear">清空记录</button>
        </div>
        <div class="page-scroll" id="history-scroll">
          <div class="loading-placeholder">加载中...</div>
        </div>
      </div>
    `;

    document.getElementById('history-clear').addEventListener('click', () => this._clearHistory());
    this._load();
  },

  async _load() {
    const scroll = document.getElementById('history-scroll');

    const result = await ApiClient.getHistory(1, 50);
    scroll.innerHTML = '';

    if (result.code !== 200) {
      scroll.innerHTML = `<div class="empty-state">加载失败: ${result.msg || '未知错误'}</div>`;
      return;
    }

    this._records = (result.data && result.data.list) || [];
    if (this._records.length === 0) {
      scroll.innerHTML = '<div class="empty-state">暂无播放记录</div>';
      return;
    }

    this._records.forEach(rec => {
      scroll.appendChild(this._createItem(rec));
    });
  },

  _createItem(rec) {
    const item = document.createElement('div');
    item.className = 'history-item';

    // 缩略图
    const thumb = document.createElement('div');
    thumb.className = 'history-thumb';
    const name = rec.vod_name || '';
    // 始终先显示占位文字
    thumb.textContent = name.slice(0, 4) || '...';
    if (rec.vod_pic) {
      const img = new Image();
      img.onload = () => { thumb.textContent = ''; thumb.appendChild(img); };
      img.onerror = () => { thumb.textContent = name.slice(0, 4) || '...'; };
      img.src = rec.vod_pic;
    }
    item.appendChild(thumb);

    // 信息
    const info = document.createElement('div');
    info.className = 'history-info';

    const title = document.createElement('div');
    title.className = 'hi-title';
    title.textContent = name;
    info.appendChild(title);

    const ep = document.createElement('div');
    ep.className = 'hi-ep';
    ep.textContent = rec.episode_name || `第${rec.episode_index || 1}集`;
    info.appendChild(ep);

    // 进度条
    const pos = rec.play_position || 0;
    const dur = rec.duration || 0;
    if (dur > 0) {
      const progBar = document.createElement('div');
      progBar.className = 'history-progress-bar';
      const fill = document.createElement('div');
      fill.className = 'hi-progress-fill';
      fill.style.width = Math.min(100, Math.round(pos * 100 / dur)) + '%';
      progBar.appendChild(fill);
      info.appendChild(progBar);
    }

    item.appendChild(info);

    // 时间
    const timeLabel = document.createElement('div');
    timeLabel.className = 'history-time';
    timeLabel.textContent = rec.updated_at || '';
    item.appendChild(timeLabel);

    // 继续播放
    const playBtn = document.createElement('button');
    playBtn.className = 'btn btn-sm history-play-btn';
    playBtn.textContent = '继续播放';
    playBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      this._continuePlay(rec);
    });
    item.appendChild(playBtn);

    item.addEventListener('click', () => {
      if (rec.vod_id) App.openDetail(rec.vod_id);
    });

    return item;
  },

  async _continuePlay(rec) {
    const result = await ApiClient.play(
      rec.vod_id, rec.episode_index || 1, rec.parse_api_id || 0, 0
    );

    if (result.code !== 200) {
      App.toast(result.msg || '无法获取播放地址', 'error');
      return;
    }

    const d = result.data;
    App.openPlayer({
      vod_id: d.vod_id,
      episode_index: d.episode_index,
      video_url: d.video_url,
      video_type: d.type || 'hls',
      vod_name: d.vod_name,
      episode_name: d.episode_name,
      parse_api_id: d.parse_api_id,
      parse_api_name: d.parse_api_name,
      vod_pic: rec.vod_pic || '',
      resume_position: rec.play_position || 0,
      episodes_all: [],
    });
  },

  async _clearHistory() {
    const ok = await App.showConfirm('确认', '确定要清空所有播放记录吗？');
    if (!ok) return;

    for (const rec of this._records) {
      if (rec.id) await ApiClient.deleteHistory(rec.id);
    }

    this._records = [];
    document.getElementById('history-scroll').innerHTML =
      '<div class="empty-state">播放记录已清空</div>';
  },
};
