# 晚风影视

Windows 桌面端视频播放器，对接苹果 CMS 资源站，支持 m3u8/HLS 流媒体播放（H.264 + H.265/HEVC）。


## 功能

- 首页推荐轮播 + 影视列表无限滚动
- 影片搜索
- 影片详情页：海报、简介、演员、评分
- **多播放源切换**：一部影片有多个资源站来源时，可切换播放源再选集
- **多解析接口切换**：播放页可随时切换解析接口，自动恢复当前进度
- 播放进度记录 + 断点续播
- 收藏管理（离线快照，不依赖 CMS 库）
- 播放历史
- 系统公告
- 明暗双主题

- <img width="1280" height="820" alt="de8ab188bb6cc0ca0c4f363a324697d0" src="https://github.com/user-attachments/assets/727fe01d-2e50-4ca0-b8f4-929dc9145e96" />



## 结构

```
├── js/                     # Electron 桌面客户端
│   ├── main/               # Electron 主进程
│   │   ├── main.js
│   │   └── preload.js
│   ├── renderer/           # 前端 UI（纯浏览器环境，无 Node 权限）
│   │   ├── index.html
│   │   ├── js/
│   │   │   ├── api.js              # HTTP 客户端（请求封装、重试、取消）
│   │   │   ├── auth.js             # Token / 用户信息持久化
│   │   │   ├── app.js              # 路由、Toast、公告弹窗、主题切换
│   │   │   ├── components/
│   │   │   │   ├── player.js       # hls.js 播放器 + 自定义控制栏
│   │   │   │   └── vod-card.js     # 影视卡片组件
│   │   │   └── pages/
│   │   │       ├── home.js         # 首页
│   │   │       ├── detail.js       # 详情页
│   │   │       ├── player.js       # 播放页
│   │   │       ├── login.js        # 登录/注册
│   │   │       ├── favorites.js    # 收藏
│   │   │       ├── history.js      # 历史
│   │   │       └── settings.js     # 设置
│   │   ├── css/
│   │   │   └── style.css
│   │   └── vendor/
│   │       └── hls.min.js          # hls.js v1.5.17
│   ├── ffmpeg/
│   │   └── ffmpeg.dll              # Chromium HEVC 解码支持
│   ├── package.json
│   └── icon.ico
│
└── web/                    # PHP 后端
    ├── api/                # API 接口
    │   ├── index.php       # 路由入口
    │   ├── vod.php         # 影视数据（CMS 只读查询 + 多播放源解析）
    │   ├── play.php        # 播放地址解析
    │   ├── login.php       # 登录
    │   ├── register.php    # 注册
    │   ├── user.php        # 用户信息
    │   ├── favorites.php   # 收藏
    │   ├── history.php     # 播放记录
    │   ├── announcements.php # 公告
    │   ├── settings.php    # 系统设置
    │   └── parse.php       # 解析接口管理
    ├── admin/              # 后台管理面板
    ├── install/            # Web 安装向导
    ├── includes/
    │   ├── init.php        # 引导（时区、错误报告、限流）
    │   ├── db.php          # PDO 连接池、分页、自动重连
    │   └── functions.php   # Token、密码、设置、限流
    └── config/
        └── database.php    # 数据库配置（安装向导自动生成）
```

## 技术栈

| 层 | 技术 |
|---|---|
| 桌面框架 | Electron 33 |
| 前端 | 原生 JS（无框架），SPA 路由 |
| 播放器 | hls.js 1.5（JS 层 transmux，绕过 Chromium MSE 限制） |
| 后端 | PHP 7.4+，无框架 |
| 数据库 | MySQL 5.7+ / 8.0（utf8mb4） |
| 视频来源 | 苹果 CMS V10（`mac_vod` 表只读） |

## 环境要求

### 后端

- PHP 7.4 或更高（推荐 PHP 8.0）
- MySQL 5.7 或更高
- 需要 `curl`、`pdo_mysql`、`mbstring`、`json` 扩展
- 网站根目录指向 `web/`

### 前端编译

- Node.js 18+
- NSIS 3.x（生成安装包需要，解压版不需要）
- Windows 系统（Electron 编译目标为 win32 x64）

---

## 后端部署（宝塔面板）

### 1. 新建站点

宝塔面板 → 网站 → 添加站点：

