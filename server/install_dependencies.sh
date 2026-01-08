#!/bin/bash

# NodeLoc VPS Test Server - 依赖安装脚本
# 自动检测系统并安装所需依赖

set -e

echo "================================================"
echo "  NodeLoc VPS Test Server - 依赖安装"
echo "================================================"
echo ""

# 检测操作系统
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo "无法检测操作系统"
    exit 1
fi

echo "检测到操作系统: $OS $VERSION"
echo ""

# 检测 PHP 是否已安装
if ! command -v php &> /dev/null; then
    echo "❌ PHP 未安装"
    echo "请先安装 PHP 7.4 或更高版本"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "✅ PHP 版本: $PHP_VERSION"
echo ""

# 根据操作系统安装依赖
case "$OS" in
    ubuntu|debian)
        echo "使用 APT 包管理器安装依赖..."
        echo ""
        
        # 更新包列表
        echo "📦 更新软件包列表..."
        sudo apt-get update -qq
        
        # 安装依赖
        echo "📦 安装 PHP GD 扩展..."
        sudo apt-get install -y php-gd
        
        echo "📦 安装 PHP mbstring 扩展..."
        sudo apt-get install -y php-mbstring
        
        echo "📦 安装 PHP cURL 扩展..."
        sudo apt-get install -y php-curl
        
        echo "📦 安装 DejaVu 字体..."
        sudo apt-get install -y fonts-dejavu-core
        
        # 重启服务
        if systemctl is-active --quiet apache2; then
            echo "🔄 重启 Apache..."
            sudo systemctl restart apache2
        fi
        
        if systemctl is-active --quiet nginx; then
            echo "🔄 重启 Nginx..."
            sudo systemctl restart nginx
            if systemctl is-active --quiet php*-fpm; then
                echo "🔄 重启 PHP-FPM..."
                sudo systemctl restart php*-fpm
            fi
        fi
        ;;
        
    centos|rhel|fedora)
        echo "使用 YUM/DNF 包管理器安装依赖..."
        echo ""
        
        # 确定使用 dnf 还是 yum
        if command -v dnf &> /dev/null; then
            PKG_MGR="dnf"
        else
            PKG_MGR="yum"
        fi
        
        # 安装依赖
        echo "📦 安装 PHP GD 扩展..."
        sudo $PKG_MGR install -y php-gd
        
        echo "📦 安装 PHP mbstring 扩展..."
        sudo $PKG_MGR install -y php-mbstring
        
        echo "📦 安装 PHP cURL 扩展..."
        sudo $PKG_MGR install -y php-curl
        
        echo "📦 安装 DejaVu 字体..."
        sudo $PKG_MGR install -y dejavu-sans-fonts
        
        # 重启服务
        if systemctl is-active --quiet httpd; then
            echo "🔄 重启 Apache..."
            sudo systemctl restart httpd
        fi
        
        if systemctl is-active --quiet nginx; then
            echo "🔄 重启 Nginx..."
            sudo systemctl restart nginx
            if systemctl is-active --quiet php-fpm; then
                echo "🔄 重启 PHP-FPM..."
                sudo systemctl restart php-fpm
            fi
        fi
        ;;
        
    *)
        echo "❌ 不支持的操作系统: $OS"
        echo "请手动安装以下依赖："
        echo "  - php-gd"
        echo "  - php-mbstring"
        echo "  - php-curl"
        echo "  - dejavu-sans-fonts"
        exit 1
        ;;
esac

echo ""
echo "================================================"
echo "  安装完成！"
echo "================================================"
echo ""

# 验证安装
echo "验证安装结果："
echo ""

# 检查 GD
if php -m | grep -q "gd"; then
    echo "✅ PHP GD 扩展 - 已安装"
else
    echo "❌ PHP GD 扩展 - 未安装"
fi

# 检查 mbstring
if php -m | grep -q "mbstring"; then
    echo "✅ PHP mbstring 扩展 - 已安装"
else
    echo "❌ PHP mbstring 扩展 - 未安装"
fi

# 检查 curl
if php -m | grep -q "curl"; then
    echo "✅ PHP cURL 扩展 - 已安装"
else
    echo "❌ PHP cURL 扩展 - 未安装"
fi

# 检查字体
FONT_PATHS=(
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
    "/usr/share/fonts/dejavu/DejaVuSans.ttf"
    "/usr/share/fonts/truetype/dejavu-sans/DejaVuSans.ttf"
)

FONT_FOUND=false
for font in "${FONT_PATHS[@]}"; do
    if [ -f "$font" ]; then
        echo "✅ DejaVu Sans 字体 - 已安装 ($font)"
        FONT_FOUND=true
        break
    fi
done

if [ "$FONT_FOUND" = false ]; then
    echo "❌ DejaVu Sans 字体 - 未找到"
fi

echo ""
echo "================================================"
echo "下一步："
echo "1. 访问 check_requirements.php 查看完整检查结果"
echo "2. 访问 test_image.html 测试图片生成功能"
echo "================================================"
