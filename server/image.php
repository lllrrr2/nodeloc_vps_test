<?php
/**
 * VPS测试报告图片生成器 - Imagick版本
 * 使用Imagick生成包含中文的美观图片
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

error_log("=== Imagick Image generation started ===");
error_log("GET: " . print_r($_GET, true));

// 检查Imagick扩展
if (!extension_loaded('imagick')) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    die("错误: 需要安装 php-imagick 扩展\n安装: sudo apt-get install php-imagick && sudo systemctl restart php-fpm nginx");
}

mb_internal_encoding('UTF-8');
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');

$filePath = basename($_GET['file'] ?? '');
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

if (empty($filePath)) {
    error_log("ERROR: No file specified");
    generateErrorImage("错误: 未指定文件");
    exit;
}

$fullPath = __DIR__ . "/{$year}/{$month}/{$filePath}";
error_log("Reading: " . $fullPath);

if (!file_exists($fullPath)) {
    error_log("ERROR: File not found");
    generateErrorImage("错误: 文件不存在");
    exit;
}

$content = file_get_contents($fullPath);
if ($content === false) {
    generateErrorImage("错误: 无法读取文件");
    exit;
}

$data = parseTestResults($content);
error_log("Parsed " . count($data['sections']) . " sections");

try {
    generateResultImage($data);
    error_log("=== Image generated successfully ===");
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    generateErrorImage("生成失败: " . $e->getMessage());
}

// ============ 解析函数 ============

function parseTestResults($content) {
    $data = ['timestamp' => date('Y-m-d H:i:s'), 'sections' => []];
    preg_match_all('/\[tab="([^"]+)"\](.*?)\[\/tab\]/s', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $tabName = $match[1];
        $tabContent = trim(str_replace(['```', '`'], '', $match[2]));
        $data['sections'][$tabName] = parseSectionContent($tabName, $tabContent);
    }
    
    return $data;
}

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

function parseYABS($content) {
    $metrics = [];
    if (preg_match('/Processor\s*:\s*(.+)/i', $content, $match)) {
        $metrics['CPU'] = trim($match[1]);
    }
    if (preg_match('/CPU cores\s*:\s*(\d+)/i', $content, $match)) {
        $metrics['CPU Cores'] = $match[1];
    }
    if (preg_match('/RAM\s*:\s*(.+)/i', $content, $match)) {
        $metrics['Memory'] = trim($match[1]);
    }
    if (preg_match('/Disk\s*:\s*(.+)/i', $content, $match)) {
        $metrics['Disk'] = trim($match[1]);
    }
    if (preg_match('/Total\s*\|\s*(\d+\.?\d*)\s*(MB\/s|GB\/s)/i', $content, $match)) {
        $metrics['Disk I/O'] = $match[1] . ' ' . $match[2];
    }
    return $metrics;
}

function parseIPQuality($content) {
    $metrics = [];
    if (preg_match('/IP类型[：:]*\s*(.+)/u', $content, $match)) {
        $metrics['IP类型'] = trim($match[1]);
    }
    if (preg_match('/自治系统号[：:]*\s*(AS\d+)/u', $content, $match)) {
        $metrics['ASN'] = trim($match[1]);
    }
    if (preg_match('/IP2Location[：:]*\s*(\d+)\|(.+)/u', $content, $match)) {
        $metrics['风险评分'] = $match[1] . ' (' . trim($match[2]) . ')';
    }
    return $metrics;
}

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
            } elseif (stripos($status, '失败') !== false || stripos($status, 'No') !== false) {
                $metrics[$serviceName] = '✗';
            }
        }
    }
    
    $unlocked = count(array_filter($metrics, function($v) { return $v === '✓'; }));
    $total = count($metrics);
    if ($total > 0) {
        $metrics['汇总'] = "$unlocked/$total 解锁";
    }
    
    return $metrics;
}

function parseSpeedTest($content) {
    $metrics = [];
    preg_match_all('/(\d+\.?\d*)\s*(Mbps|MB\/s).*?(\d+\.?\d*)\s*(Mbps|MB\/s)/i', $content, $matches, PREG_SET_ORDER);
    
    if (!empty($matches)) {
        $avgDown = 0;
        $avgUp = 0;
        $count = count($matches);
        
        foreach ($matches as $match) {
            $down = floatval($match[1]);
            $up = floatval($match[3]);
            
            if (stripos($match[2], 'MB/s') !== false) $down *= 8;
            if (stripos($match[4], 'MB/s') !== false) $up *= 8;
            
            $avgDown += $down;
            $avgUp += $up;
        }
        
        if ($count > 0) {
            $metrics['平均下载'] = round($avgDown / $count, 2) . ' Mbps';
            $metrics['平均上传'] = round($avgUp / $count, 2) . ' Mbps';
            $metrics['测试节点'] = $count;
        }
    }
    
    return $metrics;
}

function parseResponse($content) {
    $metrics = [];
    if (preg_match('/平均.*?(\d+)ms/i', $content, $match)) {
        $metrics['平均延迟'] = $match[1] . ' ms';
    }
    return $metrics;
}

function parseRouteTrace($content) {
    $metrics = [];
    preg_match_all('/No:(\d+)\/9 Traceroute to ([^\n]+)/u', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $routeNum = $match[1];
        $destination = $match[2];
        $metrics["路由 $routeNum"] = $destination;
    }
    
    return $metrics;
}

// ============ 图片生成 ============

function generateResultImage($data) {
    $width = 1200;
    $padding = 25;
    
    // 创建draw对象
    $draw = new ImagickDraw();
    
    // 查找中文字体
    $fontFile = findChineseFont();
    if ($fontFile) {
        $draw->setFont($fontFile);
        error_log("Using font: " . $fontFile);
    } else {
        error_log("WARNING: No Chinese font found, text may not display correctly");
    }
    
    // 预计算高度
    $sections = $data['sections'];
    $estimatedHeight = 200; // 标题
    $estimatedHeight += count($sections['YABS']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['IP质量']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['流媒体']['metrics'] ?? []) > 0 ? 250 : 0;
    $estimatedHeight += count($sections['多线程测速']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['单线程测速']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['响应']['metrics'] ?? []) > 0 ? 100 : 0;
    $estimatedHeight += count($sections['回程路由']['metrics'] ?? []) > 0 ? 350 : 0;
    $estimatedHeight += 100; // 底部
    
    // 创建图片
    $image = new Imagick();
    $image->newImage($width, $estimatedHeight, new ImagickPixel('#F8F9FA'));
    $image->setImageFormat('png');
    
    $currentY = 0;
    
    // 绘制标题
    $currentY = drawHeader($image, $draw, $width, $data['timestamp']);
    $currentY += 30;
    
    // 1. YABS信息
    if (!empty($sections['YABS']['metrics'])) {
        $currentY = drawSection($image, $draw, $padding, $currentY, $width, 
                                "📊 系统信息", $sections['YABS']['metrics'], 'info');
        $currentY += 30;
    }
    
    // 2. IP质量
    if (!empty($sections['IP质量']['metrics'])) {
        $currentY = drawSection($image, $draw, $padding, $currentY, $width,
                                "🌐 IP质量", $sections['IP质量']['metrics'], 'info');
        $currentY += 30;
    }
    
    // 3. 流媒体
    if (!empty($sections['流媒体']['metrics'])) {
        $currentY = drawSection($image, $draw, $padding, $currentY, $width,
                                "🎬 流媒体解锁", $sections['流媒体']['metrics'], 'grid');
        $currentY += 30;
    }
    
    // 4. 多线程测速
    if (!empty($sections['多线程测速']['metrics'])) {
        $currentY = drawSection($image, $draw, $padding, $currentY, $width,
                                "🚀 多线程测速", $sections['多线程测速']['metrics'], 'bar');
        $currentY += 30;
    }
    
    // 5. 单线程测速
    if (!empty($sections['单线程测速']['metrics'])) {
        $currentY = drawSection($image, $draw, $padding, $currentY, $width,
                                "📈 单线程测速", $sections['单线程测速']['metrics'], 'bar');
        $currentY += 30;
    }
    
    // 6. 响应测试
    if (!empty($sections['响应']['metrics'])) {
        $currentY = drawSection($image, $draw, $padding, $currentY, $width,
                                "⚡ 响应测试", $sections['响应']['metrics'], 'list');
        $currentY += 30;
    }
    
    // 7. 回程路由
    if (!empty($sections['回程路由']['metrics'])) {
        $currentY = drawSection($image, $draw, $padding, $currentY, $width,
                                "🔄 回程路由 (9条)", $sections['回程路由']['metrics'], 'routes');
        $currentY += 30;
    }
    
    // 裁剪到实际高度
    $finalHeight = $currentY + 80;
    $finalImage = new Imagick();
    $finalImage->newImage($width, $finalHeight, new ImagickPixel('#F8F9FA'));
    $finalImage->setImageFormat('png');
    $finalImage->compositeImage($image, Imagick::COMPOSITE_OVER, 0, 0);
    $image->destroy();
    
    // 绘制底部
    drawFooter($finalImage, $draw, $width, $finalHeight);
    
    // 输出
    echo $finalImage->getImageBlob();
    $finalImage->destroy();
}

function drawHeader($image, $draw, $width, $timestamp) {
    $headerHeight = 120;
    
    // 设置字体
    $fontFile = findChineseFont();
    error_log("[drawHeader] Font file: " . ($fontFile ? $fontFile : 'NULL'));
    if ($fontFile) {
        try {
            $draw->setFont($fontFile);
            error_log("[drawHeader] Font set successfully");
        } catch (Exception $e) {
            error_log("[drawHeader] Font set failed: " . $e->getMessage());
        }
    }
    
    // 渐变背景
    $headerDraw = new ImagickDraw();
    $headerDraw->setFillColor('#1A73E8');
    $headerDraw->rectangle(0, 0, $width, $headerHeight);
    $image->drawImage($headerDraw);
    
    // 标题
    $draw->setFillColor('#FFFFFF');
    $draw->setFontSize(28);
    $draw->setFontWeight(700);
    $image->annotateImage($draw, 75, 50, 0, "NodeLoc VPS 性能测试报告");
    error_log("[drawHeader] Title drawn");
    
    // 副标题
    $draw->setFontSize(14);
    $draw->setFontWeight(400);
    $image->annotateImage($draw, 75, 80, 0, "生成时间: " . $timestamp);
    error_log("[drawHeader] Subtitle drawn");
    
    // 装饰圆圈
    $headerDraw->setFillColor('#FFA726');
    $headerDraw->circle($width - 60, 40, $width - 40, 40);
    $image->drawImage($headerDraw);
    
    return $headerHeight;
}

function drawSection($image, $draw, $x, $y, $width, $title, $metrics, $type) {
    // 设置字体
    $fontFile = findChineseFont();
    if ($fontFile) {
        $draw->setFont($fontFile);
    }
    
    // 绘制section标题
    $draw->setFillColor('#1A73E8');
    $draw->setFontSize(18);
    $draw->setFontWeight(700);
    $image->annotateImage($draw, $x, $y + 20, 0, $title);
    
    $y += 40;
    
    switch ($type) {
        case 'info':
            return drawInfoCards($image, $draw, $x, $y, $width, $metrics);
        case 'grid':
            return drawStreamingGrid($image, $draw, $x, $y, $width, $metrics);
        case 'bar':
            return drawBarChart($image, $draw, $x, $y, $width, $metrics);
        case 'list':
            return drawList($image, $draw, $x, $y, $metrics);
        case 'routes':
            return drawRouteGrid($image, $draw, $x, $y, $width, $metrics);
    }
    
    return $y;
}

function drawInfoCards($image, $draw, $x, $y, $width, $metrics) {
    $cardWidth = 270;
    $cardHeight = 100;
    $spacing = 20;
    $col = 0;
    $currentX = $x;
    $currentY = $y;
    
    foreach ($metrics as $key => $value) {
        // 绘制卡片背景
        $cardDraw = new ImagickDraw();
        $cardDraw->setFillColor('#FFFFFF');
        $cardDraw->setStrokeColor('#E0E0E0');
        $cardDraw->setStrokeWidth(1);
        $cardDraw->roundRectangle($currentX, $currentY, $currentX + $cardWidth, $currentY + $cardHeight, 10, 10);
        $image->drawImage($cardDraw);
        
        // 顶部色条
        $cardDraw->setFillColor('#42A5F5');
        $cardDraw->rectangle($currentX + 1, $currentY + 1, $currentX + $cardWidth - 1, $currentY + 5);
        $image->drawImage($cardDraw);
        
        // 标题
        $draw->setFillColor('#757575');
        $draw->setFontSize(11);
        $image->annotateImage($draw, $currentX + 15, $currentY + 35, 0, $key);
        
        // 数值 - 限制长度
        $displayValue = mb_strlen($value) > 30 ? mb_substr($value, 0, 27) . '...' : $value;
        $draw->setFillColor('#212121');
        $draw->setFontSize(13);
        $image->annotateImage($draw, $currentX + 15, $currentY + 65, 0, $displayValue);
        
        $col++;
        if ($col >= 4) {
            $col = 0;
            $currentX = $x;
            $currentY += $cardHeight + $spacing;
        } else {
            $currentX += $cardWidth + $spacing;
        }
    }
    
    if ($col > 0) {
        $currentY += $cardHeight + $spacing;
    }
    
    return $currentY;
}

function drawStreamingGrid($image, $draw, $x, $y, $width, $metrics) {
    $itemWidth = 180;
    $itemHeight = 50;
    $cols = 3;
    $spacing = 15;
    $col = 0;
    $currentX = $x;
    $currentY = $y;
    
    foreach ($metrics as $service => $status) {
        if ($service === '汇总') continue;
        
        $isSuccess = ($status === '✓');
        $bgColor = $isSuccess ? '#E8F5E9' : '#FFEBEE';
        $textColor = $isSuccess ? '#4CAF50' : '#F44336';
        
        // 绘制卡片
        $cardDraw = new ImagickDraw();
        $cardDraw->setFillColor($bgColor);
        $cardDraw->setStrokeColor('#E0E0E0');
        $cardDraw->setStrokeWidth(1);
        $cardDraw->roundRectangle($currentX, $currentY, $currentX + $itemWidth, $currentY + $itemHeight, 8, 8);
        $image->drawImage($cardDraw);
        
        // 图标
        $draw->setFillColor($textColor);
        $draw->setFontSize(20);
        $image->annotateImage($draw, $currentX + 15, $currentY + 35, 0, $status);
        
        // 服务名
        $draw->setFillColor('#212121');
        $draw->setFontSize(12);
        $image->annotateImage($draw, $currentX + 50, $currentY + 35, 0, $service);
        
        $col++;
        if ($col >= $cols) {
            $col = 0;
            $currentX = $x;
            $currentY += $itemHeight + $spacing;
        } else {
            $currentX += $itemWidth + $spacing;
        }
    }
    
    if ($col > 0) {
        $currentY += $itemHeight + $spacing;
    }
    
    return $currentY;
}

function drawBarChart($image, $draw, $x, $y, $width, $metrics) {
    $barHeight = 35;
    $spacing = 15;
    $currentY = $y;
    
    // 找最大值
    $maxValue = 0;
    foreach ($metrics as $key => $value) {
        if ($key === '平均下载' || $key === '平均上传') {
            $numValue = floatval(preg_replace('/[^0-9.]/', '', $value));
            if ($numValue > $maxValue) $maxValue = $numValue;
        }
    }
    
    if ($maxValue == 0) $maxValue = 100;
    
    foreach ($metrics as $key => $value) {
        if ($key !== '平均下载' && $key !== '平均上传') continue;
        
        // 背景
        $cardDraw = new ImagickDraw();
        $cardDraw->setFillColor('#FFFFFF');
        $cardDraw->setStrokeColor('#E0E0E0');
        $cardDraw->setStrokeWidth(1);
        $cardDraw->roundRectangle($x, $currentY, $x + $width - 50, $currentY + $barHeight, 6, 6);
        $image->drawImage($cardDraw);
        
        // 标签
        $draw->setFillColor('#212121');
        $draw->setFontSize(12);
        $image->annotateImage($draw, $x + 15, $currentY + 22, 0, $key);
        
        // 条形
        $numValue = floatval(preg_replace('/[^0-9.]/', '', $value));
        $barWidth = ($numValue / $maxValue) * ($width - 300);
        
        $barDraw = new ImagickDraw();
        $barDraw->setFillColor('#42A5F5');
        $barDraw->roundRectangle($x + 120, $currentY + 8, $x + 120 + $barWidth, $currentY + $barHeight - 8, 4, 4);
        $image->drawImage($barDraw);
        
        // 数值
        $draw->setFillColor('#212121');
        $draw->setFontSize(12);
        $image->annotateImage($draw, $x + 130 + $barWidth, $currentY + 22, 0, $value);
        
        $currentY += $barHeight + $spacing;
    }
    
    return $currentY + 10;
}

function drawList($image, $draw, $x, $y, $metrics) {
    $currentY = $y;
    
    foreach ($metrics as $key => $value) {
        $draw->setFillColor('#212121');
        $draw->setFontSize(13);
        $image->annotateImage($draw, $x + 20, $currentY + 20, 0, "$key: $value");
        $currentY += 30;
    }
    
    return $currentY;
}

function drawRouteGrid($image, $draw, $x, $y, $width, $metrics) {
    $itemWidth = 370;
    $itemHeight = 70;
    $cols = 3;
    $spacing = 15;
    $col = 0;
    $currentX = $x;
    $currentY = $y;
    
    foreach ($metrics as $label => $destination) {
        // 确定颜色
        $color = '#42A5F5'; // 默认蓝色
        if (stripos($destination, '电信') !== false) $color = '#42A5F5';
        elseif (stripos($destination, '联通') !== false) $color = '#66BB6A';
        elseif (stripos($destination, '移动') !== false) $color = '#FFA726';
        
        // 绘制卡片
        $cardDraw = new ImagickDraw();
        $cardDraw->setFillColor('#FFFFFF');
        $cardDraw->setStrokeColor('#E0E0E0');
        $cardDraw->setStrokeWidth(1);
        $cardDraw->roundRectangle($currentX, $currentY, $currentX + $itemWidth, $currentY + $itemHeight, 8, 8);
        $image->drawImage($cardDraw);
        
        // 顶部色条
        $cardDraw->setFillColor($color);
        $cardDraw->rectangle($currentX + 1, $currentY + 1, $currentX + $itemWidth - 1, $currentY + 5);
        $image->drawImage($cardDraw);
        
        // 路由编号
        $draw->setFillColor($color);
        $draw->setFontSize(12);
        $draw->setFontWeight(700);
        $image->annotateImage($draw, $currentX + 15, $currentY + 28, 0, $label);
        
        // 目的地 - 自动换行
        $draw->setFillColor('#212121');
        $draw->setFontSize(10);
        $draw->setFontWeight(400);
        $maxLen = 48;
        if (mb_strlen($destination) > $maxLen) {
            $line1 = mb_substr($destination, 0, $maxLen);
            $line2 = mb_substr($destination, $maxLen);
            $image->annotateImage($draw, $currentX + 15, $currentY + 48, 0, $line1);
            $image->annotateImage($draw, $currentX + 15, $currentY + 62, 0, $line2);
        } else {
            $image->annotateImage($draw, $currentX + 15, $currentY + 48, 0, $destination);
        }
        
        $col++;
        if ($col >= $cols) {
            $col = 0;
            $currentX = $x;
            $currentY += $itemHeight + $spacing;
        } else {
            $currentX += $itemWidth + $spacing;
        }
    }
    
    if ($col > 0) {
        $currentY += $itemHeight + $spacing;
    }
    
    return $currentY;
}

function drawFooter($image, $draw, $width, $height) {
    $footerY = $height - 50;
    
    // 设置字体
    $fontFile = findChineseFont();
    if ($fontFile) {
        $draw->setFont($fontFile);
    }
    
    // 底部背景
    $footerDraw = new ImagickDraw();
    $footerDraw->setFillColor('#0D47A1');
    $footerDraw->rectangle(0, $footerY, $width, $height);
    $image->drawImage($footerDraw);
    
    // 水印
    $draw->setFillColor('#FFFFFF');
    $draw->setFontSize(11);
    $image->annotateImage($draw, 25, $footerY + 30, 0, "Powered by bench.nodeloc.cc");
    $image->annotateImage($draw, $width - 150, $footerY + 30, 0, "NodeLoc.com");
}

function findChineseFont() {
    $fonts = [
        '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
        '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
        '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
        '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttf',
        __DIR__ . '/fonts/wqy-zenhei.ttc',
        __DIR__ . '/fonts/NotoSansCJK-Regular.ttf',
    ];
    
    foreach ($fonts as $font) {
        if (file_exists($font)) {
            return $font;
        }
    }
    
    return null;
}

function generateErrorImage($message) {
    try {
        $image = new Imagick();
        $image->newImage(650, 250, new ImagickPixel('#F8F9FA'));
        $image->setImageFormat('png');
        
        $draw = new ImagickDraw();
        $draw->setFillColor('#D32F2F');
        $draw->setFontSize(18);
        $draw->annotation(50, 120, $message);
        
        $image->drawImage($draw);
        echo $image->getImageBlob();
        $image->destroy();
    } catch (Exception $e) {
        header('Content-Type: text/plain');
        echo "错误: " . $message;
    }
}
