<?php
/**
 * VPS测试报告图片生成器
 * 根据测试结果生成美观的图片报告
 * 
 * 依赖要求：
 * - PHP GD 扩展 (必需)
 * - DejaVu Sans 字体 (可选，用于中文显示)
 */

// 检查 GD 扩展
if (!extension_loaded('gd')) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    die("错误: PHP GD 扩展未安装\n\n" .
        "安装方法：\n" .
        "Ubuntu/Debian: sudo apt-get install php-gd\n" .
        "CentOS/RHEL: sudo yum install php-gd\n" .
        "然后重启Web服务器: sudo systemctl restart apache2 或 nginx");
}

// 设置字符编码和内容类型
mb_internal_encoding('UTF-8');
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');

// 获取测试结果文件路径
$filePath = $_GET['file'] ?? '';

if (empty($filePath)) {
    generateErrorImage("错误: 未指定文件");
    exit;
}

// 安全检查：防止路径遍历攻击
$filePath = basename($filePath);
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

$fullPath = __DIR__ . "/{$year}/{$month}/{$filePath}";

if (!file_exists($fullPath) || !is_file($fullPath)) {
    generateErrorImage("错误: 文件不存在");
    exit;
}

// 读取测试结果
$content = file_get_contents($fullPath);
if ($content === false) {
    generateErrorImage("错误: 无法读取文件");
    exit;
}

// 解析测试结果
$data = parseTestResults($content);

// 生成图片
generateResultImage($data);

/**
 * 翻译Section名称为英文
 */
function translateSectionName($name) {
    $translations = [
        'YABS' => 'YABS Benchmark',
        'IP质量' => 'IP Quality Check',
        '流媒体' => 'Streaming Services',
        '响应' => 'Response Test',
        '多线程测速' => 'Multi-thread Speed Test',
        '单线程测速' => 'Single-thread Speed Test',
        '回程路由' => 'Route Trace Back',
    ];
    return $translations[$name] ?? $name;
}

/**
 * 翻译指标键为英文
 */
function translateMetricKey($key) {
    $translations = [
        'CPU' => 'CPU Model',
        '内存' => 'Memory',
        '磁盘' => 'Disk',
        '磁盘速度' => 'Disk Speed',
        'IP类型' => 'IP Type',
        '黑名单' => 'Blacklist Status',
        '平均下载' => 'Avg Download Speed',
        '平均上传' => 'Avg Upload Speed',
        '平均延迟' => 'Avg Latency',
        '解锁' => 'Unlocked',
        '失败' => 'Failed',
        '成功' => 'Success',
    ];
    return $translations[$key] ?? $key;
}

/**
 * 获取section对应的图标
 */
function getSectionIcon($sectionName) {
    $icons = [
        'YABS' => '📊',
        'IP质量' => '🌐',
        '流媒体' => '🎬',
        '响应' => '⚡',
        '多线程测速' => '🚀',
        '单线程测速' => '📈',
        '回程路由' => '🔄',
    ];
    return $icons[$sectionName] ?? '📋';
}

/**
 * 获取section对应的颜色标记
 */
function getSectionColor($image, $sectionName) {
    $colors = [
        'YABS' => [66, 165, 245],        // 蓝色
        'IP质量' => [102, 187, 106],      // 绿色
        '流媒体' => [255, 112, 67],       // 橙红色
        '响应' => [255, 202, 40],         // 黄色
        '多线程测速' => [171, 71, 188],   // 紫色
        '单线程测速' => [38, 198, 218],   // 青色
        '回程路由' => [255, 167, 38],     // 橙色
    ];
    
    $color = $colors[$sectionName] ?? [158, 158, 158]; // 默认灰色
    return imagecolorallocate($image, $color[0], $color[1], $color[2]);
}

/**
 * 解析测试结果
 */
function parseTestResults($content) {
    $data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'sections' => []
    ];
    
    // 解析标签内容
    preg_match_all('/\[tab="([^"]+)"\](.*?)\[\/tab\]/s', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $tabName = $match[1];
        $tabContent = trim(str_replace(['```', '`'], '', $match[2]));
        
        $data['sections'][$tabName] = parseSectionContent($tabName, $tabContent);
    }
    
    return $data;
}

/**
 * 解析各个section的内容
 */
