# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

晚风影视 (WanFeng Video) — a video streaming platform with two components:
- **`js/`** — Electron desktop app (Windows) with hls.js player
- **`web/`** — PHP backend API + admin panel + web installer

The backend connects to **two MySQL databases**: an "own" database (read/write) for users, favorites, history, settings, and an "Apple CMS" database (read-only) for video metadata (`mac_vod` table).

## Common Commands

### Electron Desktop App (`js/`)

```bash
cd js
export PATH="/c/Program Files/nodejs:$PATH"

# Development
npm start                    # Launch Electron app

# Build Windows installer (NSIS)
npm run build                # Full build → dist/  (requires NSIS installed)
npm run build:dir            # Unpacked build only (skip installer)

# Set China mirror if Electron download fails
export ELECTRON_MIRROR=https://npmmirror.com/mirrors/electron/
```

The built installer outputs to `js/dist/晚风影视 Setup 1.0.0.exe`.

### PHP Backend (`web/`)

No build step. Deploy the `web/` directory to a PHP 7.4+ server with MySQL.

- Installer: visit `http://<server>/install/` — walks through DB config, creates tables, sets admin account
- Admin panel: `http://<server>/admin/` (redirects here after install)
- API base: `http://<server>/api/<endpoint>`
- The `web/config/database.php` uses `{DB_HOST}`, `{DB_NAME}` etc. as template placeholders — the installer replaces them. To reset to uninstalled state, restore those placeholders.
- `web/install/migrate_favorites.php` — one-shot script to add metadata columns to `wf_favorites` (run via browser after deploying updated code)

## Architecture

### Desktop App: Process Model

```
main/main.js          → Electron main process (Node.js)
  └─ main/preload.js  → contextBridge exposes window.electronAPI (minimize/maximize/close)
       └─ renderer/   → pure browser context, no Node access
            ├─ index.html    → SPA shell: custom titlebar + sidebar + <main id="content">
            ├─ js/app.js     → SPA router (_showPage), global state, toast/confirm modals
            ├─ js/api.js     → HTTP client (fetch wrapper with timeout, Bearer token, error handling)
            ├─ js/auth.js    → localStorage wrapper, token/user persistence, offline history
            ├─ js/components/player.js → hls.js video player with full custom control bar
            └─ js/pages/*.js → page modules (login, home, detail, player, favorites, history, settings)
```

**Key points:**
- Frameless window (`frame: false`), custom titlebar in renderer
- `Menu.setApplicationMenu(null)` removes default Electron menu
- `shell.writeShortcutLink` creates desktop shortcut on first launch (flag file in `userData`)
- CSP in HTML meta tag allows `https://cdn.jsdelivr.net` (hls.js CDN), `https:`/`http:` for media/connect
- Window title set via `page-title-updated` event prevention + `window:setTitle` IPC — server URL is **never** shown in titlebar

### Backend: Request Flow

```
web/api/index.php          → API router (parses /api/<route>/<sub> from REQUEST_URI)
  └─ web/includes/init.php → bootstrap: loads config/database.php, db.php, functions.php
       ├─ db.php           → DB layer: getDbConnection() (own), getCmsDbConnection() (CMS read-only)
       └─ functions.php    → jsonResponse(), token auth (generateToken/verifyToken/getCurrentUserId), settings helpers
```

**API routing** in `api/index.php`: a `switch` on the first URI segment loads the corresponding handler file and calls `handle<Name>($method, $segments, $params)`. All errors are caught by a global `try/catch` that returns generic 500 JSON.

### Auth System

- Token format: `base64(json_payload + '.' + md5(json_payload + 'wanfeng_video_salt_2024'))`
- Payload: `{uid, exp (30 days), iat, nonce}`
- Client sends `Authorization: Bearer <token>` header
- `getCurrentUserId()` tries multiple ways to read the header (getallheaders, $_SERVER, apache_request_headers)
- Admin auth uses PHP sessions (`$_SESSION['admin_id']`) — checked in `admin/includes.php`

### Database Tables (own DB, prefix `wf_`)

| Table | Purpose |
|-------|---------|
| `wf_users` | User accounts (bcrypt passwords) |
| `wf_admins` | Admin accounts (bcrypt, role: admin/super_admin) |
| `wf_play_history` | Play records (one per user+vod, updated on episode switch) |
| `wf_favorites` | User favorites with metadata snapshots (vod_name, vod_pic, etc.) |
| `wf_parse_apis` | Video parse/resolver API URLs with `{url}` template |
| `wf_announcements` | System announcements |
| `wf_recommendations` | Curated video recommendations (home/banner positions) |
| `wf_settings` | Key-value system settings |

### Video Playback Pipeline

1. `api/play.php` receives `vod_id`, `episode_index`, `parse_api_id`
2. Queries CMS `mac_vod` for `vod_play_url` (format: `ep1$url1#ep2$url2#...`)
3. Parses the `#`-separated list to extract the correct episode's raw URL
4. Resolves parse API from `wf_parse_apis`, substitutes `{url}` with urlencoded raw URL
5. `curl` calls the parse API → returns m3u8 URL
6. Saves/updates play history record
7. Client receives m3u8 URL → hls.js loads and plays it

### Favorites (Own DB, Not CMS)

Favorites store video metadata as a snapshot at toggle time (`vod_name`, `vod_pic`, `vod_remarks`, `vod_score`, `vod_year`, `vod_area`). This avoids joining the CMS database on list queries. The `toggleFavorite` API call accepts a `vodInfo` object from the detail page.

### CSS Theme

Primary color: `#4a90d9` (blue). The stylesheet in `js/renderer/css/style.css` covers the full app including player control bar (progress track, thumb, tooltip, volume slider, auto-hide controls, center play button overlay).

## Key Constraints

- **Server URL must NOT appear in UI** — the `_baseUrl` in `api.js` is hardcoded; window title events are blocked; the settings page has NO server URL display/editing
- **CMS database is read-only** — never write to `mac_*` tables
- **`web/config/database.php`** uses `{PLACEHOLDER}` syntax — always use `str_replace` (not regex) in the installer to fill them
- **Play history**: one record per `(user_id, vod_id)` — episode changes update the existing row, not create new ones
- The renderer runs with `contextIsolation: true` and `nodeIntegration: false` — all Electron APIs must go through `preload.js` and `contextBridge`
