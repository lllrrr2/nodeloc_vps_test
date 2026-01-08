<?php
/**
 * VPS测试报告图片生成器
 * 根据测试结果生成美观的图片报告
 * 
 * 依赖要求：
 * - PHP Imagick 扩展 (必需)
 * - 系统字体 (自动检测)
 */

// 启用错误报告用于调试
error_reporting(E_ALL);
ini_set('display_errors', 0); // 不在浏览器显示，只记录到日志

// 记录请求开始
error_log("=== Image generation request started ===");
error_log("GET parameters: " . print_r($_GET, true));

// 检查 Imagick 扩展
if (!extension_loaded('imagick')) {
    error_log("ERROR: Imagick extension not loaded");
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    die("错误: PHP Imagick 扩展未安装\n\n" .
        "安装方法：\n" .
        "Ubuntu/Debian: sudo apt-get install php-imagick\n" .
        "CentOS/RHEL: sudo yum install php-imagick\n" .
        "然后重启Web服务器: sudo systemctl restart php-fpm nginx");
}

error_log("Imagick extension loaded successfully");

// 设置字符编码和内容类型
mb_internal_encoding('UTF-8');
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');

// 获取测试结果文件路径
$filePath = $_GET['file'] ?? '';
error_log("File path from GET: " . $filePath);

if (empty($filePath)) {
    error_log("ERROR: No file specified in request");
    generateErrorImage("ERROR: No file specified");
    exit;
}

// 安全检查：防止路径遍历攻击
$filePath = basename($filePath);
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

$fullPath = __DIR__ . "/{$year}/{$month}/{$filePath}";
error_log("Full path constructed: " . $fullPath);
error_log("File exists: " . (file_exists($fullPath) ? 'yes' : 'no'));
error_log("Is file: " . (is_file($fullPath) ? 'yes' : 'no'));

if (!file_exists($fullPath) || !is_file($fullPath)) {
    error_log("ERROR: File not found or not a file: " . $fullPath);
    generateErrorImage("ERROR: File not found");
    exit;
}

// 读取测试结果
error_log("Reading file content...");
$content = file_get_contents($fullPath);
if ($content === false) {
    error_log("ERROR: Failed to read file content");
    generateErrorImage("ERROR: Cannot read file");
    exit;
}

error_log("File content length: " . strlen($content) . " bytes");

// 解析测试结果
error_log("Parsing test results...");
$data = parseTestResults($content);

// 记录解析结果
error_log("Parsed sections: " . implode(", ", array_keys($data['sections'])));
foreach ($data['sections'] as $name => $section) {
    error_log("Section '$name' has " . count($section['metrics']) . " metrics");
}

