/**
 * 生成 NSIS 安装器侧边栏/头部 BMP 图片（紫蓝渐变主题）
 */
const fs = require('fs');
const path = require('path');

// BMP 文件头 / DIB 头
function createBMP(width, height, pixelCallback) {
  const rowSize = ((width * 3 + 3) >> 2) << 2; // 4字节对齐
  const imageSize = rowSize * height;
  const fileSize = 54 + imageSize;

  const buf = Buffer.alloc(fileSize);

  // BMP 文件头 (14 bytes)
  buf.write('BM', 0);                    // 签名
  buf.writeUInt32LE(fileSize, 2);       // 文件大小
  buf.writeUInt32LE(0, 6);              // 保留
  buf.writeUInt32LE(54, 10);            // 像素数据偏移量

  // DIB 头 BITMAPINFOHEADER (40 bytes)
  buf.writeUInt32LE(40, 14);            // DIB 头大小
  buf.writeInt32LE(width, 18);          // 宽度
  buf.writeInt32LE(height, 22);         // 高度（正数=自底向上）
  buf.writeUInt16LE(1, 26);             // 色彩平面数
  buf.writeUInt16LE(24, 28);            // 位深度
  buf.writeUInt32LE(0, 30);             // 压缩方式
  buf.writeUInt32LE(imageSize, 34);     // 图像数据大小
  buf.writeInt32LE(0, 38);              // 水平分辨率
  buf.writeInt32LE(0, 42);              // 垂直分辨率
  buf.writeUInt32LE(0, 46);             // 调色板颜色数
  buf.writeUInt32LE(0, 50);             // 重要颜色数

  // 像素数据（自底向上）
  for (let y = 0; y < height; y++) {
    const rowOffset = 54 + y * rowSize;
    for (let x = 0; x < width; x++) {
      const [r, g, b] = pixelCallback(x / (width - 1), y / (height - 1));
      const px = rowOffset + x * 3;
      buf.writeUInt8(b, px);        // B
      buf.writeUInt8(g, px + 1);    // G
      buf.writeUInt8(r, px + 2);    // R
    }
    // 填充 4 字节对齐
    for (let p = width * 3; p < rowSize; p++) {
      buf.writeUInt8(0x0f, rowOffset + p);
    }
  }

  return buf;
}

// 紫蓝渐变：#6a11cb → #2575fc
function gradient(t) {
  const t2 = 1 - t;
  const r = Math.round(0x6a * t2 + 0x25 * t);
  const g = Math.round(0x11 * t2 + 0x75 * t);
  const b = Math.round(0xcb * t2 + 0xfc * t);
  return [r, g, b];
}

// 深色底+渐变叠加（从上到下渐变）
function gradientDark(t) {
  const dark = [0x0d, 0x11, 0x17]; // 暗色底色
  const light = [0x6a, 0x11, 0xcb]; // 紫色
  const t2 = 1 - t;
  return [
    Math.round(dark[0] * t2 + light[0] * t),
    Math.round(dark[1] * t2 + light[1] * t),
    Math.round(dark[2] * t2 + light[2] * t),
  ];
}

const outDir = path.join(__dirname, '..');

// 侧边栏 164×314
const sidebar = createBMP(164, 314, (fx, fy) => {
  // 底部暗色，顶部渐变
  return gradientDark(1 - fy);
});
fs.writeFileSync(path.join(outDir, 'installer-sidebar.bmp'), sidebar);
console.log('✅ installer-sidebar.bmp (164×314)');

// 头部 150×57（可选）
const header = createBMP(150, 57, (fx, fy) => {
  return gradient(1 - fy);
});
fs.writeFileSync(path.join(outDir, 'installer-header.bmp'), header);
console.log('✅ installer-header.bmp (150×57)');

// 卸载侧边栏 164×314
fs.writeFileSync(path.join(outDir, 'uninstaller-sidebar.bmp'), sidebar);
console.log('✅ uninstaller-sidebar.bmp (164×314)');
