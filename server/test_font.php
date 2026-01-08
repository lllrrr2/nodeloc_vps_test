<?php
/**
 * 字体和中文支持测试脚本
 */

// 设置编码
mb_internal_encoding('UTF-8');
header('Content-Type: image/png');

// 检查 GD 扩展
if (!extension_loaded('gd')) {
    die('GD extension not loaded');
}

// 创建测试图片
$width = 600;
$height = 400;
$image = imagecreatetruecolor($width, $height);

// 定义颜色
$bgColor = imagecolorallocate($image, 255, 255, 255);
$textColor = imagecolorallocate($image, 33, 33, 33);
$successColor = imagecolorallocate($image, 76, 175, 80);
$errorColor = imagecolorallocate($image, 244, 67, 54);
$borderColor = imagecolorallocate($image, 200, 200, 200);

// 填充背景
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);

// 查找字体文件
$fontPaths = [
    __DIR__ . '/fonts/DejaVuSans.ttf',
    __DIR__ . '/DejaVuSans.ttf',
    '/www/wwwroot/bench.nodeloc.cc/fonts/DejaVuSans.ttf',
];

$fontFile = null;
$fontFound = false;
foreach ($fontPaths as $path) {
    if (@file_exists($path)) {
        $fontFile = $path;
        $fontFound = true;
        break;
    }
}

$y = 30;

// 显示字体状态
if ($fontFound) {
    imagettftext($image, 14, 0, 20, $y, $successColor, $fontFile, "✓ 字体已找到");
    $y += 30;
    imagettftext($image, 10, 0, 20, $y, $textColor, $fontFile, "字体路径: " . $fontFile);
    $y += 40;
    
    // 测试中文显示
    imagettftext($image, 12, 0, 20, $y, $textColor, $fontFile, "中文测试文本:");
    $y += 25;
    imagettftext($image, 11, 0, 30, $y, $textColor, $fontFile, "• NodeLoc VPS 测试报告");
    $y += 25;
    imagettftext($image, 11, 0, 30, $y, $textColor, $fontFile, "• CPU 型号: Intel Xeon");
    $y += 25;
    imagettftext($image, 11, 0, 30, $y, $textColor, $fontFile, "• 内存: 8GB DDR4");
    $y += 25;
    imagettftext($image, 11, 0, 30, $y, $textColor, $fontFile, "• 磁盘速度: 1500 MB/s");
    $y += 25;
    imagettftext($image, 11, 0, 30, $y, $textColor, $fontFile, "• 流媒体解锁: Netflix ✓ Disney+ ✓");
    $y += 40;
    
    // 特殊字符测试
    imagettftext($image, 12, 0, 20, $y, $textColor, $fontFile, "特殊字符测试:");
    $y += 25;
    imagettftext($image, 11, 0, 30, $y, $successColor, $fontFile, "✓ ✔ ☑ 成功");
    $y += 25;
    imagettftext($image, 11, 0, 30, $y, $errorColor, $fontFile, "✗ ✘ ☒ 失败");
    $y += 25;
    imagettftext($image, 11, 0, 30, $y, $textColor, $fontFile, "→ ← ↑ ↓ 箭头");
    
} else {
    imagestring($image, 5, 20, $y, "X Font NOT Found", $errorColor);
    $y += 30;
    imagestring($image, 3, 20, $y, "GD built-in font will be used", $textColor);
    $y += 20;
    imagestring($image, 3, 20, $y, "(Chinese characters NOT supported)", $textColor);
    $y += 40;
    
    // 使用内置字体的英文测试
    imagestring($image, 4, 20, $y, "English Test Text:", $textColor);
    $y += 20;
    imagestring($image, 3, 30, $y, "- NodeLoc VPS Test Report", $textColor);
    $y += 20;
    imagestring($image, 3, 30, $y, "- CPU Model: Intel Xeon", $textColor);
    $y += 20;
    imagestring($image, 3, 30, $y, "- RAM: 8GB DDR4", $textColor);
    $y += 20;
    imagestring($image, 3, 30, $y, "- Disk Speed: 1500 MB/s", $textColor);
    $y += 20;
    imagestring($image, 3, 30, $y, "- Streaming: Netflix [Y] Disney+ [Y]", $textColor);
    $y += 40;
    
    imagestring($image, 2, 20, $y, "To fix: Run ./install_fonts.sh", $errorColor);
}

// 添加底部信息
$bottom = $height - 30;
$infoText = "GD Info: ";
$gdInfo = gd_info();
if (isset($gdInfo['FreeType Support']) && $gdInfo['FreeType Support']) {
    $infoText .= "FreeType: YES";
} else {
    $infoText .= "FreeType: NO";
}

if ($fontFound) {
    imagettftext($image, 9, 0, 20, $bottom, $borderColor, $fontFile, $infoText);
} else {
    imagestring($image, 2, 20, $bottom - 15, $infoText, $borderColor);
}

// 输出图片
imagepng($image);
imagedestroy($image);