// 生成图片
error_log("Starting image generation...");
try {
    generateResultImage($data);
    error_log("=== Image generation completed successfully ===");
} catch (Exception $e) {
    error_log("Image generation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    generateErrorImage("Error: Failed to generate image - " . $e->getMessage());
} catch (Error $e) {
    error_log("PHP Error during image generation: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    generateErrorImage("Error: PHP Error - " . $e->getMessage());
}

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
    
    // IP类型
    if (preg_match('/IP类型[：:]*\s*(.+)/u', $content, $match)) {
        $ipType = trim($match[1]);
        // Translate Chinese IP types to English
        $ipType = str_replace('原生IP', 'Native IP', $ipType);
        $ipType = str_replace('数据中心', 'Data Center', $ipType);
        $ipType = str_replace('家庭宽带', 'Residential', $ipType);
        $ipType = str_replace('机房', 'IDC', $ipType);
        $metrics['IP Type'] = $ipType;
    }
    
    // 自治系统
    if (preg_match('/自治系统号[：:]*\s*(AS\d+)/u', $content, $match)) {
        $metrics['ASN'] = trim($match[1]);
    }
    
    // 风险评分 - translate risk level
    if (preg_match('/IP2Location[：:]*\s*(\d+)\|(.+)/u', $content, $match)) {
        $riskLevel = trim($match[2]);
        // Translate risk levels
        $riskLevel = str_replace('低风险', 'Low Risk', $riskLevel);
        $riskLevel = str_replace('中风险', 'Medium Risk', $riskLevel);
        $riskLevel = str_replace('高风险', 'High Risk', $riskLevel);
        $riskLevel = str_replace('极高风险', 'Very High Risk', $riskLevel);
        $riskLevel = str_replace('极低风险', 'Very Low Risk', $riskLevel);
        $metrics['Risk Score'] = $match[1] . ' (' . $riskLevel . ')';
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
        $metrics['Average Latency'] = $match[1] . ' ms';
    }
    
    return $metrics;
}

/**
 * Parse route trace data - extract all 9 routes
 */
function parseRouteTrace($content) {
    $metrics = [];
    
    // Extract all route trace entries (No:X/9 Traceroute to ...)
    preg_match_all('/No:(\d+)\/9 Traceroute to ([^\n]+)/u', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $routeNum = $match[1];
        $destination = $match[2];
        
        // Clean up destination (remove Chinese characters and extra spaces)
        // Extract key information: country, city, ISP type
        $destination = preg_replace('/中国\s*/u', 'China ', $destination);
        $destination = preg_replace('/广东/u', 'Guangdong', $destination);
        $destination = preg_replace('/上海/u', 'Shanghai', $destination);
        $destination = preg_replace('/北京/u', 'Beijing', $destination);
        $destination = preg_replace('/电信/u', 'CT', $destination); // China Telecom
        $destination = preg_replace('/联通/u', 'CU', $destination); // China Unicom
        $destination = preg_replace('/移动/u', 'CM', $destination); // China Mobile
        $destination = preg_replace('/\s+/', ' ', $destination);
        $destination = trim($destination);
        
        $metrics["Route $routeNum"] = $destination;
    }
    
    return $metrics;
}

/**
 * 生成结果图片 - 使用Imagick
 */
function generateResultImage($data) {
    // 图片尺寸
    $width = 1200;
    $headerHeight = 120;
    $padding = 25;
    
    // 预估高度
    $estimatedHeight = 2500;
    
    // 创建Imagick对象
    $image = new Imagick();
    $image->newImage($width, $estimatedHeight, new ImagickPixel('#F8F9FA'));
    $image->setImageFormat('png');
    
    // 定义现代化配色方案
    $bgColor = imagecolorallocate($image, 248, 249, 250);
    $headerBg = imagecolorallocate($image, 26, 115, 232);
    $headerBgDark = imagecolorallocate($image, 13, 71, 161);
    $sectionBg = imagecolorallocate($image, 227, 242, 253);
    $sectionBorder = imagecolorallocate($image, 144, 202, 249);
    $textColor = imagecolorallocate($image, 33, 33, 33);
    $textLight = imagecolorallocate($image, 97, 97, 97);
    $whiteColor = imagecolorallocate($image, 255, 255, 255);
    $successColor = imagecolorallocate($image, 56, 142, 60);
    $failColor = imagecolorallocate($image, 211, 47, 47);
    $accentColor = imagecolorallocate($image, 255, 167, 38);
    $chartBlue = imagecolorallocate($image, 66, 165, 245);
    $chartGreen = imagecolorallocate($image, 102, 187, 106);
    $chartOrange = imagecolorallocate($image, 255, 167, 38);
    $chartPurple = imagecolorallocate($image, 171, 71, 188);
    $chartCyan = imagecolorallocate($image, 38, 198, 218);
    $gridColor = imagecolorallocate($image, 224, 224, 224);
    
    // 填充背景
    imagefilledrectangle($image, 0, 0, $width, $estimatedHeight, $bgColor);
    
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
    
    // 如果没有找到字体文件，记录日志并使用内置字体
    if (!$fontExists) {
        error_log("Warning: No TrueType font found. Using built-in fonts. Consider installing fonts with: apt-get install fonts-dejavu-core");
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
        // 使用内置字体绘制标题
        imagestring($image, 5, $padding + 10, 25, "NodeLoc VPS Benchmark", $whiteColor);
        imagestring($image, 3, $padding + 10, 50, $subtitle, $whiteColor);
    }
    
    // 开始绘制内容
    $currentY = $headerHeight + 30;
    
    // 准备颜色数组
    $colors = [$chartBlue, $chartGreen, $chartOrange, $chartPurple, $chartCyan];
    
    // 检查是否有任何数据
    $hasData = false;
    foreach ($data['sections'] as $section) {
        if (!empty($section['metrics'])) {
            $hasData = true;
            break;
        }
    }
    
    // 如果没有数据，显示提示信息
    if (!$hasData) {
        if ($fontFile) {
            imagettftext($image, 14, 0, $padding, $currentY + 50, $textColor, $fontFile, "No benchmark data found in the file.");
        } else {
            imagestring($image, 4, $padding, $currentY + 30, "No benchmark data found", $textColor);
        }
        $currentY += 100;
    }
    
    // 1. 绘制YABS信息卡片
    if (isset($data['sections']['YABS']) && !empty($data['sections']['YABS']['metrics'])) {
        $yabsMetrics = $data['sections']['YABS']['metrics'];
        
        // 绘制section标题
        if ($fontFile) {
            imagettftext($image, 16, 0, $padding, $currentY, $headerBg, $fontFile, "📊 System Information");
        } else {
            imagestring($image, 5, $padding, $currentY - 10, "System Information", $headerBg);
        }
        $currentY += 35;
        
        // 绘制信息卡片 - 4列布局
        $cardWidth = 270;
        $cardHeight = 100;
        $cardSpacing = 20;
        $cardsPerRow = 4;
        
        $cardData = [
            ['icon' => '💻', 'title' => 'CPU', 'value' => $yabsMetrics['CPU'] ?? 'N/A', 'color' => $chartBlue],
            ['icon' => '🧠', 'title' => 'Memory', 'value' => $yabsMetrics['Memory'] ?? 'N/A', 'color' => $chartGreen],
            ['icon' => '💾', 'title' => 'Disk', 'value' => $yabsMetrics['Disk'] ?? 'N/A', 'color' => $chartOrange],
            ['icon' => '⚡', 'title' => 'Disk I/O', 'value' => $yabsMetrics['Disk I/O'] ?? 'N/A', 'color' => $chartPurple],
        ];
        
        $cardX = $padding;
        foreach ($cardData as $index => $card) {
            drawInfoCard($image, $cardX, $currentY, $cardWidth, $cardHeight, 
                        $card['icon'], $card['title'], $card['value'], $card['color'], $fontFile);
            $cardX += $cardWidth + $cardSpacing;
        }
        $currentY += $cardHeight + 40;
    }
    
    // 2. 绘制IP质量信息
    if (isset($data['sections']['IP质量']) && !empty($data['sections']['IP质量']['metrics'])) {
        if ($fontFile) {
            imagettftext($image, 16, 0, $padding, $currentY, $headerBg, $fontFile, "🌐 IP Quality");
        } else {
            imagestring($image, 5, $padding, $currentY - 10, "IP Quality", $headerBg);
        }
        $currentY += 35;
        
        $ipMetrics = $data['sections']['IP质量']['metrics'];
        $cardX = $padding;
        
        $ipCards = [
            ['icon' => '🔖', 'title' => 'IP Type', 'value' => $ipMetrics['IP Type'] ?? 'N/A', 'color' => $chartBlue],
            ['icon' => '🏢', 'title' => 'ASN', 'value' => $ipMetrics['ASN'] ?? 'N/A', 'color' => $chartGreen],
            ['icon' => '⚠️', 'title' => 'Risk Score', 'value' => $ipMetrics['Risk Score'] ?? 'N/A', 'color' => $chartOrange],
        ];
        
        foreach ($ipCards as $card) {
            drawInfoCard($image, $cardX, $currentY, $cardWidth, $cardHeight,
                        $card['icon'], $card['title'], $card['value'], $card['color'], $fontFile);
            $cardX += $cardWidth + $cardSpacing;
        }
        $currentY += $cardHeight + 40;
    }
    
    // 3. 绘制流媒体解锁网格
    if (isset($data['sections']['流媒体']) && !empty($data['sections']['流媒体']['metrics'])) {
        if ($fontFile) {
            imagettftext($image, 16, 0, $padding, $currentY, $headerBg, $fontFile, "🎬 Streaming Services Unlock Status");
        } else {
            imagestring($image, 5, $padding, $currentY - 10, "Streaming Services", $headerBg);
        }
        $currentY += 35;
        
        $currentY = drawStreamingGrid($image, $padding, $currentY, $width - $padding * 2, 
                                     $data['sections']['流媒体']['metrics'], $fontFile);
        $currentY += 30;
    }
    
    // 4. 绘制多线程测速条形图
    if (isset($data['sections']['多线程测速']) && !empty($data['sections']['多线程测速']['metrics'])) {
        $speedMetrics = $data['sections']['多线程测速']['metrics'];
        
        if ($fontFile) {
            imagettftext($image, 16, 0, $padding, $currentY, $headerBg, $fontFile, "🚀 Multi-thread Speed Test");
        } else {
            imagestring($image, 5, $padding, $currentY - 10, "Multi-thread Speed Test", $headerBg);
        }
        $currentY += 35;
        
        // 准备条形图数据
        $chartData = [];
        if (isset($speedMetrics['Avg Download'])) {
            $value = floatval(preg_replace('/[^0-9.]/', '', $speedMetrics['Avg Download']));
            $chartData[] = ['label' => 'Download Speed', 'value' => $value, 'valueText' => $speedMetrics['Avg Download']];
        }
        if (isset($speedMetrics['Avg Upload'])) {
            $value = floatval(preg_replace('/[^0-9.]/', '', $speedMetrics['Avg Upload']));
            $chartData[] = ['label' => 'Upload Speed', 'value' => $value, 'valueText' => $speedMetrics['Avg Upload']];
        }
        
        if (!empty($chartData)) {
            $chartHeight = count($chartData) * 40 + 80;
            drawBarChart($image, $padding, $currentY, $width - $padding * 2, $chartHeight, 
                        $chartData, [$chartBlue, $chartGreen], $fontFile, '');
            $currentY += $chartHeight + 30;
        }
    }
    
    // 5. 绘制单线程测速条形图
    if (isset($data['sections']['单线程测速']) && !empty($data['sections']['单线程测速']['metrics'])) {
        $speedMetrics = $data['sections']['单线程测速']['metrics'];
        
        if ($fontFile) {
            imagettftext($image, 16, 0, $padding, $currentY, $headerBg, $fontFile, "📈 Single-thread Speed Test");
        } else {
            imagestring($image, 5, $padding, $currentY - 10, "Single-thread Speed Test", $headerBg);
        }
        $currentY += 35;
        
        // 准备条形图数据
        $chartData = [];
        if (isset($speedMetrics['Avg Download'])) {
            $value = floatval(preg_replace('/[^0-9.]/', '', $speedMetrics['Avg Download']));
            $chartData[] = ['label' => 'Download Speed', 'value' => $value, 'valueText' => $speedMetrics['Avg Download']];
        }
        if (isset($speedMetrics['Avg Upload'])) {
            $value = floatval(preg_replace('/[^0-9.]/', '', $speedMetrics['Avg Upload']));
            $chartData[] = ['label' => 'Upload Speed', 'value' => $value, 'valueText' => $speedMetrics['Avg Upload']];
        }
        
        if (!empty($chartData)) {
            $chartHeight = count($chartData) * 40 + 80;
            drawBarChart($image, $padding, $currentY, $width - $padding * 2, $chartHeight,
                        $chartData, [$chartPurple, $chartCyan], $fontFile, '');
            $currentY += $chartHeight + 30;
        }
    }
    
    // 6. 绘制响应测试
    if (isset($data['sections']['响应']) && !empty($data['sections']['响应']['metrics'])) {
        if ($fontFile) {
            imagettftext($image, 16, 0, $padding, $currentY, $headerBg, $fontFile, "⚡ Response Test");
        } else {
            imagestring($image, 5, $padding, $currentY - 10, "Response Test", $headerBg);
        }
        $currentY += 35;
        
        $responseMetrics = $data['sections']['响应']['metrics'];
        foreach ($responseMetrics as $key => $value) {
            if ($fontFile) {
                imagettftext($image, 12, 0, $padding + 20, $currentY, $textColor, $fontFile, "$key: $value");
            } else {
                imagestring($image, 3, $padding + 20, $currentY - 5, "$key: $value", $textColor);
            }
            $currentY += 30;
        }
        $currentY += 20;
    }
    
    // 7. Display route trace (9 routes)
    if (isset($data['sections']['回程路由']) && !empty($data['sections']['回程路由']['metrics'])) {
        if ($fontFile) {
            imagettftext($image, 16, 0, $padding, $currentY, $headerBg, $fontFile, "🔄 Route Trace Back (9 Routes)");
        } else {
            imagestring($image, 5, $padding, $currentY - 10, "Route Trace Back", $headerBg);
        }
        $currentY += 35;
        
        $currentY = drawRouteGrid($image, $padding, $currentY, $width - $padding * 2, 
                                 $data['sections']['回程路由']['metrics'], $fontFile);
        $currentY += 30;
    }
    
    // 裁剪到实际使用的高度
    $finalHeight = max($currentY + 60, $headerHeight + 200); // 确保最小高度
    $finalImage = imagecreatetruecolor($width, $finalHeight);
    
    // 填充背景
    $bgColor2 = imagecolorallocate($finalImage, 248, 249, 250);
    imagefilledrectangle($finalImage, 0, 0, $width, $finalHeight, $bgColor2);
    
    // 复制内容
    imagecopy($finalImage, $image, 0, 0, 0, 0, $width, min($currentY + 60, $estimatedHeight));
    imagedestroy($image);
    $image = $finalImage;
    
    // 添加现代化底部区域
    $footerY = $finalHeight - 45;
    $footerBgDark = imagecolorallocate($image, 13, 71, 161);
    $accentColor2 = imagecolorallocate($image, 255, 167, 38);
    $whiteColor2 = imagecolorallocate($image, 255, 255, 255);
    
    imagefilledrectangle($image, 0, $footerY, $width, $finalHeight, $footerBgDark);
    
    // 底部装饰元素
    for ($i = 0; $i < 5; $i++) {
        $x = $width - 100 + ($i * 15);
        $size = 6 - $i;
        imagefilledellipse($image, $x, $footerY + 22, $size, $size, $accentColor2);
    }
    
    // 水印和版权信息
    $watermark = "Powered by bench.nodeloc.cc";
    if ($fontFile) {
        imagettftext($image, 10, 0, $padding, $footerY + 28, $whiteColor2, $fontFile, $watermark);
        // 右侧添加小图标
        $rightText = "NodeLoc.com";
        imagettftext($image, 9, 0, $width - 150, $footerY + 28, $whiteColor2, $fontFile, $rightText);
    } else {
        imagestring($image, 2, $padding, $footerY + 18, $watermark, $whiteColor2);
        imagestring($image, 2, $width - 120, $footerY + 18, "NodeLoc.com", $whiteColor2);
    }
    
    // 输出图片
    imagepng($image);
    imagedestroy($image);
}

/**
 * 绘制条形图
 */
function drawBarChart($image, $x, $y, $width, $height, $data, $colors, $fontFile, $title = '') {
    $whiteColor = imagecolorallocate($image, 255, 255, 255);
    $textColor = imagecolorallocate($image, 33, 33, 33);
    $gridColor = imagecolorallocate($image, 224, 224, 224);
    $bgColor = imagecolorallocate($image, 255, 255, 255);
    
    // 绘制背景
    drawRoundedRect($image, $x, $y, $x + $width, $y + $height, 8, $bgColor, $gridColor);
    
    // 绘制标题
    if ($title) {
        if ($fontFile) {
            imagettftext($image, 12, 0, $x + 15, $y + 25, $textColor, $fontFile, $title);
        } else {
            imagestring($image, 4, $x + 15, $y + 10, $title, $textColor);
        }
    }
    
    $chartY = $y + ($title ? 40 : 15);
    $chartHeight = $height - ($title ? 55 : 30);
    $barHeight = 25;
    $barSpacing = 10;
    
    // 找出最大值
    $maxValue = 0;
    foreach ($data as $item) {
        if ($item['value'] > $maxValue) {
            $maxValue = $item['value'];
        }
    }
    
    if ($maxValue == 0) $maxValue = 100;
    
    // 绘制每个条形
    $currentY = $chartY;
    foreach ($data as $index => $item) {
        $barWidth = ($item['value'] / $maxValue) * ($width - 250);
        $color = $colors[$index % count($colors)];
        
        // 绘制标签
        if ($fontFile) {
            imagettftext($image, 10, 0, $x + 15, $currentY + 18, $textColor, $fontFile, $item['label']);
        } else {
            imagestring($image, 3, $x + 15, $currentY + 8, $item['label'], $textColor);
        }
        
        // 绘制条形（带圆角）
        $barX = $x + 150;
        drawRoundedRect($image, $barX, $currentY + 2, $barX + $barWidth, $currentY + $barHeight, 4, $color, $color);
        
        // 绘制数值
        if ($fontFile) {
            imagettftext($image, 10, 0, $barX + $barWidth + 10, $currentY + 18, $textColor, $fontFile, $item['valueText']);
        } else {
            imagestring($image, 3, $barX + $barWidth + 10, $currentY + 8, $item['valueText'], $textColor);
        }
        
        $currentY += $barHeight + $barSpacing;
    }
    
    return $currentY - $chartY + 15;
}

/**
 * 绘制进度条
 */
function drawProgressBar($image, $x, $y, $width, $percentage, $color, $fontFile, $label = '') {
    $bgColor = imagecolorallocate($image, 230, 230, 230);
    $textColor = imagecolorallocate($image, 33, 33, 33);
    $whiteColor = imagecolorallocate($image, 255, 255, 255);
    
    $barHeight = 24;
    
    // 绘制标签
    if ($label) {
        if ($fontFile) {
            imagettftext($image, 10, 0, $x, $y - 5, $textColor, $fontFile, $label);
        } else {
            imagestring($image, 3, $x, $y - 15, $label, $textColor);
        }
        $y += 20;
    }
    
    // 绘制背景条
    drawRoundedRect($image, $x, $y, $x + $width, $y + $barHeight, 12, $bgColor, $bgColor);
    
    // 绘制进度条
    $progressWidth = ($width * $percentage) / 100;
    if ($progressWidth > 0) {
        drawRoundedRect($image, $x, $y, $x + $progressWidth, $y + $barHeight, 12, $color, $color);
    }
    
    // 绘制百分比文字
    $text = round($percentage, 1) . '%';
    if ($fontFile) {
        imagettftext($image, 10, 0, $x + $width/2 - 20, $y + 17, $textColor, $fontFile, $text);
    } else {
        imagestring($image, 3, $x + $width/2 - 15, $y + 8, $text, $textColor);
    }
    
    return $y + $barHeight + 5;
}

/**
 * 绘制流媒体解锁网格
 */
function drawStreamingGrid($image, $x, $y, $width, $data, $fontFile) {
    $successColor = imagecolorallocate($image, 76, 175, 80);
    $failColor = imagecolorallocate($image, 244, 67, 54);
    $textColor = imagecolorallocate($image, 33, 33, 33);
    $whiteColor = imagecolorallocate($image, 255, 255, 255);
    $borderColor = imagecolorallocate($image, 224, 224, 224);
    
    $itemWidth = 180;
    $itemHeight = 50;
    $cols = 3;
    $spacing = 15;
    
    $currentX = $x;
    $currentY = $y;
    $col = 0;
    
    foreach ($data as $service => $status) {
        if ($service === 'Summary') continue;
        
        $color = ($status === '✓') ? $successColor : $failColor;
        $bgColor = ($status === '✓') ? 
            imagecolorallocate($image, 232, 245, 233) : 
            imagecolorallocate($image, 255, 235, 238);
        
        // 绘制卡片
        drawRoundedRect($image, $currentX, $currentY, $currentX + $itemWidth, $currentY + $itemHeight, 8, $bgColor, $borderColor);
        
        // 绘制图标和文字
        $icon = ($status === '✓') ? '✓' : '✗';
        if ($fontFile) {
            imagettftext($image, 18, 0, $currentX + 15, $currentY + 32, $color, $fontFile, $icon);
            imagettftext($image, 11, 0, $currentX + 45, $currentY + 32, $textColor, $fontFile, $service);
        } else {
            imagestring($image, 5, $currentX + 15, $currentY + 15, $icon, $color);
            imagestring($image, 3, $currentX + 45, $currentY + 20, substr($service, 0, 15), $textColor);
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
    
    return $currentY + ($col > 0 ? $itemHeight + $spacing : 0);
}

/**
 * Draw route trace grid - 3 columns for 9 routes
 */
function drawRouteGrid($image, $x, $y, $width, $data, $fontFile) {
    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $textColor = imagecolorallocate($image, 33, 33, 33);
    $textLight = imagecolorallocate($image, 97, 97, 97);
    $borderColor = imagecolorallocate($image, 224, 224, 224);
    $ctColor = imagecolorallocate($image, 66, 165, 245);   // China Telecom - Blue
    $cuColor = imagecolorallocate($image, 102, 187, 106);  // China Unicom - Green
    $cmColor = imagecolorallocate($image, 255, 167, 38);   // China Mobile - Orange
    
    $itemWidth = 370;
    $itemHeight = 70;
    $cols = 3;
    $spacing = 15;
    
    $currentX = $x;
    $currentY = $y;
    $col = 0;
    
    foreach ($data as $routeLabel => $destination) {
        // Determine color based on destination ISP
        $accentColor = $borderColor;
        if (stripos($destination, 'CT') !== false || stripos($destination, 'Telecom') !== false) {
            $accentColor = $ctColor;
        } elseif (stripos($destination, 'CU') !== false || stripos($destination, 'Unicom') !== false) {
            $accentColor = $cuColor;
        } elseif (stripos($destination, 'CM') !== false || stripos($destination, 'Mobile') !== false) {
            $accentColor = $cmColor;
        }
        
        // Draw card background
        drawRoundedRect($image, $currentX, $currentY, $currentX + $itemWidth, $currentY + $itemHeight, 8, $bgColor, $borderColor);
        
        // Draw colored top bar
        imagefilledrectangle($image, $currentX + 1, $currentY + 1, $currentX + $itemWidth - 1, $currentY + 5, $accentColor);
        
        // Draw route number and destination
        if ($fontFile) {
            imagettftext($image, 11, 0, $currentX + 15, $currentY + 28, $accentColor, $fontFile, $routeLabel);
            // Wrap long destination text
            $maxLen = 45;
            if (strlen($destination) > $maxLen) {
                $line1 = substr($destination, 0, $maxLen);
                $line2 = substr($destination, $maxLen);
                imagettftext($image, 9, 0, $currentX + 15, $currentY + 48, $textColor, $fontFile, $line1);
                imagettftext($image, 9, 0, $currentX + 15, $currentY + 62, $textColor, $fontFile, $line2);
            } else {
                imagettftext($image, 9, 0, $currentX + 15, $currentY + 48, $textColor, $fontFile, $destination);
            }
        } else {
            imagestring($image, 4, $currentX + 15, $currentY + 15, substr($routeLabel, 0, 15), $accentColor);
            imagestring($image, 3, $currentX + 15, $currentY + 35, substr($destination, 0, 40), $textColor);
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
    
    return $currentY + ($col > 0 ? $itemHeight + $spacing : 0);
}

/**
 * 绘制信息卡片
 */
function drawInfoCard($image, $x, $y, $width, $height, $icon, $title, $value, $color, $fontFile) {
    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $textColor = imagecolorallocate($image, 33, 33, 33);
    $textLight = imagecolorallocate($image, 117, 117, 117);
    $borderColor = imagecolorallocate($image, 224, 224, 224);
    
    // 绘制卡片背景
    drawRoundedRect($image, $x, $y, $x + $width, $y + $height, 10, $bgColor, $borderColor);
    
    // 绘制彩色顶部条
    imagefilledrectangle($image, $x + 1, $y + 1, $x + $width - 1, $y + 5, $color);
    
    if ($fontFile) {
        // 图标
        imagettftext($image, 24, 0, $x + 15, $y + 45, $color, $fontFile, $icon);
        // 标题
        imagettftext($image, 10, 0, $x + 15, $y + 65, $textLight, $fontFile, $title);
        // 数值
        imagettftext($image, 14, 0, $x + 15, $y + 90, $textColor, $fontFile, $value);
    } else {
        // 使用内置字体 fallback
        imagestring($image, 5, $x + 15, $y + 15, $icon, $color);
        imagestring($image, 3, $x + 15, $y + 40, $title, $textLight);
        imagestring($image, 4, $x + 15, $y + 60, substr($value, 0, 25), $textColor);
    }
}

/**
 * 绘制圆角矩形
 */
function drawRoundedRect($image, $x1, $y1, $x2, $y2, $radius, $fillColor, $borderColor) {
    // 确保所有坐标都是整数
    $x1 = (int)round($x1);
    $y1 = (int)round($y1);
    $x2 = (int)round($x2);
    $y2 = (int)round($y2);
    $radius = (int)round($radius);
    
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
    // Already in English now, but keep function for compatibility
    return $message;
}
