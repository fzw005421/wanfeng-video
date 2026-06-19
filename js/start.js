/**
 * 开发启动器 — 清除 ELECTRON_RUN_AS_NODE 后启动 Electron
 * VS Code 终端会注入 ELECTRON_RUN_AS_NODE=1，导致 electron 以 Node 模式运行
 */
delete process.env.ELECTRON_RUN_AS_NODE;
require('child_process').spawn(
  require('electron'),
  ['.', '--ignore-certificate-errors'],
  { stdio: 'inherit', windowsHide: false }
);
