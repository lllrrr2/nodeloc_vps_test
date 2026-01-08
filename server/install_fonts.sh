#!/bin/bash

# 安装中文字体以支持PNG生成
echo "正在安装中文字体..."

# 检测系统类型并安装相应的字体
if command -v apt-get &> /dev/null; then
    # Debian/Ubuntu系统
    echo "检测到Debian/Ubuntu系统"
    sudo apt-get update
    sudo apt-get install -y fonts-wqy-zenhei fonts-wqy-microhei fonts-noto-cjk
    
elif command -v yum &> /dev/null; then
    # CentOS/RHEL系统
    echo "检测到CentOS/RHEL系统"
    sudo yum install -y wqy-zenhei-fonts wqy-microhei-fonts google-noto-sans-cjk-fonts
    
elif command -v dnf &> /dev/null; then
    # Fedora系统
    echo "检测到Fedora系统"
    sudo dnf install -y wqy-zenhei-fonts wqy-microhei-fonts google-noto-sans-cjk-fonts
    
else
    echo "未知的系统类型，请手动安装中文字体"
    exit 1
fi

# 更新字体缓存
echo "更新字体缓存..."
sudo fc-cache -fv

# 验证安装
echo ""
echo "已安装的中文字体："
fc-list :lang=zh | head -10

echo ""
echo "字体安装完成！"
