; ============================================
; 晚风影视 - NSIS 安装器主题定制
; ============================================

; ---- 品牌文字 ----
BrandingText "晚风影视"

; ---- 欢迎/完成页 ----
!define MUI_WELCOMEPAGE_TITLE "欢迎安装 晚风影视"
!define MUI_WELCOMEPAGE_TEXT "晚风影视 — 跨平台桌面视频播放客户端$\r$\n$\r$\n支持多源切换、HLS 流媒体播放、断点续播等。$\r$\n$\r$\n即将安装 晚风影视 ${VERSION} 到您的计算机。"

!define MUI_FINISHPAGE_TITLE "晚风影视 安装完成"
!define MUI_FINISHPAGE_TEXT "感谢使用晚风影视！桌面快捷方式已创建，双击即可启动。"

; ---- 卸载确认 ----
!define MUI_UNCONFIRMPAGE_TEXT_TOP "确定要从本机移除 晚风影视 吗？"
