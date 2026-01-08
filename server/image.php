<?php
/**
 * VPS测试报告图片生成器 - SVG增强版
 * 现代化设计，漂亮配色，矢量图形
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

mb_internal_encoding('UTF-8');
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$filePath = basename($_GET['file'] ?? '');
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

if (empty($filePath)) {
    generateErrorSVG("错误: 未指定文件");
    exit;
}

$fullPath = __DIR__ . "/{$year}/{$month}/{$filePath}";

if (!file_exists($fullPath)) {
    generateErrorSVG("错误: 文件不存在");
    exit;
}

$content = file_get_contents($fullPath);
if ($content === false) {
    generateErrorSVG("错误: 无法读取文件");
    exit;
}

$data = parseTestResults($content);

try {
    generateSVGImage($data);
} catch (Exception $e) {
    generateErrorSVG("生成失败: " . $e->getMessage());
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
        $metrics['核心数'] = $match[1] . ' 核';
    }
    if (preg_match('/RAM\s*:\s*(.+)/i', $content, $match)) {
        $metrics['内存'] = trim($match[1]);
    }
    if (preg_match('/Disk\s*:\s*(.+)/i', $content, $match)) {
        $metrics['硬盘'] = trim($match[1]);
    }
    if (preg_match('/Total\s*\|\s*(\d+\.?\d*)\s*(MB\/s|GB\/s)/i', $content, $match)) {
        $metrics['磁盘IO'] = $match[1] . ' ' . $match[2];
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
        $metrics['风险评分'] = $match[1];
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
    
    if (preg_match('/国家:\s*([^\s]+)\s+城市:\s*(.+?)\s+服务商:\s*(.+)/u', $content, $serverMatch)) {
        $metrics['_server_info'] = [
            'country' => trim($serverMatch[1]),
            'city' => trim($serverMatch[2]),
            'provider' => trim($serverMatch[3])
        ];
    }
    
    preg_match_all('/(北京|上海|广州|成都)(电信|联通|移动)\s+([\d\.]+)\s+(\S+)\s+\[([^\]]+)\]/u', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $region = trim($match[1]);
        $isp = trim($match[2]);
        $route = trim($match[4]);
        $quality = trim($match[5]);
        
        $metrics[$region . $isp] = [
            'route' => $route,
            'quality' => $quality
        ];
    }
    
    return $metrics;
}

// ============ SVG生成 ============

function generateSVGImage($data) {
    $width = 1200;
    $sections = $data['sections'];
    
    $estimatedHeight = 150;
    $estimatedHeight += count($sections['YABS']['metrics'] ?? []) > 0 ? 180 : 0;
    $estimatedHeight += count($sections['IP质量']['metrics'] ?? []) > 0 ? 150 : 0;
    $estimatedHeight += count($sections['流媒体']['metrics'] ?? []) > 0 ? 200 : 0;
    $estimatedHeight += count($sections['多线程测速']['metrics'] ?? []) > 0 ? 160 : 0;
    $estimatedHeight += count($sections['单线程测速']['metrics'] ?? []) > 0 ? 160 : 0;
    $estimatedHeight += count($sections['响应']['metrics'] ?? []) > 0 ? 80 : 0;
    $estimatedHeight += count($sections['回程路由']['metrics'] ?? []) > 0 ? 350 : 0;
    $estimatedHeight += 100;
    
    $svg = [];
    $svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $estimatedHeight . '" viewBox="0 0 ' . $width . ' ' . $estimatedHeight . '">';
    
    // 定义样式和渐变
    $svg[] = '<defs>';
    
    // 渐变背景
    $svg[] = '<linearGradient id="headerGrad" x1="0%" y1="0%" x2="100%" y2="100%">';
    $svg[] = '  <stop offset="0%" stop-color="#6366F1"/>';
    $svg[] = '  <stop offset="100%" stop-color="#8B5CF6"/>';
    $svg[] = '</linearGradient>';
    
    $svg[] = '<linearGradient id="footerGrad" x1="0%" y1="0%" x2="100%" y2="0%">';
    $svg[] = '  <stop offset="0%" stop-color="#1E293B"/>';
    $svg[] = '  <stop offset="100%" stop-color="#334155"/>';
    $svg[] = '</linearGradient>';
    
    // 卡片渐变
    $svg[] = '<linearGradient id="cardBg" x1="0%" y1="0%" x2="0%" y2="100%">';
    $svg[] = '  <stop offset="0%" stop-color="#FFFFFF"/>';
    $svg[] = '  <stop offset="100%" stop-color="#F8FAFC"/>';
    $svg[] = '</linearGradient>';
    
    // 阴影
    $svg[] = '<filter id="shadow">';
    $svg[] = '  <feDropShadow dx="0" dy="2" stdDeviation="4" flood-opacity="0.1"/>';
    $svg[] = '</filter>';
    
    $svg[] = '<filter id="glow">';
    $svg[] = '  <feGaussianBlur stdDeviation="2" result="coloredBlur"/>';
    $svg[] = '  <feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge>';
    $svg[] = '</filter>';
    
    // 样式
    $svg[] = '<style>';
    $svg[] = '@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap");';
    $svg[] = '* { font-family: "Inter", -apple-system, sans-serif; }';
    $svg[] = '.title { font-size: 32px; font-weight: 700; fill: #FFFFFF; letter-spacing: -0.5px; }';
    $svg[] = '.subtitle { font-size: 14px; font-weight: 500; fill: rgba(255,255,255,0.8); }';
    $svg[] = '.section-title { font-size: 18px; font-weight: 700; fill: #1E293B; }';
    $svg[] = '.card-label { font-size: 11px; font-weight: 500; fill: #64748B; text-transform: uppercase; letter-spacing: 0.5px; }';
    $svg[] = '.card-value { font-size: 14px; font-weight: 600; fill: #1E293B; }';
    $svg[] = '.emoji { font-size: 20px; }';
    $svg[] = '</style>';
    $svg[] = '</defs>';
    
    // 背景
    $svg[] = '<rect width="' . $width . '" height="' . $estimatedHeight . '" fill="#F1F5F9"/>';
    
    $currentY = 0;
    $padding = 30;
    
    // 标题
    $currentY = drawHeader($svg, $width, $data['timestamp'], $currentY);
    $currentY += 25;
    
    // YABS信息
    if (!empty($sections['YABS']['metrics'])) {
        $currentY = drawSection($svg, $padding, $currentY, $width, "💻 系统配置", $sections['YABS']['metrics'], 'info');
        $currentY += 20;
    }
    
    // IP质量
    if (!empty($sections['IP质量']['metrics']) || !empty($sections['回程路由']['metrics']['_server_info'])) {
        $ipMetrics = $sections['IP质量']['metrics'] ?? [];
        
        if (!empty($sections['回程路由']['metrics']['_server_info'])) {
            $serverInfo = $sections['回程路由']['metrics']['_server_info'];
            $ipMetrics['国家'] = $serverInfo['country'];
            $ipMetrics['城市'] = $serverInfo['city'];
            $ipMetrics['服务商'] = $serverInfo['provider'];
        }
        
        $currentY = drawSection($svg, $padding, $currentY, $width, "🌐 网络质量", $ipMetrics, 'ip');
        $currentY += 20;
    }
    
    // 流媒体
    if (!empty($sections['流媒体']['metrics'])) {
        $currentY = drawSection($svg, $padding, $currentY, $width, "🎬 流媒体解锁", $sections['流媒体']['metrics'], 'streaming');
        $currentY += 20;
    }
    
    // 测速
    if (!empty($sections['多线程测速']['metrics']) || !empty($sections['单线程测速']['metrics'])) {
        $currentY = drawSpeedTest($svg, $padding, $currentY, $width,
                                  $sections['多线程测速']['metrics'] ?? [],
                                  $sections['单线程测速']['metrics'] ?? []);
        $currentY += 20;
    }
    
    // 响应
    if (!empty($sections['响应']['metrics'])) {
        $currentY = drawSection($svg, $padding, $currentY, $width, "⚡ 响应测试", $sections['响应']['metrics'], 'response');
        $currentY += 20;
    }
    
    // 回程路由
    if (!empty($sections['回程路由']['metrics'])) {
        $routeCount = count(array_filter(array_keys($sections['回程路由']['metrics']), fn($k) => $k !== '_server_info'));
        $currentY = drawSection($svg, $padding, $currentY, $width, "🔄 回程路由 ({$routeCount})", $sections['回程路由']['metrics'], 'routes');
        $currentY += 20;
    }
    
    // 底部
    drawFooter($svg, $width, $currentY + 30);
    
    $svg[] = '</svg>';
    echo implode("\n", $svg);
}

function drawHeader(&$svg, $width, $timestamp, $y) {
    $h = 100;
    
    // 渐变背景
    $svg[] = '<rect y="' . $y . '" width="' . $width . '" height="' . $h . '" fill="url(#headerGrad)" filter="url(#shadow)"/>';
    
    // 装饰元素
    $svg[] = '<circle cx="' . ($width - 50) . '" cy="' . ($y + 50) . '" r="80" fill="#FFFFFF" opacity="0.05"/>';
    $svg[] = '<circle cx="50" cy="' . ($y + 80) . '" r="60" fill="#FFFFFF" opacity="0.05"/>';
    
    // 标题
    $svg[] = '<text x="40" y="' . ($y + 45) . '" class="title">VPS Performance Report</text>';
    $svg[] = '<text x="40" y="' . ($y + 70) . '" class="subtitle">Generated: ' . htmlspecialchars($timestamp) . '</text>';
    
    return $y + $h;
}

function drawSection(&$svg, $x, $y, $width, $title, $metrics, $type) {
    $svg[] = '<text x="' . $x . '" y="' . ($y + 22) . '" class="section-title">' . htmlspecialchars($title) . '</text>';
    $y += 35;
    
    switch ($type) {
        case 'info':
            return drawInfoCards($svg, $x, $y, $metrics);
        case 'ip':
            return drawIPCards($svg, $x, $y, $metrics);
        case 'streaming':
            return drawStreamingCards($svg, $x, $y, $metrics);
        case 'response':
            return drawResponseCard($svg, $x, $y, $metrics);
        case 'routes':
            return drawRouteCards($svg, $x, $y, $metrics);
    }
    
    return $y;
}

function drawInfoCards(&$svg, $x, $y, $metrics) {
    $w = 220;
    $h = 80;
    $gap = 12;
    $cols = 5;
    $col = 0;
    $cx = $x;
    $cy = $y;
    
    foreach ($metrics as $key => $value) {
        // 卡片
        $svg[] = '<g filter="url(#shadow)">';
        $svg[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $w . '" height="' . $h . '" rx="12" fill="url(#cardBg)"/>';
        $svg[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $w . '" height="4" rx="12 12 0 0" fill="#6366F1"/>';
        $svg[] = '</g>';
        
        // 文本
        $svg[] = '<text x="' . ($cx + 16) . '" y="' . ($cy + 32) . '" class="card-label">' . htmlspecialchars($key) . '</text>';
        
        $displayValue = mb_strlen($value) > 26 ? mb_substr($value, 0, 23) . '...' : $value;
        $svg[] = '<text x="' . ($cx + 16) . '" y="' . ($cy + 56) . '" class="card-value">' . htmlspecialchars($displayValue) . '</text>';
        
        $col++;
        if ($col >= $cols) {
            $col = 0;
            $cx = $x;
            $cy += $h + $gap;
        } else {
            $cx += $w + $gap;
        }
    }
    
    if ($col > 0) $cy += $h + $gap;
    return $cy;
}

function drawIPCards(&$svg, $x, $y, $metrics) {
    $w = 185;
    $h = 80;
    $gap = 10;
    $cols = 6;
    $col = 0;
    $cx = $x;
    $cy = $y;
    
    foreach ($metrics as $key => $value) {
        $svg[] = '<g filter="url(#shadow)">';
        $svg[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $w . '" height="' . $h . '" rx="12" fill="url(#cardBg)"/>';
        $svg[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $w . '" height="4" rx="12 12 0 0" fill="#10B981"/>';
        $svg[] = '</g>';
        
        $svg[] = '<text x="' . ($cx + 14) . '" y="' . ($cy + 30) . '" class="card-label">' . htmlspecialchars($key) . '</text>';
        
        $displayValue = mb_strlen($value) > 20 ? mb_substr($value, 0, 17) . '...' : $value;
        $svg[] = '<text x="' . ($cx + 14) . '" y="' . ($cy + 54) . '" class="card-value">' . htmlspecialchars($displayValue) . '</text>';
        
        $col++;
        if ($col >= $cols) {
            $col = 0;
            $cx = $x;
            $cy += $h + $gap;
        } else {
            $cx += $w + $gap;
        }
    }
    
    if ($col > 0) $cy += $h + $gap;
    return $cy;
}

function drawStreamingCards(&$svg, $x, $y, $metrics) {
    $w = 185;
    $h = 70;
    $gap = 10;
    $cols = 6;
    $col = 0;
    $cx = $x;
    $cy = $y;
    
    foreach ($metrics as $service => $status) {
        $isSuccess = ($status === '✓');
        $bgColor = $isSuccess ? '#ECFDF5' : '#FEF2F2';
        $accentColor = $isSuccess ? '#10B981' : '#EF4444';
        $icon = $isSuccess ? '✓' : '✗';
        
        $svg[] = '<g filter="url(#shadow)">';
        $svg[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $w . '" height="' . $h . '" rx="12" fill="' . $bgColor . '"/>';
        $svg[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $w . '" height="4" rx="12 12 0 0" fill="' . $accentColor . '"/>';
        $svg[] = '</g>';
        
        // 图标
        $svg[] = '<circle cx="' . ($cx + 25) . '" cy="' . ($cy + 42) . '" r="14" fill="' . $accentColor . '" opacity="0.15"/>';
        $svg[] = '<text x="' . ($cx + 18) . '" y="' . ($cy + 50) . '" style="font-size: 20px; font-weight: 700; fill: ' . $accentColor . ';">' . $icon . '</text>';
        
        $svg[] = '<text x="' . ($cx + 45) . '" y="' . ($cy + 48) . '" style="font-size: 13px; font-weight: 600; fill: #1E293B;">' . htmlspecialchars($service) . '</text>';
        
        $col++;
        if ($col >= $cols) {
            $col = 0;
            $cx = $x;
            $cy += $h + $gap;
        } else {
            $cx += $w + $gap;
        }
    }
    
    if ($col > 0) $cy += $h + $gap;
    return $cy;
}

function drawSpeedTest(&$svg, $x, $y, $width, $multi, $single) {
    $svg[] = '<text x="' . $x . '" y="' . ($y + 22) . '" class="section-title">🚀 网络测速</text>';
    $y += 35;
    
    $halfW = floor(($width - 60 - 30) / 2);
    
    if (!empty($multi)) {
        $svg[] = '<text x="' . $x . '" y="' . ($y + 18) . '" style="font-size: 14px; font-weight: 600; fill: #64748B;">多线程</text>';
        $leftY = drawSpeedBars($svg, $x, $y + 30, $halfW, $multi);
    }
    
    if (!empty($single)) {
        $svg[] = '<text x="' . ($x + $halfW + 30) . '" y="' . ($y + 18) . '" style="font-size: 14px; font-weight: 600; fill: #64748B;">单线程</text>';
        $rightY = drawSpeedBars($svg, $x + $halfW + 30, $y + 30, $halfW, $single);
    }
    
    return max($leftY ?? $y, $rightY ?? $y) + 15;
}

function drawSpeedBars(&$svg, $x, $y, $w, $metrics) {
    $bh = 32;
    $gap = 10;
    $cy = $y;
    
    foreach ($metrics as $key => $value) {
        if ($key !== '平均下载' && $key !== '平均上传') continue;
        
        preg_match('/(\d+\.?\d*)/', $value, $match);
        $numValue = isset($match[1]) ? floatval($match[1]) : 0;
        
        $barW = min(($numValue / 1000) * ($w - 150), $w - 150);
        if ($barW < 10) $barW = 10;
        
        // 背景条
        $svg[] = '<rect x="' . ($x + 90) . '" y="' . $cy . '" width="' . ($w - 150) . '" height="' . $bh . '" rx="6" fill="#F1F5F9"/>';
        
        // 渐变条
        $isDown = (strpos($key, '下载') !== false);
        $color = $isDown ? '#6366F1' : '#8B5CF6';
        $svg[] = '<rect x="' . ($x + 90) . '" y="' . $cy . '" width="' . $barW . '" height="' . $bh . '" rx="6" fill="' . $color . '" filter="url(#glow)"/>';
        
        $label = str_replace('平均', '', $key);
        $svg[] = '<text x="' . ($x + 5) . '" y="' . ($cy + 21) . '" style="font-size: 12px; font-weight: 500; fill: #64748B;">' . htmlspecialchars($label) . '</text>';
        $svg[] = '<text x="' . ($x + 100) . '" y="' . ($cy + 21) . '" style="font-size: 12px; font-weight: 700; fill: #FFFFFF;">' . htmlspecialchars($value) . '</text>';
        
        $cy += $bh + $gap;
    }
    
    return $cy;
}

function drawResponseCard(&$svg, $x, $y, $metrics) {
    $cy = $y;
    
    foreach ($metrics as $key => $value) {
        $svg[] = '<g filter="url(#shadow)">';
        $svg[] = '<rect x="' . $x . '" y="' . $cy . '" width="300" height="70" rx="12" fill="url(#cardBg)"/>';
        $svg[] = '<rect x="' . $x . '" y="' . $cy . '" width="300" height="4" rx="12 12 0 0" fill="#F59E0B"/>';
        $svg[] = '</g>';
        
        $svg[] = '<text x="' . ($x + 16) . '" y="' . ($cy + 30) . '" class="card-label">' . htmlspecialchars($key) . '</text>';
        $svg[] = '<text x="' . ($x + 16) . '" y="' . ($cy + 54) . '" class="card-value" style="font-size: 18px;">' . htmlspecialchars($value) . '</text>';
        
        $cy += 85;
    }
    
    return $cy;
}

function drawRouteCards(&$svg, $x, $y, $metrics) {
    $w = 280;
    $h = 90;
    $gap = 12;
    $cols = 4;
    $col = 0;
    $cx = $x;
    $cy = $y;
    
    foreach ($metrics as $label => $data) {
        if ($label === '_server_info') continue;
        if (!is_array($data)) continue;
        
        $route = $data['route'] ?? '';
        $quality = $data['quality'] ?? '普通线路';
        $isHQ = (strpos($quality, '优质') !== false);
        
        // 运营商颜色
        if (strpos($label, '电信') !== false) {
            $color = $isHQ ? '#2563EB' : '#60A5FA';
        } elseif (strpos($label, '联通') !== false) {
            $color = $isHQ ? '#059669' : '#10B981';
        } elseif (strpos($label, '移动') !== false) {
            $color = $isHQ ? '#DC2626' : '#F87171';
        } else {
            $color = '#64748B';
        }
        
        $svg[] = '<g filter="url(#shadow)">';
        $svg[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $w . '" height="' . $h . '" rx="12" fill="url(#cardBg)" stroke="' . $color . '" stroke-width="' . ($isHQ ? '2' : '1') . '"/>';
        $svg[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $w . '" height="4" rx="12 12 0 0" fill="' . $color . '"/>';
        $svg[] = '</g>';
        
        $svg[] = '<text x="' . ($cx + 16) . '" y="' . ($cy + 32) . '" style="font-size: 14px; font-weight: 700; fill: ' . $color . ';">' . htmlspecialchars($label) . '</text>';
        $svg[] = '<text x="' . ($cx + 16) . '" y="' . ($cy + 54) . '" style="font-size: 12px; font-weight: 600; fill: #1E293B;">' . htmlspecialchars($route) . '</text>';
        
        $qualityColor = $isHQ ? '#059669' : '#64748B';
        $svg[] = '<text x="' . ($cx + 16) . '" y="' . ($cy + 72) . '" style="font-size: 11px; font-weight: 500; fill: ' . $qualityColor . ';">' . htmlspecialchars($quality) . '</text>';
        
        $col++;
        if ($col >= $cols) {
            $col = 0;
            $cx = $x;
            $cy += $h + $gap;
        } else {
            $cx += $w + $gap;
        }
    }
    
    if ($col > 0) $cy += $h + $gap;
    return $cy;
}

function drawFooter(&$svg, $width, $y) {
    $h = 60;
    
    $svg[] = '<rect y="' . $y . '" width="' . $width . '" height="' . $h . '" fill="url(#footerGrad)"/>';
    $svg[] = '<text x="30" y="' . ($y + 35) . '" style="font-size: 12px; font-weight: 500; fill: rgba(255,255,255,0.8);">Powered by bench.nodeloc.cc</text>';
    $svg[] = '<text x="' . ($width - 150) . '" y="' . ($y + 35) . '" style="font-size: 12px; font-weight: 500; fill: rgba(255,255,255,0.8);">NodeLoc.com</text>';
}

function generateErrorSVG($message) {
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="200">';
    echo '<rect width="600" height="200" fill="#FEF2F2"/>';
    echo '<text x="30" y="100" style="font-size: 18px; fill: #DC2626;">' . htmlspecialchars($message) . '</text>';
    echo '</svg>';
}
