/**
 * 晚风影视 - 视频播放器组件
 * 基于 hls.js + 原生 video 元素 + 自定义控件
 */
const VideoPlayer = {
  _hls: null,
  _video: null,
  _container: null,
  _wrapper: null,
  _url: '',
  _onError: null,
  _onReady: null,
  _hideTimer: null,
  _controlsEl: null,
  _progressEl: null,
  _playedEl: null,
  _bufferedEl: null,
  _thumbEl: null,
  _timeEl: null,
  _playBtn: null,
  _centerPlayEl: null,
  _volumeBtn: null,
  _volumeSlider: null,
  _speedSelect: null,
  _fullscreenBtn: null,
  _lastVolume: 1,
  _dragging: false,

  /* ======================== 挂载 ======================== */

  mount(container, callbacks = {}) {
    this._container = container;
    this._onError = callbacks.onError || (() => {});
    this._onReady = callbacks.onReady || (() => {});
    this._onEnded = callbacks.onEnded || (() => {});
    container.innerHTML = '';
  },

  /* ======================== 播放控制 ======================== */

  play(url, videoType) {
    if (!url) { this._onError('播放地址为空'); return; }
    this._url = url;
    this.stop();

    const el = this._container;
    if (!el) { this._onError('播放器容器不存在'); return; }

    // 构建播放器 DOM
    el.innerHTML = `
      <div class="wf-player-wrapper">
        <video class="wf-video"></video>
        <div class="wf-center-play">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </div>
        <div class="wf-controls">
          <div class="wf-progress-bar">
            <div class="wf-progress-track">
              <div class="wf-progress-buffered"></div>
              <div class="wf-progress-played"></div>
              <div class="wf-progress-thumb"></div>
            </div>
          </div>
          <div class="wf-controls-row">
            <button class="wf-btn wf-btn-play" title="播放/暂停">
              <svg class="wf-icon-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
              <svg class="wf-icon-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </button>
            <span class="wf-time">00:00 / 00:00</span>
            <div class="wf-spacer"></div>
            <div class="wf-volume-wrap">
              <button class="wf-btn wf-btn-volume" title="音量">
                <svg class="wf-icon-vol-on" viewBox="0 0 24 24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/></svg>
                <svg class="wf-icon-vol-off" viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
              </button>
              <div class="wf-volume-slider-wrap">
                <input type="range" class="wf-volume-slider" min="0" max="100" value="100">
              </div>
            </div>
            <select class="wf-speed-select">
              <option value="0.5">0.5x</option>
              <option value="0.75">0.75x</option>
              <option value="1" selected>1.0x</option>
              <option value="1.25">1.25x</option>
              <option value="1.5">1.5x</option>
              <option value="2">2.0x</option>
            </select>
            <button class="wf-btn wf-btn-fullscreen" title="全屏">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
            </button>
          </div>
        </div>
      </div>
    `;

    // 缓存 DOM 引用
    this._wrapper = el.querySelector('.wf-player-wrapper');
    this._video = el.querySelector('.wf-video');
    this._controlsEl = el.querySelector('.wf-controls');
    this._progressEl = el.querySelector('.wf-progress-bar');
    this._playedEl = el.querySelector('.wf-progress-played');
    this._bufferedEl = el.querySelector('.wf-progress-buffered');
    this._thumbEl = el.querySelector('.wf-progress-thumb');
    this._timeEl = el.querySelector('.wf-time');
    this._playBtn = el.querySelector('.wf-btn-play');
    this._centerPlayEl = el.querySelector('.wf-center-play');
    this._volumeBtn = el.querySelector('.wf-btn-volume');
    this._volumeSlider = el.querySelector('.wf-volume-slider');
    this._speedSelect = el.querySelector('.wf-speed-select');
    this._fullscreenBtn = el.querySelector('.wf-btn-fullscreen');

    const video = this._video;
    video.volume = 1;
    video.playsInline = true;

    // 判断是否需要 hls.js（m3u8 / hls）
    const typeHint = (videoType || '').toLowerCase();
    const isHLS = typeHint === 'hls' || typeHint === 'm3u8' || /\.m3u8/i.test(url);

    if (isHLS && typeof Hls !== 'undefined') {
      this._initHLS(url);
    } else {
      video.src = url;
      video.load();
    }

    this._bindEvents();
    this._startHideTimer();
    this._syncPlayBtn();
    this._syncVolumeIcon();
  },

  _initHLS(url) {
    const video = this._video;

    this._hls = new Hls({
      enableWorker: false,
      lowLatencyMode: false,
      // 画质优化：匹配播放器尺寸 + 检测掉帧自动降级
      capLevelToPlayerSize: true,
      capLevelOnFPSDrop: true,
      startLevel: -1,              // 自动选择最高可用码率
      // 缓冲调优：更大缓冲 → 更少卡顿
      backBufferLength: 90,
      maxBufferLength: 60,
      maxMaxBufferLength: 600,
      maxBufferSize: 60 * 1000 * 1000,  // 60MB
      maxBufferHole: 0.5,
      // 加载重试
      maxLoadingDelay: 4,
      maxLoadingRetryDelay: 4,
      manifestLoadingTimeOut: 10000,
      manifestLoadingMaxRetry: 3,
      levelLoadingTimeOut: 10000,
      levelLoadingMaxRetry: 4,
      fragLoadingTimeOut: 20000,
      fragLoadingMaxRetry: 6,
      // ABR 自适应码率 — 响应更快的带宽估算
      abrEwmaFastLive: 3,
      abrEwmaSlowVoD: 9,
      abrBandWidthFactor: 0.95,
      abrBandWidthUpFactor: 0.7,
      abrMaxWithRealBitrate: true,
      // 平滑切换码率
      smoothSwitch: true,
      stretchShortVideoTrack: true,
      maxFragLookUpTolerance: 0.25,
    });

    this._hls.loadSource(url);
    this._hls.attachMedia(video);

    this._hls.on(Hls.Events.MANIFEST_PARSED, () => {
      if (this._onReady) this._onReady();
      video.play().catch(() => {});
    });

    this._hls.on(Hls.Events.ERROR, (_event, data) => {
      if (data.fatal) {
        const msg = data.type === Hls.ErrorTypes.NETWORK_ERROR
          ? '网络错误，请检查网络或切换解析接口'
          : data.type === Hls.ErrorTypes.MEDIA_ERROR
            ? '媒体解析失败，请尝试切换解析接口'
            : '视频播放出错，请尝试切换解析接口';
        this._onError(msg);
        this._hls.destroy();
        this._hls = null;
      }
    });
  },

  /* ======================== 事件绑定 ======================== */

  _bindEvents() {
    const video = this._video;

    // 视频事件
    video.addEventListener('loadedmetadata', () => {
      this._updateTime();
      if (!this._hls && this._onReady) this._onReady();
    });

    video.addEventListener('play', () => this._syncPlayBtn());
    video.addEventListener('pause', () => this._syncPlayBtn());
    video.addEventListener('ended', () => {
      this._syncPlayBtn();
      this._centerPlayEl.classList.remove('hidden');
      this._onEnded();
    });
    video.addEventListener('timeupdate', () => { if (!this._dragging) this._updateProgress(); });
    video.addEventListener('waiting', () => {});
    video.addEventListener('canplay', () => { this._centerPlayEl.classList.add('hidden'); });

    video.addEventListener('click', () => {
      if (video.paused) { video.play().catch(() => {}); }
      else { video.pause(); }
    });

    // 中心播放按钮
    this._centerPlayEl.addEventListener('click', (e) => {
      e.stopPropagation();
      video.play().catch(() => {});
    });

    // 播放/暂停按钮
    this._playBtn.addEventListener('click', () => {
      if (video.paused) { video.play().catch(() => {}); }
      else { video.pause(); }
    });

    // 进度条拖拽
    this._onDrag = (e) => {
      if (this._dragging) this._seekTo(e);
    };
    this._onDragEnd = () => {
      this._dragging = false;
      document.removeEventListener('mousemove', this._onDrag);
      document.removeEventListener('mouseup', this._onDragEnd);
    };
    this._progressEl.addEventListener('mousedown', (e) => {
      this._dragging = true;
      this._seekTo(e);
      document.addEventListener('mousemove', this._onDrag);
      document.addEventListener('mouseup', this._onDragEnd);
    });
    // 安全网：鼠标在进度条外松开也重置
    document.addEventListener('mouseup', () => {
      if (this._dragging) {
        this._dragging = false;
        document.removeEventListener('mousemove', this._onDrag);
        document.removeEventListener('mouseup', this._onDragEnd);
      }
    });

    // 音量
    this._volumeBtn.addEventListener('click', () => {
      if (video.volume > 0) { this._lastVolume = video.volume; video.volume = 0; }
      else { video.volume = this._lastVolume || 1; }
      this._volumeSlider.value = video.volume * 100;
      this._syncVolumeIcon();
    });
    this._volumeSlider.addEventListener('input', () => {
      video.volume = this._volumeSlider.value / 100;
      this._lastVolume = video.volume;
      this._syncVolumeIcon();
    });

    // 倍速
    this._speedSelect.addEventListener('change', () => {
      video.playbackRate = parseFloat(this._speedSelect.value);
    });

    // 全屏
    this._fullscreenBtn.addEventListener('click', () => {
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else {
        this._wrapper.requestFullscreen();
      }
    });

    // 键盘快捷键
    this._onKeyDown = (e) => {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
      switch (e.key) {
        case ' ': case 'k': e.preventDefault(); (video.paused ? video.play() : video.pause()); break;
        case 'ArrowLeft': e.preventDefault(); video.currentTime = Math.max(0, video.currentTime - 5); break;
        case 'ArrowRight': e.preventDefault(); video.currentTime = Math.min(video.duration || Infinity, video.currentTime + 5); break;
        case 'ArrowUp': e.preventDefault(); video.volume = Math.min(1, video.volume + 0.1); this._volumeSlider.value = video.volume * 100; this._syncVolumeIcon(); break;
        case 'ArrowDown': e.preventDefault(); video.volume = Math.max(0, video.volume - 0.1); this._volumeSlider.value = video.volume * 100; this._syncVolumeIcon(); break;
        case 'f': e.preventDefault(); this._fullscreenBtn.click(); break;
        case 'm': e.preventDefault(); this._volumeBtn.click(); break;
      }
    };
    document.addEventListener('keydown', this._onKeyDown);

    // 鼠标移动 → 显示控件 + 恢复光标
    this._cursorTimer = null;
    this._onWrapperMouseMove = () => {
      this._showControls();
      this._startHideTimer();
      // 全屏下：恢复光标并重置 3s 隐藏定时器
      if (this._wrapper) {
        this._wrapper.style.cursor = '';
        clearTimeout(this._cursorTimer);
        if (document.fullscreenElement && this._video && !this._video.paused) {
          this._cursorTimer = setTimeout(() => {
            if (this._wrapper && document.fullscreenElement) {
              this._wrapper.style.cursor = 'none';
            }
          }, 3000);
        }
      }
    };
    this._wrapper.addEventListener('mousemove', this._onWrapperMouseMove);
    this._wrapper.addEventListener('mouseleave', () => {
      if (!this._video.paused) this._hideControls();
    });

    // 退出全屏时恢复光标
    this._onFullscreenChange = () => {
      if (!document.fullscreenElement) {
        if (this._wrapper) this._wrapper.style.cursor = '';
        clearTimeout(this._cursorTimer);
      }
    };
    document.addEventListener('fullscreenchange', this._onFullscreenChange);
  },

  /* ======================== 进度控制 ======================== */

  _seekTo(e) {
    const rect = this._progressEl.getBoundingClientRect();
    const ratio = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    if (this._video.duration) {
      this._video.currentTime = ratio * this._video.duration;
    }
    this._updateProgress(ratio);
  },

  _updateProgress(forcedRatio) {
    const video = this._video;
    if (!video.duration) return;
    const ratio = forcedRatio !== undefined ? forcedRatio : (video.currentTime / video.duration);
    const pct = (ratio * 100).toFixed(2) + '%';
    this._playedEl.style.width = pct;
    this._thumbEl.style.left = pct;

    // 缓冲进度
    if (video.buffered.length > 0) {
      const buffered = video.buffered.end(video.buffered.length - 1);
      this._bufferedEl.style.width = ((buffered / video.duration) * 100).toFixed(2) + '%';
    }

    this._updateTime();
  },

  _updateTime() {
    const video = this._video;
    const current = video.currentTime || 0;
    const total = video.duration || 0;
    this._timeEl.textContent = fmtTime(current) + ' / ' + (total ? fmtTime(total) : '00:00');
  },

  /* ======================== 控件显隐 ======================== */

  _showControls() {
    this._controlsEl.classList.remove('wf-hidden');
    this._centerPlayEl.classList.remove('wf-hidden');
    if (this._video && this._video.paused) {
      this._centerPlayEl.classList.remove('hidden');
    }
  },

  _hideControls() {
    if (this._video && this._video.paused) return;
    this._controlsEl.classList.add('wf-hidden');
    this._centerPlayEl.classList.add('wf-hidden');
  },

  _startHideTimer() {
    clearTimeout(this._hideTimer);
    this._hideTimer = setTimeout(() => this._hideControls(), 3000);
  },

  _syncPlayBtn() {
    if (!this._video) return;
    const paused = this._video.paused;
    this._playBtn.classList.toggle('wf-paused', paused);
    if (paused) {
      this._centerPlayEl.classList.remove('hidden');
    } else {
      this._centerPlayEl.classList.add('hidden');
    }
  },

  _syncVolumeIcon() {
    if (!this._video) return;
    const v = this._video.volume;
    this._volumeBtn.classList.toggle('wf-muted', v === 0);
  },

  /* ======================== API ======================== */

  stop() {
    clearTimeout(this._hideTimer);
    clearTimeout(this._cursorTimer);
    document.removeEventListener('mousemove', this._onDrag);
    document.removeEventListener('mouseup', this._onDragEnd);
    document.removeEventListener('keydown', this._onKeyDown);
    document.removeEventListener('fullscreenchange', this._onFullscreenChange);

    if (this._hls) {
      try { this._hls.destroy(); } catch (e) { /* ignore */ }
      this._hls = null;
    }
    if (this._video) {
      try {
        this._video.pause();
        this._video.removeAttribute('src');
        this._video.load();
      } catch (e) { /* ignore */ }
      this._video = null;
    }
    if (this._container) {
      this._container.innerHTML = '';
    }
    this._wrapper = null;
    this._controlsEl = null;
  },

  pause() {
    if (this._video) { try { this._video.pause(); } catch (e) { /* ignore */ } }
  },

  resume() {
    if (this._video) { try { this._video.play(); } catch (e) { /* ignore */ } }
  },

  getCurrentTime() {
    return this._video ? (this._video.currentTime || 0) : 0;
  },

  getDuration() {
    return this._video ? (this._video.duration || 0) : 0;
  },

  seek(time) {
    if (this._video) { this._video.currentTime = time; }
  },

  isPaused() {
    return this._video ? this._video.paused : true;
  },
};

/* ======================== 工具函数 ======================== */

function fmtTime(seconds) {
  if (!isFinite(seconds) || seconds < 0) return '00:00';
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = Math.floor(seconds % 60);
  const pad = (n) => String(n).padStart(2, '0');
  if (h > 0) return pad(h) + ':' + pad(m) + ':' + pad(s);
  return pad(m) + ':' + pad(s);
}
