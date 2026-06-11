/**
 * 晚风影视 - 影视卡片组件
 * 企业版：海报渐变遮罩 + 评分徽章 + 年份标签
 */

const VodCard = {
  create(vod) {
    const card = document.createElement('div');
    card.className = 'vod-card';
    card.dataset.vodId = vod.vod_id || 0;

    // 封面区
    const poster = document.createElement('div');
    poster.className = 'vod-card-poster';

    // 占位文字（图片加载前显示）
    const name = vod.vod_name || '';
    const placeholder = document.createElement('span');
    placeholder.className = 'vod-card-placeholder';
    placeholder.textContent = name.slice(0, 4) || '...';
    poster.appendChild(placeholder);

    // 评分徽章
    if (vod.vod_score && vod.vod_score !== '0.0' && vod.vod_score !== 0) {
      const rating = document.createElement('div');
      rating.className = 'vod-card-rating star';
      rating.textContent = '★ ' + parseFloat(vod.vod_score).toFixed(1);
      poster.appendChild(rating);
    }

    // 异步加载封面图
    if (vod.vod_pic) {
      this._loadPoster(vod.vod_pic, poster, placeholder);
    }

    card.appendChild(poster);

    // 信息区
    const info = document.createElement('div');
    info.className = 'vod-card-info';

    const title = document.createElement('div');
    title.className = 'vod-card-title';
    title.textContent = name.length > 12 ? name.slice(0, 12) + '…' : name;
    title.title = name;
    info.appendChild(title);

    // 年份·类型·地区 标签行
    const metaRow = document.createElement('div');
    metaRow.className = 'vod-card-meta-row';
    const metaParts = [];
    if (vod.vod_year) metaParts.push(vod.vod_year);
    if (vod.vod_area) metaParts.push(vod.vod_area);
    if (vod.vod_remarks) metaParts.push(vod.vod_remarks);
    if (metaParts.length === 0 && vod.type_name) metaParts.push(vod.type_name);
    metaRow.innerHTML = metaParts.map(t => `<span>${t}</span>`).join('<span>·</span>');
    info.appendChild(metaRow);

    card.appendChild(info);

    card.addEventListener('click', () => {
      App.openDetail(vod.vod_id);
    });

    return card;
  },

  _loadPoster(url, posterEl, placeholderEl) {
    if (!url) return;
    const img = new Image();
    img.onload = () => {
      if (placeholderEl) placeholderEl.remove();
      posterEl.appendChild(img);
    };
    img.onerror = () => { /* 保留占位 */ };
    img.src = url;
  },
};