- 域名：填写你的域名或 IP
- 根目录：选择 `web/` 目录
- PHP 版本：**PHP 8.0**（7.4 也可以）
- 数据库：先不创建，后面用安装向导自动建

### 2. PHP 配置

宝塔 → 软件商店 → PHP 8.0 → 设置：

**安装扩展**（如果没有）：
- `fileinfo`（一般默认有）
- 确保 `curl`、`pdo_mysql`、`mbstring`、`json`、`openssl` 已开启

**性能调整**（可选，按服务器配置来）：

```
max_execution_time = 300
memory_limit = 256M
```

### 3. 伪静态

宝塔 → 网站设置 → 伪静态，粘贴：

```nginx
location / {
    try_files $uri $uri/ /api/index.php?$query_string;
}
```

如果用 Apache，项目里的 `.htaccess` 已自带规则，确保 Apache 开启了 `mod_rewrite`。

### 4. 安装向导

浏览器访问 `http://你的域名/install/`，按步骤填写：

**步骤 1**：检查环境（PHP 版本、扩展是否齐全）

**步骤 2**：填写两个数据库信息

| | 自有数据库 | 苹果 CMS 数据库 |
|---|---|---|
| 用途 | 用户、收藏、历史、设置 | 视频元数据（只读） |
| 主机 | localhost | CMS 数据库地址 |
| 库名 | 新建一个，如 `wanfeng` | CMS 的库名，如 `maccms` |
| 账号 | 有建库权限的账号 | 有只读权限即可 |

**步骤 3**：设置管理员账号

安装完成后，访问 `http://你的域名/admin/` 进入后台。

### 5. 后台管理

后台可操作的内容：

- **影视推荐**：添加首页推荐位和 Banner（从 CMS 中选择影片）
- **解析接口**：管理 m3u8 解析 API，支持 `{url}` 占位符
- **系统公告**：发布公告，客户端登录后弹出，用户可选"不再提示"
- **用户管理**：查看注册用户
- **系统设置**：站点名称等

### 6. 解析接口说明

解析接口用于把苹果 CMS 的原始播放地址转换成 m3u8。接口 URL 中用 `{url}` 代表原始地址，例如：

```
https://api.example.com/parse?url={url}
```

播放时系统会自动替换 `{url}` 为编码后的原始播放地址，curl 拿到 m3u8 地址后传给前端 hls.js 播放。

---

## 前端编译

### 1. 配置后端地址

修改两个文件的默认地址：

**`js/renderer/js/api.js`** 第 9 行：

```js
_baseUrl: 'http://你的服务器IP:端口',
```

**`js/renderer/js/auth.js`** 第 54 行：

```js
return this.get('server_url', 'http://你的服务器IP:端口');
```

> 注意：客户端安装后第一次登录时，前端会把登录时使用的地址存到 localStorage，后续启动优先用 localStorage 的地址。上面两个文件改的是**出厂默认值**。

### 2. 安装 Node.js 依赖

```bash
cd js
npm install
```

> 如果 Electron 下载慢，设置国内镜像：
> ```bash
> export ELECTRON_MIRROR=https://npmmirror.com/mirrors/electron/
> npm install
> ```

### 3. 关于 ffmpeg.dll

`js/ffmpeg/ffmpeg.dll` 是 Chromium 的 HEVC/H.265 解码支持库。如果删掉它，HEVC 视频可能无法解码（表现为有声音没画面）。

