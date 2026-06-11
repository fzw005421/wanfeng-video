/**
 * 晚风影视 - 我的收藏页
 * 对照 python/client/ui/favorites_page.py
 */

const FavoritesPage = {

  render(container, _data) {
    container.innerHTML = `
      <div class="page active" id="favorites-page">
        <div class="page-topbar">
          <div class="page-title">我的收藏</div>
          <div class="spacer"></div>
        </div>
        <div class="page-scroll" id="fav-scroll">
          <div class="loading-placeholder">加载中...</div>
        </div>
      </div>
    `;

    this._load();
  },

  async _load() {
    const scroll = document.getElementById('fav-scroll');

    const result = await ApiClient.getFavorites(1, 50);
    scroll.innerHTML = '';

    if (result.code !== 200) {
      scroll.innerHTML = `<div class="empty-state">加载失败: ${result.msg || '未知错误'}</div>`;
      return;
    }

    const favList = (result.data && result.data.list) || [];
    if (favList.length === 0) {
      scroll.innerHTML = '<div class="empty-state">暂无收藏</div>';
      return;
    }

    const grid = document.createElement('div');
    grid.className = 'vod-grid';
    favList.forEach(fav => grid.appendChild(VodCard.create(fav)));
    scroll.appendChild(grid);
  },
};
