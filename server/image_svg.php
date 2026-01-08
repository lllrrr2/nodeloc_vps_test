<?php
/**
 * VPS测试报告图片生成器 - SVG版本
 * 使用SVG生成矢量图形，更清晰、更小、更易渲染
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

error_log("=== SVG Image generation started ===");
error_log("GET: " . print_r($_GET, true));

mb_internal_encoding('UTF-8');
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$filePath = basename($_GET['file'] ?? '');
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

if (empty($filePath)) {
    error_log("ERROR: No file specified");
    generateErrorSVG("错误: 未指定文件");
    exit;
}

$fullPath = __DIR__ . "/{$year}/{$month}/{$filePath}";
error_log("Reading: " . $fullPath);

if (!file_exists($fullPath)) {
    error_log("ERROR: File not found");
    generateErrorSVG("错误: 文件不存在");
    exit;
}

$content = file_get_contents($fullPath);
if ($content === false) {
    generateErrorSVG("错误: 无法读取文件");
    exit;
}

// 复用解析函数
require_once __DIR__ . '/image.php';

$data = parseTestResults($content);
error_log("Parsed " . count($data['sections']) . " sections");

try {
    generateSVGImage($data);
    error_log("=== SVG generated successfully ===");
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    generateErrorSVG("生成失败: " . $e->getMessage());
}

// ============ SVG生成函数 ============

function generateSVGImage($data) {
    $width = 1200;
    $sections = $data['sections'];
    
    // 预计算高度
    $estimatedHeight = 200; // 标题
    $estimatedHeight += count($sections['YABS']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['IP质量']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['流媒体']['metrics'] ?? []) > 0 ? 250 : 0;
    $estimatedHeight += count($sections['多线程测速']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['单线程测速']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['响应']['metrics'] ?? []) > 0 ? 100 : 0;
    $estimatedHeight += count($sections['回程路由']['metrics'] ?? []) > 0 ? 350 : 0;
    $estimatedHeight += 100;
    
    $svg = [];
    $svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $estimatedHeight . '" viewBox="0 0 ' . $width . ' ' . $estimatedHeight . '">';
    
    // 定义样式和渐变
    $svg[] = '<defs>';
    
    // 渐变定义
    $svg[] = '<linearGradient id="headerGradient" x1="0%" y1="0%" x2="100%" y2="0%">';
    $svg[] = '  <stop offset="0%" style="stop-color:#1A73E8;stop-opacity:1" />';
    $svg[] = '  <stop offset="100%" style="stop-color:#4A90E2;stop-opacity:1" />';
    $svg[] = '</linearGradient>';
    
    $svg[] = '<linearGradient id="footerGradient" x1="0%" y1="0%" x2="100%" y2="0%">';
    $svg[] = '  <stop offset="0%" style="stop-color:#0D47A1;stop-opacity:1" />';
    $svg[] = '  <stop offset="100%" style="stop-color:#1565C0;stop-opacity:1" />';
    $svg[] = '</linearGradient>';
    
    $svg[] = '<linearGradient id="cardGradient" x1="0%" y1="0%" x2="0%" y2="100%">';
    $svg[] = '  <stop offset="0%" style="stop-color:#FFFFFF;stop-opacity:1" />';
    $svg[] = '  <stop offset="100%" style="stop-color:#FAFAFA;stop-opacity:1" />';
    $svg[] = '</linearGradient>';
    
    // 阴影滤镜
    $svg[] = '<filter id="cardShadow" x="-50%" y="-50%" width="200%" height="200%">';
    $svg[] = '  <feGaussianBlur in="SourceAlpha" stdDeviation="3"/>';
    $svg[] = '  <feOffset dx="0" dy="2" result="offsetblur"/>';
    $svg[] = '  <feComponentTransfer><feFuncA type="linear" slope="0.15"/></feComponentTransfer>';
    $svg[] = '  <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>';
    $svg[] = '</filter>';
    
    $svg[] = '<filter id="headerShadow" x="-50%" y="-50%" width="200%" height="200%">';
    $svg[] = '  <feGaussianBlur in="SourceAlpha" stdDeviation="4"/>';
    $svg[] = '  <feOffset dx="0" dy="3" result="offsetblur"/>';
    $svg[] = '  <feComponentTransfer><feFuncA type="linear" slope="0.2"/></feComponentTransfer>';
    $svg[] = '  <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>';
    $svg[] = '</filter>';
    
    // 样式
    $svg[] = '<style type="text/css"><![CDATA[';
    $svg[] = '@import url("https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;600;700&display=swap");';
    $svg[] = 'text { font-family: "Noto Sans SC", sans-serif; }';
    $svg[] = '.title { font-size: 28px; font-weight: 700; fill: #FFFFFF; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }';
    $svg[] = '.subtitle { font-size: 14px; font-weight: 400; fill: rgba(255,255,255,0.9); }';
    $svg[] = '.section-title { font-size: 18px; font-weight: 700; fill: #1A73E8; }';
    $svg[] = '.card-label { font-size: 11px; fill: #757575; font-weight: 500; }';
    $svg[] = '.card-value { font-size: 13px; fill: #212121; font-weight: 600; }';
    $svg[] = '.card-value-large { font-size: 13px; font-weight: 600; fill: #212121; }';
    $svg[] = '.icon { font-size: 22px; }';
    $svg[] = '.footer-text { font-size: 11px; fill: #FFFFFF; }';
    $svg[] = ']]></style>';
    $svg[] = '</defs>';
    
    // 背景
    $svg[] = '<rect width="' . $width . '" height="' . $estimatedHeight . '" fill="#F8F9FA"/>';
    
    $currentY = 0;
    
    // 绘制标题
    $currentY = svgDrawHeader($svg, $width, $data['timestamp'], $currentY);
    $currentY += 20;
    
    $padding = 25;
    
    // 1. YABS信息
    if (!empty($sections['YABS']['metrics'])) {
        $currentY = svgDrawSection($svg, $padding, $currentY, $width, 
                                "📊 系统信息", $sections['YABS']['metrics'], 'info');
        $currentY += 20;
    }
    
    // 2. IP质量（合并服务器信息）
    if (!empty($sections['IP质量']['metrics']) || !empty($sections['回程路由']['metrics']['_server_info'])) {
        $ipMetrics = $sections['IP质量']['metrics'] ?? [];
        
        if (!empty($sections['回程路由']['metrics']['_server_info'])) {
            $serverInfo = $sections['回程路由']['metrics']['_server_info'];
            $ipMetrics['国家'] = $serverInfo['country'];
            $ipMetrics['城市'] = $serverInfo['city'];
            $ipMetrics['服务商'] = $serverInfo['provider'];
        }
        
        $currentY = svgDrawSection($svg, $padding, $currentY, $width,
                                "🌐 IP质量", $ipMetrics, 'ipquality');
        $currentY += 20;
    }
    
    // 3. 流媒体
    if (!empty($sections['流媒体']['metrics'])) {
        $currentY = svgDrawSection($svg, $padding, $currentY, $width,
                                "🎬 流媒体解锁", $sections['流媒体']['metrics'], 'streaming');
        $currentY += 20;
    }
    
    // 4. 测速
    if (!empty($sections['多线程测速']['metrics']) || !empty($sections['单线程测速']['metrics'])) {
        $currentY = svgDrawDualSpeedTest($svg, $padding, $currentY, $width,
                                      $sections['多线程测速']['metrics'] ?? [],
                                      $sections['单线程测速']['metrics'] ?? []);
        $currentY += 20;
    }
    
    // 5. 响应测试
    if (!empty($sections['响应']['metrics'])) {
        $currentY = svgDrawSection($svg, $padding, $currentY, $width,
                                "⚡ 响应测试", $sections['响应']['metrics'], 'list');
        $currentY += 20;
    }
    
    // 6. 回程路由
    if (!empty($sections['回程路由']['metrics'])) {
        $routeCount = count(array_filter(array_keys($sections['回程路由']['metrics']), function($k) {
            return $k !== '_server_info';
        }));
        $currentY = svgDrawSection($svg, $padding, $currentY, $width,
                                "🔄 回程路由 ({$routeCount}条)", $sections['回程路由']['metrics'], 'routes');
        $currentY += 20;
    }
    
    // 绘制底部
    svgDrawFooter($svg, $width, $currentY + 40);
    
    $svg[] = '</svg>';
    
    echo implode("\n", $svg);
}

function svgDrawHeader(&$svg, $width, $timestamp, $y) {
    $headerHeight = 90;
    
    // 渐变背景
    $svg[] = '<rect x="0" y="' . $y . '" width="' . $width . '" height="' . $headerHeight . '" fill="url(#headerGradient)" filter="url(#headerShadow)"/>';
    
    // 装饰圆圈带渐变
    $svg[] = '<defs><radialGradient id="circleGradient"><stop offset="0%" style="stop-color:#FFB74D"/><stop offset="100%" style="stop-color:#FF9800"/></radialGradient></defs>';
    $svg[] = '<circle cx="' . ($width - 60) . '" cy="' . ($y + 40) . '" r="20" fill="url(#circleGradient)" opacity="0.9"/>';
    $svg[] = '<circle cx="' . ($width - 60) . '" cy="' . ($y + 40) . '" r="15" fill="none" stroke="#FFFFFF" stroke-width="2" opacity="0.5"/>';
    
    // 装饰线条
    $svg[] = '<line x1="60" y1="' . ($y + 75) . '" x2="' . ($width - 100) . '" y2="' . ($y + 75) . '" stroke="#FFFFFF" stroke-width="1" opacity="0.3"/>';
    
    // 标题
    $svg[] = '<text x="75" y="' . ($y + 40) . '" class="title">VPS Performance Test Report</text>';
    
    // 副标题
    $svg[] = '<text x="75" y="' . ($y + 65) . '" class="subtitle">Generated: ' . htmlspecialchars($timestamp) . '</text>';
    
    return $y + $headerHeight;
}

function svgDrawSection(&$svg, $x, $y, $width, $title, $metrics, $type) {
    // Section标题
    $svg[] = '<text x="' . $x . '" y="' . ($y + 18) . '" class="section-title">' . htmlspecialchars($title) . '</text>';
    $y += 32;
    
    switch ($type) {
        case 'info':
            return svgDrawInfoCards($svg, $x, $y, $metrics);
        case 'ipquality':
            return svgDrawIPQualityCards($svg, $x, $y, $metrics);
        case 'streaming':
            return svgDrawStreamingGrid($svg, $x, $y, $metrics);
        case 'list':
            return svgDrawList($svg, $x, $y, $metrics);
        case 'routes':
            return svgDrawRouteGrid($svg, $x, $y, $metrics);
    }
    
    return $y;
}

function svgDrawInfoCards(&$svg, $x, $y, $metrics) {
    $cardWidth = 220;
    $cardHeight = 70;
    $spacing = 10;
    $cols = 5;
    $col = 0;
    $currentX = $x;
    $currentY = $y;
    
    foreach ($metrics as $key => $value) {
        // 卡片背景带阴影
        $svg[] = '<rect x="' . $currentX . '" y="' . $currentY . '" width="' . $cardWidth . '" height="' . $cardHeight . '" rx="8" fill="url(#cardGradient)" stroke="#E3F2FD" stroke-width="1.5" filter="url(#cardShadow)"/>';
        
        // 顶部渐变色条
        $svg[] = '<defs><linearGradient id="barBlue' . $col . '" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:#42A5F5"/><stop offset="100%" style="stop-color:#64B5F6"/></linearGradient></defs>';
        $svg[] = '<rect x="' . ($currentX + 1) . '" y="' . ($currentY + 1) . '" width="' . ($cardWidth - 2) . '" height="4" rx="8 8 0 0" fill="url(#barBlue' . $col . ')"/>';
        
        // 标签
        $svg[] = '<text x="' . ($currentX + 12) . '" y="' . ($currentY + 25) . '" class="card-label">' . htmlspecialchars($key) . '</text>';
        
        // 数值
        $displayValue = mb_strlen($value) > 28 ? mb_substr($value, 0, 25) . '...' : $value;
        $svg[] = '<text x="' . ($currentX + 12) . '" y="' . ($currentY + 48) . '" class="card-value">' . htmlspecialchars($displayValue) . '</text>';
        
        $col++;
        if ($col >= $cols) {
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

function svgDrawIPQualityCards(&$svg, $x, $y, $metrics) {
    $cardWidth = 185;
    $cardHeight = 70;
    $spacing = 10;
    $cols = 6;
    $col = 0;
    $currentX = $x;
    $currentY = $y;
    
    foreach ($metrics as $key => $value) {
        $svg[] = '<rect x="' . $currentX . '" y="' . $currentY . '" width="' . $cardWidth . '" height="' . $cardHeight . '" rx="8" fill="url(#cardGradient)" stroke="#E8F5E9" stroke-width="1.5" filter="url(#cardShadow)"/>';
        $svg[] = '<defs><linearGradient id="barGreen' . $col . '" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:#66BB6A"/><stop offset="100%" style="stop-color:#81C784"/></linearGradient></defs>';
        $svg[] = '<rect x="' . ($currentX + 1) . '" y="' . ($currentY + 1) . '" width="' . ($cardWidth - 2) . '" height="4" rx="8 8 0 0" fill="url(#barGreen' . $col . ')"/>';
        
        $svg[] = '<text x="' . ($currentX + 12) . '" y="' . ($currentY + 25) . '" class="card-label">' . htmlspecialchars($key) . '</text>';
        
        $displayValue = mb_strlen($value) > 22 ? mb_substr($value, 0, 19) . '...' : $value;
        $svg[] = '<text x="' . ($currentX + 12) . '" y="' . ($currentY + 48) . '" class="card-value">' . htmlspecialchars($displayValue) . '</text>';
        
        $col++;
        if ($col >= $cols) {
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

function svgDrawStreamingGrid(&$svg, $x, $y, $metrics) {
    $cardWidth = 185;
    $cardHeight = 60;
    $spacing = 8;
    $cols = 6;
    $col = 0;
    $currentX = $x;
    $currentY = $y;
    
    foreach ($metrics as $service => $status) {
        if ($service === '汇总') continue;
        
        $isSuccess = ($status === '✓' || $status === '解锁');
        $bgColor = $isSuccess ? '#E8F5E9' : '#FFEBEE';
        $iconColor = $isSuccess ? '#4CAF50' : '#F44336';
        $icon = $isSuccess ? '✓' : '✗';
        $strokeColor = $isSuccess ? '#81C784' : '#EF9A9A';
        
        $svg[] = '<rect x="' . $currentX . '" y="' . $currentY . '" width="' . $cardWidth . '" height="' . $cardHeight . '" rx="8" fill="' . $bgColor . '" stroke="' . $strokeColor . '" stroke-width="1.5" filter="url(#cardShadow)"/>';
        
        // 图标背景圆圈
        $svg[] = '<circle cx="' . ($currentX + 22) . '" cy="' . ($currentY + 30) . '" r="12" fill="' . $iconColor . '" opacity="0.15"/>';
        $svg[] = '<text x="' . ($currentX + 15) . '" y="' . ($currentY + 38) . '" class="icon" fill="' . $iconColor . '" font-weight="700">' . $icon . '</text>';
        $svg[] = '<text x="' . ($currentX + 45) . '" y="' . ($currentY + 38) . '" class="card-value">' . htmlspecialchars($service) . '</text>';
        
        $col++;
        if ($col >= $cols) {
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

function svgDrawDualSpeedTest(&$svg, $x, $y, $width, $multiMetrics, $singleMetrics) {
    $svg[] = '<text x="' . $x . '" y="' . ($y + 18) . '" class="section-title">🚀 测速结果</text>';
    $y += 35;
    
    $halfWidth = floor(($width - 50 - 30) / 2);
    
    $leftY = $y;
    $rightY = $y;
    
    if (!empty($multiMetrics)) {
        $svg[] = '<text x="' . $x . '" y="' . ($y + 15) . '" style="font-size: 13px; font-weight: 600; fill: #757575;">多线程</text>';
        $leftY = svgDrawSpeedBars($svg, $x, $y + 30, $halfWidth, $multiMetrics);
    }
    
    if (!empty($singleMetrics)) {
        $svg[] = '<text x="' . ($x + $halfWidth + 30) . '" y="' . ($y + 15) . '" style="font-size: 13px; font-weight: 600; fill: #757575;">单线程</text>';
        $rightY = svgDrawSpeedBars($svg, $x + $halfWidth + 30, $y + 30, $halfWidth, $singleMetrics);
    }
    
    return max($leftY, $rightY) + 10;
}

function svgDrawSpeedBars(&$svg, $x, $y, $width, $metrics) {
    $barHeight = 25;
    $spacing = 8;
    $currentY = $y;
    
    foreach ($metrics as $key => $value) {
        if ($key !== '平均下载' && $key !== '平均上传') continue;
        
        preg_match('/(\d+\.?\d*)\s*([MGT]?)(b|B)?/i', $value, $matches);
        $numValue = isset($matches[1]) ? floatval($matches[1]) : 0;
        $unit = isset($matches[2]) ? $matches[2] : '';
        
        if ($unit === 'G' || $unit === 'g') $numValue *= 1000;
        if ($unit === 'K' || $unit === 'k') $numValue /= 1000;
        
        $barWidth = min(($numValue / 1000) * ($width - 200), $width - 200);
        if ($barWidth < 10) $barWidth = 10;
        
        $gradientId = 'speedGradient' . $currentY;
        $svg[] = '<defs><linearGradient id="' . $gradientId . '" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:#42A5F5"/><stop offset="100%" style="stop-color:#1E88E5"/></linearGradient></defs>';
        $svg[] = '<rect x="' . ($x + 80) . '" y="' . $currentY . '" width="' . $barWidth . '" height="' . $barHeight . '" rx="4" fill="url(#' . $gradientId . ')" filter="url(#cardShadow)"/>';
        
        $labelText = str_replace('平均', '', $key);
        $svg[] = '<text x="' . ($x + 5) . '" y="' . ($currentY + 17) . '" style="font-size: 10px; fill: #212121;">' . htmlspecialchars($labelText) . '</text>';
        $svg[] = '<text x="' . ($x + 85) . '" y="' . ($currentY + 17) . '" style="font-size: 10px; font-weight: 700; fill: #1976D2;">' . htmlspecialchars($value) . '</text>';
        
        $currentY += $barHeight + $spacing;
    }
    
    return $currentY;
}

function svgDrawList(&$svg, $x, $y, $metrics) {
    $currentY = $y;
    
    foreach ($metrics as $key => $value) {
        $svg[] = '<text x="' . ($x + 20) . '" y="' . ($currentY + 20) . '" style="font-size: 13px; fill: #212121;">' . htmlspecialchars("$key: $value") . '</text>';
        $currentY += 30;
    }
    
    return $currentY;
}

function svgDrawRouteGrid(&$svg, $x, $y, $metrics) {
    $itemWidth = 280;
    $itemHeight = 85;
    $cols = 4;
    $spacing = 10;
    $col = 0;
    $currentX = $x;
    $currentY = $y;
    
    foreach ($metrics as $label => $routeData) {
        if ($label === '_server_info') continue;
        if (!is_array($routeData)) continue;
        
        $region = $routeData['region'] ?? '';
        $isp = $routeData['isp'] ?? '';
        $route = $routeData['route'] ?? '';
        $quality = $routeData['quality'] ?? '普通线路';
        
        $isHighQuality = (strpos($quality, '优质') !== false);
        
        if (strpos($isp, '电信') !== false) {
            $ispColor = $isHighQuality ? '#1976D2' : '#42A5F5';
        } elseif (strpos($isp, '联通') !== false) {
            $ispColor = $isHighQuality ? '#388E3C' : '#66BB6A';
        } elseif (strpos($isp, '移动') !== false) {
            $ispColor = $isHighQuality ? '#F57C00' : '#FFA726';
        } else {
            $ispColor = '#757575';
        }
        
        $bgColor = $isHighQuality ? '#F1F8E9' : '#FFFFFF';
        $strokeWidth = $isHighQuality ? 2 : 1;
        $strokeColor = $isHighQuality ? $ispColor : '#E0E0E0';
        
        $svg[] = '<rect x="' . $currentX . '" y="' . $currentY . '" width="' . $itemWidth . '" height="' . $itemHeight . '" rx="8" fill="' . $bgColor . '" stroke="' . $strokeColor . '" stroke-width="' . $strokeWidth . '"/>';
        $svg[] = '<rect x="' . ($currentX + 1) . '" y="' . ($currentY + 1) . '" width="' . ($itemWidth - 2) . '" height="4" fill="' . $ispColor . '"/>';
        
        $svg[] = '<text x="' . ($currentX + 15) . '" y="' . ($currentY + 28) . '" style="font-size: 13px; font-weight: 700; fill: ' . $ispColor . ';">' . htmlspecialchars($label) . '</text>';
        $svg[] = '<text x="' . ($currentX + 15) . '" y="' . ($currentY + 48) . '" style="font-size: 11px; font-weight: 600; fill: #212121;">' . htmlspecialchars($route) . '</text>';
        
        $qualityColor = $isHighQuality ? '#558B2F' : '#757575';
        $svg[] = '<text x="' . ($currentX + 15) . '" y="' . ($currentY + 68) . '" style="font-size: 10px; fill: ' . $qualityColor . ';">' . htmlspecialchars($quality) . '</text>';
        
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

function svgDrawFooter(&$svg, $width, $y) {
    $footerHeight = 40;
    
    $svg[] = '<rect x="0" y="' . $y . '" width="' . $width . '" height="' . $footerHeight . '" fill="url(#footerGradient)"/>';
    $svg[] = '<line x1="0" y1="' . $y . '" x2="' . $width . '" y2="' . $y . '" stroke="#1565C0" stroke-width="2"/>';
    $svg[] = '<text x="25" y="' . ($y + 25) . '" class="footer-text">Powered by bench.nodeloc.cc</text>';
    $svg[] = '<text x="' . ($width - 150) . '" y="' . ($y + 25) . '" class="footer-text">NodeLoc.com</text>';
}

function generateErrorSVG($message) {
    $svg = [];
    $svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="650" height="250">';
    $svg[] = '<rect width="650" height="250" fill="#F8F9FA"/>';
    $svg[] = '<text x="50" y="120" style="font-size: 18px; fill: #D32F2F;">' . htmlspecialchars($message) . '</text>';
    $svg[] = '</svg>';
    echo implode("\n", $svg);
}
