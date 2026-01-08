# 中文字体安装说明

## 问题
生成的图片中中文无法正常显示，显示为方框或乱码。

## 解决方案

### 方法1: 使用安装脚本（推荐）

```bash
cd /home/jungle/nodeloc_vps_test/server
sudo bash install_chinese_fonts.sh
```

### 方法2: 手动安装

#### Debian/Ubuntu 系统
```bash
# 安装中文字体包
sudo apt-get update
sudo apt-get install -y fonts-noto-cjk fonts-noto-cjk-extra

# 创建项目字体目录
mkdir -p /home/jungle/nodeloc_vps_test/server/fonts

# 复制字体文件
sudo cp /usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc /home/jungle/nodeloc_vps_test/server/fonts/

# 设置权限
sudo chown www-data:www-data /home/jungle/nodeloc_vps_test/server/fonts/*
sudo chmod 644 /home/jungle/nodeloc_vps_test/server/fonts/*
```

#### CentOS/RHEL 系统
```bash
# 安装中文字体包
sudo yum install -y google-noto-sans-cjk-fonts

# 创建项目字体目录并复制字体
mkdir -p /home/jungle/nodeloc_vps_test/server/fonts
sudo find /usr/share/fonts -name "*Noto*CJK*.ttf" -exec cp {} /home/jungle/nodeloc_vps_test/server/fonts/ \;

# 设置权限
sudo chown nginx:nginx /home/jungle/nodeloc_vps_test/server/fonts/*
sudo chmod 644 /home/jungle/nodeloc_vps_test/server/fonts/*
```

### 方法3: 下载字体文件

如果无法通过包管理器安装，可以直接下载字体：

```bash
# 创建字体目录
mkdir -p /home/jungle/nodeloc_vps_test/server/fonts
cd /home/jungle/nodeloc_vps_test/server/fonts

# 下载 Noto Sans SC 字体（选择其一）
# 方式1: 从 Google Fonts
wget "https://github.com/googlefonts/noto-cjk/raw/main/Sans/SubsetOTF/SC/NotoSansSC-Regular.otf" -O NotoSansSC-Regular.ttf

# 方式2: 从备用源
wget "https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSansSC/NotoSansSC-Regular.ttf"

# 设置权限
chmod 644 *.ttf
```

## 验证安装

安装完成后，可以通过以下方式验证：

```bash
# 检查字体文件是否存在
ls -lh /home/jungle/nodeloc_vps_test/server/fonts/

# 测试图片生成
curl "http://localhost/image.php?file=NL1767859366-65A535.txt&year=2026&month=01" > test.png
```

## 图片改进说明

已添加以下视觉改进：

1. **装饰元素**：
   - 标题区域添加了闪电图标 ⚡
   - 右上角装饰性圆圈
   - 左侧装饰方块
   - 底部装饰点阵

2. **Section图标**：
   - 📊 YABS
   - 🌐 IP质量
   - 🎬 流媒体
   - ⚡ 响应
   - 🚀 多线程测速
   - 📈 单线程测速
   - 🔄 回程路由

3. **彩色标记条**：
   - 每个section左侧有独特的颜色标记
   - 不同section使用不同主题色

4. **改进的指标显示**：
   - 成功/失败项目有彩色背景高亮
   - 使用渐变圆点作为项目符号
   - 更清晰的视觉层次

## 注意事项

- 确保Web服务器用户（www-data 或 nginx）有读取字体文件的权限
- 字体文件通常较大（10-20MB），下载可能需要一些时间
- 如果使用.ttc格式（TrueType Collection），PHP GD可能需要特定支持
