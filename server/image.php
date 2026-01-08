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

// 设置内容类型为PNG图片
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
    }
    
    return $result;
}

/**
 * 解析YABS测试结果
 */
function parseYABS($content) {
    $metrics = [];
    
    // CPU信息
    if (preg_match('/CPU Model\s*:\s*(.+)/', $content, $match)) {
        $metrics['CPU'] = trim($match[1]);
    }
    
    // 内存
    if (preg_match('/Total RAM\s*:\s*(.+)/', $content, $match)) {
        $metrics['内存'] = trim($match[1]);
    }
    
    // 磁盘
    if (preg_match('/Total Disk\s*:\s*(.+)/', $content, $match)) {
        $metrics['磁盘'] = trim($match[1]);
    }
    
    // 下载速度
    if (preg_match('/fio Disk Speed.*?(\d+\.?\d*)\s*(MB\/s|GB\/s)/s', $content, $match)) {
        $metrics['磁盘速度'] = $match[1] . ' ' . $match[2];
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
    $services = ['Netflix', 'YouTube', 'Disney+', 'HBO', 'TikTok'];
    
    foreach ($services as $service) {
        if (preg_match("/{$service}[:\s]+(.+)/i", $content, $match)) {
            $status = trim($match[1]);
            if (stripos($status, '解锁') !== false || stripos($status, 'Yes') !== false) {
                $metrics[$service] = '✓';
            } elseif (stripos($status, '失败') !== false || stripos($status, 'No') !== false) {
                $metrics[$service] = '✗';
            }
        }
    }
    
    return $metrics;
}

/**
 * 解析测速结果
 */
function parseSpeedTest($content) {
    $metrics = [];
    
    // 提取上传下载速度
    preg_match_all('/(\d+\.?\d*)\s*Mbps.*?(\d+\.?\d*)\s*Mbps/i', $content, $matches, PREG_SET_ORDER);
    
    if (!empty($matches)) {
        $avgDown = 0;
        $avgUp = 0;
        $count = count($matches);
        
        foreach ($matches as $match) {
            $avgDown += floatval($match[1]);
            $avgUp += floatval($match[2]);
        }
        
        if ($count > 0) {
            $metrics['平均下载'] = round($avgDown / $count, 2) . ' Mbps';
            $metrics['平均上传'] = round($avgUp / $count, 2) . ' Mbps';
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
 * 生成结果图片
 */
function generateResultImage($data) {
    // 图片尺寸
    $width = 800;
    $headerHeight = 80;
    $sectionHeight = 40;
    $metricsLineHeight = 30;
    
    // 计算总高度
    $totalMetrics = 0;
    foreach ($data['sections'] as $section) {
        if (!empty($section['metrics'])) {
            $totalMetrics += count($section['metrics']);
        }
    }
    
    $sectionsCount = count($data['sections']);
    $height = $headerHeight + ($sectionsCount * $sectionHeight) + ($totalMetrics * $metricsLineHeight) + 60;
    
    // 创建图片
    $image = imagecreatetruecolor($width, $height);
    
    // 定义颜色
    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $headerBg = imagecolorallocate($image, 13, 71, 161);
    $sectionBg = imagecolorallocate($image, 187, 222, 251);
    $textColor = imagecolorallocate($image, 33, 33, 33);
    $whiteColor = imagecolorallocate($image, 255, 255, 255);
    $borderColor = imagecolorallocate($image, 200, 200, 200);
    $successColor = imagecolorallocate($image, 76, 175, 80);
    $failColor = imagecolorallocate($image, 244, 67, 54);
    
    // 填充背景
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
    
    // 设置字体路径（使用GD库的内置字体）
    $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    $fontExists = file_exists($fontFile);
    
    // 绘制标题区域
    imagefilledrectangle($image, 0, 0, $width, $headerHeight, $headerBg);
    
    $title = "NodeLoc VPS 测试报告";
    $subtitle = "生成时间: " . $data['timestamp'];
    
    if ($fontExists) {
        imagettftext($image, 20, 0, 20, 35, $whiteColor, $fontFile, $title);
        imagettftext($image, 12, 0, 20, 60, $whiteColor, $fontFile, $subtitle);
    } else {
        imagestring($image, 5, 20, 20, $title, $whiteColor);
        imagestring($image, 3, 20, 50, $subtitle, $whiteColor);
    }
    
    // 绘制测试结果
    $currentY = $headerHeight + 20;
    
    foreach ($data['sections'] as $sectionName => $section) {
        if (empty($section['metrics'])) {
            continue;
        }
        
        // 绘制section标题
        imagefilledrectangle($image, 10, $currentY, $width - 10, $currentY + $sectionHeight, $sectionBg);
        imagerectangle($image, 10, $currentY, $width - 10, $currentY + $sectionHeight, $borderColor);
        
        if ($fontExists) {
            imagettftext($image, 14, 0, 20, $currentY + 27, $textColor, $fontFile, $sectionName);
        } else {
            imagestring($image, 4, 20, $currentY + 12, $sectionName, $textColor);
        }
        
        $currentY += $sectionHeight + 5;
        
        // 绘制metrics
        foreach ($section['metrics'] as $key => $value) {
            $text = "{$key}: {$value}";
            
            // 根据内容选择颜色
            $color = $textColor;
            if ($value === '✓') {
                $color = $successColor;
            } elseif ($value === '✗') {
                $color = $failColor;
            }
            
            if ($fontExists) {
                imagettftext($image, 11, 0, 30, $currentY + 20, $color, $fontFile, $text);
            } else {
                imagestring($image, 3, 30, $currentY + 8, $text, $color);
            }
            
            $currentY += $metricsLineHeight;
        }
        
        $currentY += 10;
    }
    
    // 添加水印
    $watermark = "Generated by bench.nodeloc.cc";
    if ($fontExists) {
        imagettftext($image, 10, 0, $width - 220, $height - 15, $borderColor, $fontFile, $watermark);
    } else {
        imagestring($image, 2, $width - 220, $height - 20, $watermark, $borderColor);
    }
    
    // 输出图片
    imagepng($image);
    imagedestroy($image);
}

/**
 * 生成错误图片
 */
function generateErrorImage($message) {
    $width = 600;
    $height = 200;
    
    $image = imagecreatetruecolor($width, $height);
    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $textColor = imagecolorallocate($image, 244, 67, 54);
    $borderColor = imagecolorallocate($image, 200, 200, 200);
    
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
    imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);
    
    $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    
    if (file_exists($fontFile)) {
        imagettftext($image, 16, 0, 50, 100, $textColor, $fontFile, $message);
    } else {
        imagestring($image, 5, 50, 90, $message, $textColor);
    }
    
    imagepng($image);
    imagedestroy($image);
}