function parseSectionContent($tabName, $content) {
    $result = ['raw' => $content, 'metrics' => []];
    
    switch ($tabName) {
        case 'YABS':
            $result['metrics'] = parseYABS($content);
            break;
        case 'IP质量':
            $result['metrics'] = parseIPQuality($content);
            break;
        case '流媒体':
            $result['metrics'] = parseStreaming($content);
            break;
        case '多线程测速':
        case '单线程测速':
            $result['metrics'] = parseSpeedTest($content);
            break;
        case '响应':
            $result['metrics'] = parseResponse($content);
            break;
        case '回程路由':
            $result['metrics'] = parseRouteTrace($content);
            break;
    }
    
    return $result;
}

/**
 * 解析YABS测试结果
 */
function parseYABS($content) {
    $metrics = [];
    
    // CPU信息
    if (preg_match('/Processor\s*:\s*(.+)/i', $content, $match)) {
        $metrics['CPU'] = trim($match[1]);
    } elseif (preg_match('/CPU.*?:\s*(.+)/i', $content, $match)) {
        $metrics['CPU'] = trim($match[1]);
    }
    
    // CPU核心数
    if (preg_match('/CPU cores\s*:\s*(\d+)/i', $content, $match)) {
        $metrics['CPU Cores'] = $match[1];
    }
    
    // 内存
    if (preg_match('/RAM\s*:\s*(.+)/i', $content, $match)) {
        $metrics['Memory'] = trim($match[1]);
    }
    
    // 磁盘
    if (preg_match('/Disk\s*:\s*(.+)/i', $content, $match)) {
        $metrics['Disk'] = trim($match[1]);
    }
    
    // 虚拟化类型
    if (preg_match('/VM Type\s*:\s*(.+)/i', $content, $match)) {
        $metrics['Virtualization'] = trim($match[1]);
    }
    
    // 磁盘读写速度 - 提取混合读写的总速度
    if (preg_match('/Total\s*\|\s*(\d+\.?\d*)\s*(MB\/s|GB\/s)/i', $content, $match)) {
        $speed = $match[1];
        $unit = $match[2];
        if ($unit === 'MB/s' && floatval($speed) < 1000) {
            $metrics['Disk I/O'] = $speed . ' ' . $unit;
        } elseif ($unit === 'GB/s') {
            $metrics['Disk I/O'] = $speed . ' ' . $unit;
        }
    }
    
    return $metrics;
}

/**
 * 解析IP质量
 */
function parseIPQuality($content) {
    $metrics = [];
    
    if (preg_match('/IP类型:\s*(.+)/', $content, $match)) {
        $metrics['IP类型'] = trim($match[1]);
    }
    
    if (preg_match('/黑名单记录统计.*?(\d+)\/(\d+)/s', $content, $match)) {
        $metrics['黑名单'] = "{$match[1]}/{$match[2]}";
    }
    
    return $metrics;
}

/**
 * 解析流媒体解锁
 */
function parseStreaming($content) {
    $metrics = [];
    $services = ['Netflix', 'YouTube', 'Disney\+', 'TikTok', 'Amazon Prime', 'ChatGPT', 'Spotify'];
    
    foreach ($services as $service) {
        $pattern = "/" . str_replace('+', '\+', $service) . "[：:]*\s*(.+)/ui";
        if (preg_match($pattern, $content, $match)) {
            $status = trim($match[1]);
            $serviceName = str_replace('\\', '', $service);
            
            if (stripos($status, '解锁') !== false || stripos($status, 'Yes') !== false || stripos($status, '原生') !== false) {
                $metrics[$serviceName] = '✓';
            } elseif (stripos($status, '失败') !== false || stripos($status, 'No') !== false || stripos($status, '屏蔽') !== false) {
                $metrics[$serviceName] = '✗';
            }
        }
    }
    
    // 统计解锁数量
    $unlocked = count(array_filter($metrics, function($v) { return $v === '✓'; }));
    $total = count($metrics);
    if ($total > 0) {
        $metrics['Summary'] = "$unlocked/$total unlocked";
    }
    
    return $metrics;
}

/**
 * 解析测速结果
 */
function parseSpeedTest($content) {
    $metrics = [];
    
    // 提取上传下载速度（支持多种单位）
    preg_match_all('/(\d+\.?\d*)\s*(Mbps|MB\/s).*?(\d+\.?\d*)\s*(Mbps|MB\/s)/i', $content, $matches, PREG_SET_ORDER);
    
    if (!empty($matches)) {
        $avgDown = 0;
        $avgUp = 0;
        $count = count($matches);
        
        foreach ($matches as $match) {
            $down = floatval($match[1]);
            $up = floatval($match[3]);
            
            // 转换 MB/s 到 Mbps
            if (stripos($match[2], 'MB/s') !== false) {
                $down = $down * 8;
            }
            if (stripos($match[4], 'MB/s') !== false) {
                $up = $up * 8;
            }
            
            $avgDown += $down;
            $avgUp += $up;
        }
        
        if ($count > 0) {
            $metrics['Avg Download'] = round($avgDown / $count, 2) . ' Mbps';
            $metrics['Avg Upload'] = round($avgUp / $count, 2) . ' Mbps';
            $metrics['Test Nodes'] = $count;
        }
    }
    
    return $metrics;
}

