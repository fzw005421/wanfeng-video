/**
 * 晚风影视 - Preload 脚本
 * 通过 contextBridge 安全暴露 IPC 接口给渲染进程
 */
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  // 窗口控制
  minimize: () => ipcRenderer.invoke('window:minimize'),
  maximize: () => ipcRenderer.invoke('window:maximize'),
  close: () => ipcRenderer.invoke('window:close'),
  isMaximized: () => ipcRenderer.invoke('window:isMaximized'),
  setTitle: (title) => ipcRenderer.invoke('window:setTitle', title),

  // 平台信息
  platform: process.platform,
});