这个文件来源于 [nwjs-ffmpeg-prebuilt](https://github.com/nwjs-ffmpeg-prebuilt/nwjs-ffmpeg-prebuilt)，需要与 Electron 的 Chromium 版本匹配。当前项目用的是 Electron 33（Chromium 130），对应 ffmpeg 0.93.0。

如果升级 Electron，需要同步更换 ffmpeg.dll，否则 HEVC 解码失效。

### 4. 编译

**不打包直接运行（开发调试）：**

```bash
npm start
```

**编译解压版（不生成安装包）：**

```bash
npm run build:dir
```

输出在 `js/dist/win-unpacked/`。

**编译 NSIS 安装包：**

```bash
npm run build
```

输出在 `js/dist/晚风影视 Setup 1.0.0.exe`。

编译前确保 NSIS 已安装，默认路径是 `C:\Program Files (x86)\NSIS`。如果不在 PATH 里，手动加一下：

```bash
export PATH="/c/Program Files (x86)/NSIS:$PATH"
```

### 5. 安装包说明

- 安装包约 83MB
- 包含 ffmpeg.dll（HEVC 解码）
- 支持自定义安装目录
- 自动创建桌面快捷方式和开始菜单项
- 未签名，Windows 可能会弹出 SmartScreen 警告

---

## 播放流程

```
用户点击剧集
  → 前端请求 /api/play（vod_id + episode_index + source_index + parse_api_id）
  → 后端查 CMS mac_vod.vod_play_url，按 $$$ / # / $ 拆分出对应剧集的原始地址
  → 后端拿原始地址去调解析接口（curl），拿到 m3u8 地址
  → 保存播放记录到 wf_play_history
  → 返回 m3u8 URL 给前端
  → 前端 hls.js 加载 m3u8 播放
```

### 多播放源机制

苹果 CMS 的 `vod_play_url` 字段支持多个播放源，用 `$$$` 分隔：

```
源1剧集1$URL1#源1剧集2$URL2$$$源2剧集1$URL1#源2剧集2$URL2
```

`vod_server` 字段存源名称（同样 `$$$` 分隔）。详情页会自动展示源标签切换。

### 视频格式支持

| 编码 | 支持 |
|------|------|
| H.264 / AVC | ✅ |
| H.265 / HEVC | ✅（需 ffmpeg.dll） |
| m3u8 / HLS | ✅ |
| MP4 | ✅（直接走 video 标签） |

---

## API 接口一览

| 路径 | 方法 | 说明 | 认证 |
|------|------|------|------|
| `/api/login` | POST | 登录 | - |
| `/api/register` | POST | 注册 | - |
| `/api/vod/list` | GET | 影视列表 | - |
| `/api/vod/search` | GET | 搜索 | - |
| `/api/vod/recommend` | GET | 推荐/Banner | - |
| `/api/vod/{id}` | GET | 影片详情（含多源剧集） | - |
| `/api/play` | POST | 获取播放地址 | Token |
| `/api/parse-apis` | GET | 解析接口列表 | Token |
| `/api/user/profile` | GET/POST | 用户信息 | Token |
| `/api/history/list` | GET | 播放历史 | Token |
| `/api/history/save` | POST | 保存进度 | Token |
| `/api/history/{id}` | DELETE | 删除历史 | Token |
| `/api/favorites/list` | GET | 收藏列表 | Token |
| `/api/favorites/check` | GET | 检查收藏状态 | Token |
| `/api/favorite/toggle` | POST | 切换收藏 | Token |
| `/api/announcements` | GET | 公告列表 | Token |
| `/api/settings` | GET | 系统设置 | - |

---

## 数据库表

自有数据库（`wf_` 前缀）：

| 表 | 说明 |
|---|---|
| `wf_users` | 用户表（bcrypt 密码） |
| `wf_admins` | 管理员表 |
| `wf_play_history` | 播放记录（user_id + vod_id 唯一） |
| `wf_favorites` | 收藏（含影片元数据快照） |
| `wf_parse_apis` | 解析接口 |
| `wf_announcements` | 系统公告 |
| `wf_recommendations` | 推荐位/Banner |
| `wf_settings` | 系统设置（KV） |

---

## 常见问题

**Q: 播放提示"网络错误"？**

检查解析接口是否正常。可以用 curl 直接测试解析接口地址能否返回 m3u8。也可以尝试切换到其他解析接口（播放页顶部下拉框）。

**Q: HEVC 视频有声音没画面？**

ffmpeg.dll 没有正确加载。检查安装目录下是否有 `ffmpeg.dll` 文件。如果升级了 Electron 版本，需要同步更换匹配的 ffmpeg.dll。

**Q: 搜索框点击不弹历史记录？**

搜索过一次之后才会有记录。历史保存在 localStorage，清除应用数据会丢失。

**Q: macOS 能用吗？**

目前只支持 Windows。Electron 本身跨平台，但编译配置只写了 win x64。要支持 macOS 需要在 `package.json` 的 `build` 里加 mac target，并搞定 ffmpeg 的 macOS 版本（没有 HEVC 硬解问题通常不需要额外 ffmpeg）。

## 许可证

MIT