/**
 * 解析响应测试
 */
function parseResponse($content) {
    $metrics = [];
    
    // 提取平均响应时间
    if (preg_match('/平均.*?(\d+)ms/i', $content, $match)) {
        $metrics['平均延迟'] = $match[1] . ' ms';
    }
    
    return $metrics;
}

/**
 * 解析回程路由
 */
function parseRouteTrace($content) {
    $metrics = [];
    
    // 提取三网回程信息
    if (preg_match('/电信.*?(\S+)/u', $content, $match)) {
        $metrics['电信回程'] = trim($match[1]);
    }
    
    if (preg_match('/联通.*?(\S+)/u', $content, $match)) {
        $metrics['联通回程'] = trim($match[1]);
    }
    
    if (preg_match('/移动.*?(\S+)/u', $content, $match)) {
        $metrics['移动回程'] = trim($match[1]);
    }
    
    // 如果没有匹配到，尝试简单提取
    if (empty($metrics)) {
        $lines = explode("\n", $content);
        $routeCount = 0;
        foreach ($lines as $line) {
            if (preg_match('/traceroute|route/i', $line)) {
                $routeCount++;
            }
        }
        if ($routeCount > 0) {
            $metrics['路由测试'] = $routeCount . ' routes traced';
        }
    }
    
    return $metrics;
}

/**
 * 生成结果图片
 */
