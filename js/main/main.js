/**
 * 晚风影视 - Electron 主进程
 * 负责窗口管理、生命周期控制
 */
const { app, BrowserWindow, ipcMain, Menu, shell } = require('electron');
const path = require('path');
const fs = require('fs');

let mainWindow = null;

function createWindow() {
  // 移除默认菜单栏
  Menu.setApplicationMenu(null);

  mainWindow = new BrowserWindow({
    width: 1280,
    height: 820,
    minWidth: 960,
    minHeight: 640,
    title: '晚风影视',
    backgroundColor: '#f5f6f8',
    show: false,
    frame: false,
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      webSecurity: true,
    },
  });

  mainWindow.loadFile(path.join(__dirname, '..', 'renderer', 'index.html'));

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });

  // 屏蔽窗口标题显示服务器地址
  mainWindow.on('page-title-updated', (event) => {
    event.preventDefault();
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

// ======================== IPC 处理器 ========================

ipcMain.handle('window:minimize', () => {
  if (mainWindow) mainWindow.minimize();
});

ipcMain.handle('window:maximize', () => {
  if (!mainWindow) return;
  if (mainWindow.isMaximized()) {
    mainWindow.unmaximize();
  } else {
    mainWindow.maximize();
  }
});

ipcMain.handle('window:close', () => {
  if (mainWindow) mainWindow.close();
});

ipcMain.handle('window:isMaximized', () => {
  return mainWindow ? mainWindow.isMaximized() : false;
});

ipcMain.handle('window:setTitle', (_event, title) => {
  if (mainWindow) mainWindow.setTitle(title || '晚风影视');
});

ipcMain.handle('shell:openExternal', (_event, url) => {
  if (!url || typeof url !== 'string') return false;
  // 只允许 http/https 链接
  if (!/^https?:\/\//i.test(url)) return false;
  try {
    return shell.openExternal(url);
  } catch (e) {
    return false;
  }
});

// ======================== 自动创建桌面快捷方式 ========================

function createDesktopShortcut() {
  const flagPath = path.join(app.getPath('userData'), '.shortcut_created');
  if (fs.existsSync(flagPath)) return;

  try {
    const desktopDir = app.getPath('desktop');
    const shortcutPath = path.join(desktopDir, '晚风影视.lnk');
    const exePath = process.execPath;

    if (exePath.includes('electron') && !exePath.includes('晚风影视')) return;
    if (fs.existsSync(shortcutPath)) {
      fs.writeFileSync(flagPath, 'exists');
      return;
    }

    const success = shell.writeShortcutLink(shortcutPath, {
      target: exePath,
      args: '',
      description: '晚风影视 - 桌面客户端',
      icon: exePath,
      iconIndex: 0,
    });

    if (success) {
      console.log('桌面快捷方式已创建:', shortcutPath);
    }
  } catch (e) {
    console.error('创建快捷方式失败:', e.message);
  }

  fs.writeFileSync(flagPath, 'ok');
}

// 启用 HEVC/H.265 硬件解码（需 Windows 安装 HEVC 视频扩展）
app.commandLine.appendSwitch('enable-features', 'PlatformHEVCDecoderSupport,HardwareMediaKeyHandling');

app.whenReady().then(() => {
  createDesktopShortcut();
  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('before-quit', () => {
  // 清理
});
