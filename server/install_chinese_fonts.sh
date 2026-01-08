#!/bin/bash

# 安装中文字体脚本
# 用于支持在生成的图片中显示中文

echo "正在安装中文字体..."

# 检查是否有root权限
if [ "$EUID" -ne 0 ]; then 
    echo "请使用 sudo 运行此脚本"
    exit 1
fi

# 创建字体目录
mkdir -p /home/jungle/nodeloc_vps_test/server/fonts

cd /home/jungle/nodeloc_vps_test/server/fonts

# 下载 Noto Sans SC (简体中文) 字体
echo "正在下载 Noto Sans SC 字体..."
wget -O NotoSansSC-Regular.ttf "https://github.com/googlefonts/noto-cjk/raw/main/Sans/OTF/SimplifiedChinese/NotoSansSC-Regular.otf" 2>/dev/null || \
wget -O NotoSansSC-Regular.ttf "https://noto-website-2.storage.googleapis.com/pkgs/NotoSansSC.zip" 2>/dev/null

# 如果下载失败，尝试从系统安装
if [ ! -f "NotoSansSC-Regular.ttf" ]; then
    echo "直接下载失败，尝试通过包管理器安装..."
    
    # 检测系统类型
    if command -v apt-get &> /dev/null; then
        # Debian/Ubuntu
        apt-get update
        apt-get install -y fonts-noto-cjk fonts-noto-cjk-extra
        
        # 复制字体到项目目录
        if [ -f "/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc" ]; then
            cp /usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc ./
            echo "已安装 Noto Sans CJK 字体"
        fi
    elif command -v yum &> /dev/null; then
        # CentOS/RHEL
        yum install -y google-noto-sans-cjk-fonts
    fi
fi

# 设置权限
chmod 644 *.ttf *.ttc *.otf 2>/dev/null
chown www-data:www-data *.ttf *.ttc *.otf 2>/dev/null || chown nginx:nginx *.ttf *.ttc *.otf 2>/dev/null

echo "字体安装完成！"
echo "字体位置: /home/jungle/nodeloc_vps_test/server/fonts/"
ls -lh /home/jungle/nodeloc_vps_test/server/fonts/