function generateResultImage($data) {
    // 图片尺寸
    $width = 850;
    $headerHeight = 100;
    $sectionHeight = 45;
    $metricsLineHeight = 32;
    $padding = 20;
    
    // 计算总高度
    $totalMetrics = 0;
    foreach ($data['sections'] as $section) {
        if (!empty($section['metrics'])) {
            $totalMetrics += count($section['metrics']);
        }
    }
    
    $sectionsCount = count($data['sections']);
    $height = $headerHeight + ($sectionsCount * ($sectionHeight + 10)) + ($totalMetrics * $metricsLineHeight) + 80;
    
    // 创建图片
    $image = imagecreatetruecolor($width, $height);
    
    // 定义现代化配色方案
    $bgColor = imagecolorallocate($image, 248, 249, 250);           // 浅灰背景
    $headerBg = imagecolorallocate($image, 26, 115, 232);           // 现代蓝色
    $headerBgDark = imagecolorallocate($image, 13, 71, 161);        // 深蓝
    $sectionBg = imagecolorallocate($image, 227, 242, 253);         // 浅蓝色
    $sectionBorder = imagecolorallocate($image, 144, 202, 249);     // 蓝色边框
    $textColor = imagecolorallocate($image, 33, 33, 33);            // 深灰文字
    $textLight = imagecolorallocate($image, 97, 97, 97);            // 浅灰文字
    $whiteColor = imagecolorallocate($image, 255, 255, 255);
    $successColor = imagecolorallocate($image, 56, 142, 60);        // 成功绿
    $failColor = imagecolorallocate($image, 211, 47, 47);           // 失败红
    $accentColor = imagecolorallocate($image, 255, 167, 38);        // 强调橙
    
    // 填充背景
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
    
    // 查找字体 - 优先使用支持中文的字体
    $fontPaths = [
        // 中文字体路径
        __DIR__ . '/fonts/NotoSansSC-Regular.ttf',
        __DIR__ . '/fonts/NotoSansCJK-Regular.ttf',
        __DIR__ . '/fonts/SourceHanSansCN-Regular.ttf',
        '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
        '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttf',
        '/System/Library/Fonts/PingFang.ttc',
        // 备用英文字体
        __DIR__ . '/fonts/DejaVuSans.ttf',
        __DIR__ . '/DejaVuSans.ttf',
        '/www/wwwroot/bench.nodeloc.cc/fonts/DejaVuSans.ttf',
    ];
    
    if (!ini_get('open_basedir')) {
        $fontPaths = array_merge($fontPaths, [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ]);
    }
    
    $fontFile = null;
    $fontExists = false;
    foreach ($fontPaths as $path) {
        if (@file_exists($path)) {
            $fontFile = $path;
            $fontExists = true;
            break;
        }
    }
    
    // 绘制现代化标题区域（渐变效果通过两层矩形模拟）
    imagefilledrectangle($image, 0, 0, $width, $headerHeight, $headerBg);
    imagefilledrectangle($image, 0, $headerHeight - 20, $width, $headerHeight, $headerBgDark);
    
    // 绘制装饰性图形元素
    // 右上角装饰圆圈
    imagefilledellipse($image, $width - 50, 30, 80, 80, $headerBgDark);
    imagefilledellipse($image, $width - 70, 50, 60, 60, $headerBg);
    
    // 左侧装饰方块
    $decorSize = 12;
    for ($i = 0; $i < 3; $i++) {
        $x = $padding - 5 + ($i * 6);
        $y = 10 + ($i * 6);
        imagefilledrectangle($image, $x, $y, $x + $decorSize, $y + $decorSize, $accentColor);
    }
    
    // 标题文本
    $title = "NodeLoc VPS Benchmark Report";
    $subtitle = "Generated: " . $data['timestamp'];
    
    if ($fontExists) {
        // 添加图标风格的emoji/符号
        $iconText = "⚡"; // 闪电图标
        imagettftext($image, 32, 0, $padding, 45, $accentColor, $fontFile, $iconText);
        
        // 标题
        imagettftext($image, 24, 0, $padding + 50, 40, $whiteColor, $fontFile, $title);
        // 副标题
        imagettftext($image, 12, 0, $padding + 50, 65, $whiteColor, $fontFile, $subtitle);
        // 装饰线
        imagefilledrectangle($image, $padding + 50, 75, $padding + 200, 78, $accentColor);
    } else {
        imagestring($image, 5, $padding, 25, $title, $whiteColor);
        imagestring($image, 3, $padding, 55, $subtitle, $whiteColor);
    }
    
    // 绘制测试结果
    $currentY = $headerHeight + 25;
    
    foreach ($data['sections'] as $sectionName => $section) {
        if (empty($section['metrics'])) {
            continue;
        }
        
        // 翻译section名称为英文
        $sectionNameEn = translateSectionName($sectionName);
        
        // 为每个section选择图标
        $sectionIcon = getSectionIcon($sectionName);
        
        // 绘制section标题（圆角效果）
        drawRoundedRect($image, $padding, $currentY, $width - $padding, $currentY + $sectionHeight, 8, $sectionBg, $sectionBorder);
        
        // 绘制左侧彩色标记条
        $markerColor = getSectionColor($image, $sectionName);
        imagefilledrectangle($image, $padding + 5, $currentY + 10, $padding + 10, $currentY + $sectionHeight - 10, $markerColor);
        
        if ($fontExists) {
            // 绘制图标
            imagettftext($image, 18, 0, $padding + 20, $currentY + 32, $headerBg, $fontFile, $sectionIcon);
            // 绘制标题
            imagettftext($image, 15, 0, $padding + 50, $currentY + 30, $headerBg, $fontFile, $sectionNameEn);
        } else {
            imagestring($image, 4, $padding + 15, $currentY + 15, $sectionIcon . " " . $sectionNameEn, $headerBg);
        }
        
        $currentY += $sectionHeight + 10;
        
        // 绘制metrics
        $metricIndex = 0;
        foreach ($section['metrics'] as $key => $value) {
            $keyEn = translateMetricKey($key);
            $text = "{$keyEn}: {$value}";
            
            // 根据内容选择颜色和图标
            $color = $textColor;
            $icon = "•";
            $bgRect = false;
            
            if ($value === '✓') {
                $color = $successColor;
                $icon = "✓";
                $text = "{$icon} {$keyEn}";
                $bgRect = true;
            } elseif ($value === '✗') {
                $color = $failColor;
                $icon = "✗";
                $text = "{$icon} {$keyEn}";
                $bgRect = true;
            }
            
            // 为带背景的项目绘制浅色背景
            if ($bgRect && $fontExists) {
                $bgAlpha = imagecolorallocatealpha($image, 
                    $value === '✓' ? 200 : 255, 
                    $value === '✓' ? 230 : 220, 
                    $value === '✓' ? 201 : 220, 
                    100
                );
                drawRoundedRect($image, $padding + 20, $currentY + 2, $padding + 300, $currentY + 28, 4, $bgAlpha, $bgAlpha);
            }
            
            // 绘制项目符号和文本
            if ($fontExists) {
                // 绘制渐变效果的圆点
                if (!$bgRect) {
                    imagefilledellipse($image, $padding + 30, $currentY + 14, 8, 8, $markerColor);
                    imagefilledellipse($image, $padding + 30, $currentY + 14, 6, 6, $color);
                }
                
                // 绘制文本
                imagettftext($image, 11, 0, $padding + ($bgRect ? 35 : 42), $currentY + 19, $bgRect ? $color : $textColor, $fontFile, $text);
            } else {
                imagestring($image, 3, $padding + 20, $currentY + 5, $icon . " " . $text, $color);
            }
            
            $currentY += $metricsLineHeight;
            $metricIndex++;
        }
        
        $currentY += 15;
    }
    
    // 添加现代化底部区域
    $footerY = $height - 40;
    imagefilledrectangle($image, 0, $footerY, $width, $height, $headerBgDark);
    
    // 底部装饰元素
    for ($i = 0; $i < 5; $i++) {
        $x = $width - 100 + ($i * 15);
        $size = 6 - $i;
        imagefilledellipse($image, $x, $footerY + 20, $size, $size, $accentColor);
    }
    
    // 水印和版权信息
    $watermark = "⚡ Powered by bench.nodeloc.cc";
    if ($fontExists) {
        imagettftext($image, 10, 0, $padding, $footerY + 25, $whiteColor, $fontFile, $watermark);
        // 右侧添加小图标
        $rightText = "📊 NodeLoc.com";
        imagettftext($image, 9, 0, $width - 150, $footerY + 25, $whiteColor, $fontFile, $rightText);
    } else {
        imagestring($image, 2, $padding, $footerY + 15, $watermark, $whiteColor);
        imagestring($image, 2, $width - 120, $footerY + 15, "NodeLoc.com", $whiteColor);
    }
    
    // 输出图片
    imagepng($image);
    imagedestroy($image);
}

