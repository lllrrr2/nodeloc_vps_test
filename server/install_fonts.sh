#!/bin/bash

# 字体安装脚本 - 用于有 open_basedir 限制的环境
# 将 DejaVu Sans 字体复制到网站目录

echo "================================================"
echo "  NodeLoc VPS Test - 字体安装脚本"
echo "================================================"
echo ""

# 获取脚本所在目录
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
FONT_DIR="${SCRIPT_DIR}/fonts"

echo "目标字体目录: ${FONT_DIR}"
echo ""

# 创建字体目录
if [ ! -d "$FONT_DIR" ]; then
    echo "📁 创建字体目录..."
    mkdir -p "$FONT_DIR"
fi

# 查找系统字体
SYSTEM_FONT_PATHS=(
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
    "/usr/share/fonts/dejavu/DejaVuSans.ttf"
    "/usr/share/fonts/truetype/dejavu-sans/DejaVuSans.ttf"
    "/System/Library/Fonts/Supplemental/DejaVuSans.ttf"
)

FONT_FOUND=false
SOURCE_FONT=""

for font_path in "${SYSTEM_FONT_PATHS[@]}"; do
    if [ -f "$font_path" ]; then
        echo "✅ 找到系统字体: $font_path"
        SOURCE_FONT="$font_path"
        FONT_FOUND=true
        break
    fi
done

if [ "$FONT_FOUND" = true ]; then
    # 复制字体文件
    echo "📦 复制字体文件到 ${FONT_DIR}/DejaVuSans.ttf ..."
    cp "$SOURCE_FONT" "${FONT_DIR}/DejaVuSans.ttf"
    
    # 设置权限
    echo "🔒 设置文件权限..."
    chmod 644 "${FONT_DIR}/DejaVuSans.ttf"
    
    echo ""
    echo "✅ 字体安装成功！"
    echo ""
    echo "字体位置: ${FONT_DIR}/DejaVuSans.ttf"
else
    echo "❌ 未找到系统字体文件"
    echo ""
    echo "解决方案："
    echo ""
    echo "1. 安装 DejaVu 字体："
    echo "   Ubuntu/Debian: sudo apt-get install fonts-dejavu-core"
    echo "   CentOS/RHEL:   sudo yum install dejavu-sans-fonts"
    echo ""
    echo "2. 或者手动下载字体："
    echo "   wget -O ${FONT_DIR}/DejaVuSans.ttf \\"
    echo "     https://github.com/dejavu-fonts/dejavu-fonts/raw/master/ttf/DejaVuSans.ttf"
    echo ""
    echo "3. 然后设置权限："
    echo "   chmod 644 ${FONT_DIR}/DejaVuSans.ttf"
    
    # 尝试自动下载
    echo ""
    read -p "是否尝试自动下载字体文件？(y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "📥 正在下载字体..."
        if command -v wget &> /dev/null; then
            wget -O "${FONT_DIR}/DejaVuSans.ttf" \
                "https://github.com/dejavu-fonts/dejavu-fonts/raw/master/ttf/DejaVuSans.ttf"
            
            if [ -f "${FONT_DIR}/DejaVuSans.ttf" ]; then
                chmod 644 "${FONT_DIR}/DejaVuSans.ttf"
                echo "✅ 字体下载成功！"
                FONT_FOUND=true
            else
                echo "❌ 下载失败"
            fi
        elif command -v curl &> /dev/null; then
            curl -L -o "${FONT_DIR}/DejaVuSans.ttf" \
                "https://github.com/dejavu-fonts/dejavu-fonts/raw/master/ttf/DejaVuSans.ttf"
            
            if [ -f "${FONT_DIR}/DejaVuSans.ttf" ]; then
                chmod 644 "${FONT_DIR}/DejaVuSans.ttf"
                echo "✅ 字体下载成功！"
                FONT_FOUND=true
            else
                echo "❌ 下载失败"
            fi
        else
            echo "❌ 未找到 wget 或 curl 命令"
        fi
    fi
fi

echo ""
echo "================================================"

if [ "$FONT_FOUND" = true ] || [ -f "${FONT_DIR}/DejaVuSans.ttf" ]; then
    echo "  安装完成！"
    echo "================================================"
    echo ""
    echo "提示："
    echo "- 字体已就绪，图片生成将使用 TrueType 字体"
    echo "- 访问 test_image.html 测试图片生成功能"
    echo ""
    
    # 显示文件信息
    if [ -f "${FONT_DIR}/DejaVuSans.ttf" ]; then
        FILE_SIZE=$(du -h "${FONT_DIR}/DejaVuSans.ttf" | cut -f1)
        echo "字体文件大小: ${FILE_SIZE}"
    fi
else
    echo "  需要手动安装"
    echo "================================================"
    echo ""
    echo "注意："
    echo "- 没有字体文件时，系统将使用 GD 内置字体"
    echo "- 内置字体不支持中文，显示效果较差"
    echo "- 建议按照上述说明手动安装字体"
fi

echo ""