/**
 * 绘制圆角矩形
 */
function drawRoundedRect($image, $x1, $y1, $x2, $y2, $radius, $fillColor, $borderColor) {
    // 填充主体
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $fillColor);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $fillColor);
    
    // 四个角（圆角效果）
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $fillColor);
    
    // 边框
    imagerectangle($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $borderColor);
    imagerectangle($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $borderColor);
    imagerectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $borderColor);
    imagerectangle($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $borderColor);
}

/**
 * 生成错误图片
 */
function generateErrorImage($message) {
    $width = 650;
    $height = 250;
    
    $image = imagecreatetruecolor($width, $height);
    $bgColor = imagecolorallocate($image, 248, 249, 250);
    $errorBg = imagecolorallocate($image, 255, 235, 238);
    $textColor = imagecolorallocate($image, 211, 47, 47);
    $borderColor = imagecolorallocate($image, 239, 154, 154);
    $darkText = imagecolorallocate($image, 33, 33, 33);
    
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
    
    // 错误框
    drawRoundedRect($image, 20, 40, $width - 20, $height - 40, 10, $errorBg, $borderColor);
    
    // 查找字体文件
    $fontPaths = [
        __DIR__ . '/fonts/DejaVuSans.ttf',
        __DIR__ . '/DejaVuSans.ttf',
        '/www/wwwroot/bench.nodeloc.cc/fonts/DejaVuSans.ttf',
    ];
    
    $fontFile = null;
    foreach ($fontPaths as $path) {
        if (@file_exists($path)) {
            $fontFile = $path;
            break;
        }
    }
    
    // 翻译错误消息为英文
    $messageEn = translateErrorMessage($message);
    
    if ($fontFile) {
        // 错误图标
        imagettftext($image, 32, 0, 40, 110, $textColor, $fontFile, "⚠");
        // 错误消息
        imagettftext($image, 18, 0, 90, 110, $textColor, $fontFile, $messageEn);
        // 提示信息
        imagettftext($image, 11, 0, 90, 140, $darkText, $fontFile, "Please check your request and try again");
    } else {
        imagestring($image, 5, 40, 90, "ERROR:", $textColor);
        imagestring($image, 4, 40, 120, $messageEn, $darkText);
        imagestring($image, 3, 40, 150, "Please check your request", $darkText);
    }
    
    imagepng($image);
    imagedestroy($image);
}

/**
 * 翻译错误消息为英文
 */
function translateErrorMessage($message) {
    $translations = [
        '错误: 未指定文件' => 'Error: No file specified',
        '错误: 文件不存在' => 'Error: File not found',
        '错误: 无法读取文件' => 'Error: Cannot read file',
    ];
    
    foreach ($translations as $cn => $en) {
        if (strpos($message, $cn) !== false) {
            return $en;
        }
    }
    
    return $message;
}
