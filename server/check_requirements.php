<?php
/**
 * 服务器环境检查脚本
 * 检查图片生成功能所需的依赖
 */
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>环境检查 - NodeLoc VPS Test Server</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 2px solid #e0e0e0;
        }
        .check-item.success {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        .check-item.warning {
            background: #fff3e0;
            border-color: #ff9800;
        }
        .check-item.error {
            background: #ffebee;
            border-color: #f44336;
        }
        .check-icon {
            font-size: 24px;
            margin-right: 15px;
            min-width: 30px;
        }
        .check-content {
            flex: 1;
        }
        .check-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .check-description {
            font-size: 14px;
            color: #666;
        }
        .install-command {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 8px;
            overflow-x: auto;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 5px;
            text-align: center;
        }
        .summary.all-good {
            background: #e8f5e9;
        }
        .summary.has-issues {
            background: #fff3e0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0d47a1;
        }
        .section-title {
            font-size: 20px;
            margin: 30px 0 15px;
            color: #0d47a1;
            border-bottom: 2px solid #1976d2;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 环境检查</h1>
            <p>NodeLoc VPS Test Server - 依赖检查</p>
        </div>
        
        <div class="content">
            <?php
            $allGood = true;
            $warnings = 0;
            $errors = 0;
            
            // 检查项目列表
            $checks = [];
            
            // 1. PHP版本检查
            $phpVersion = phpversion();
            $phpOk = version_compare($phpVersion, '7.4.0', '>=');
            $checks[] = [
                'title' => 'PHP 版本',
                'status' => $phpOk ? 'success' : 'error',
                'message' => "当前版本: {$phpVersion}",
                'description' => $phpOk ? '版本符合要求 (>= 7.4)' : '需要 PHP 7.4 或更高版本',
                'install' => !$phpOk ? '请升级 PHP 版本' : ''
            ];
            if (!$phpOk) $errors++;
            
            // 2. GD 扩展检查
            $gdLoaded = extension_loaded('gd');
            $checks[] = [
                'title' => 'PHP GD 扩展',
                'status' => $gdLoaded ? 'success' : 'error',
                'message' => $gdLoaded ? '已安装' : '未安装',
                'description' => $gdLoaded ? 'GD 图像处理库可用' : '图片生成功能需要 GD 扩展',
                'install' => !$gdLoaded ? 'Ubuntu/Debian: sudo apt-get install php-gd
CentOS/RHEL: sudo yum install php-gd
重启服务: sudo systemctl restart apache2' : ''
            ];
            if (!$gdLoaded) {
                $errors++;
                $allGood = false;
            }
            
            // 3. GD 功能检查
            if ($gdLoaded) {
                $gdInfo = gd_info();
                $pngSupport = $gdInfo['PNG Support'] ?? false;
                $ttfSupport = $gdInfo['FreeType Support'] ?? false;
                
                $checks[] = [
                    'title' => 'GD PNG 支持',
                    'status' => $pngSupport ? 'success' : 'error',
                    'message' => $pngSupport ? '支持' : '不支持',
                    'description' => 'PNG 图片格式支持',
                    'install' => ''
                ];
                
                $checks[] = [
                    'title' => 'GD FreeType 支持',
                    'status' => $ttfSupport ? 'success' : 'warning',
                    'message' => $ttfSupport ? '支持' : '不支持',
                    'description' => $ttfSupport ? 'TrueType 字体渲染可用' : '无法使用 TTF 字体，将使用内置字体',
                    'install' => ''
                ];
                if (!$ttfSupport) $warnings++;
            }
            
            // 4. 字体文件检查
            $fontPaths = [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/dejavu-sans/DejaVuSans.ttf'
            ];
            $fontFound = false;
            $fontPath = '';
            foreach ($fontPaths as $path) {
                if (file_exists($path)) {
                    $fontFound = true;
                    $fontPath = $path;
                    break;
                }
            }
            
            $checks[] = [
                'title' => 'DejaVu Sans 字体',
                'status' => $fontFound ? 'success' : 'warning',
                'message' => $fontFound ? "已找到: {$fontPath}" : '未找到',
                'description' => $fontFound ? '可以正常渲染中文字体' : '将使用 GD 内置字体（中文显示效果较差）',
                'install' => !$fontFound ? 'Ubuntu/Debian: sudo apt-get install fonts-dejavu-core
CentOS/RHEL: sudo yum install dejavu-sans-fonts' : ''
            ];
            if (!$fontFound) $warnings++;
            
            // 5. mbstring 扩展检查
            $mbstringLoaded = extension_loaded('mbstring');
            $checks[] = [
                'title' => 'PHP mbstring 扩展',
                'status' => $mbstringLoaded ? 'success' : 'warning',
                'message' => $mbstringLoaded ? '已安装' : '未安装',
                'description' => $mbstringLoaded ? '多字节字符串处理可用' : '建议安装以更好地处理中文',
                'install' => !$mbstringLoaded ? 'Ubuntu/Debian: sudo apt-get install php-mbstring
CentOS/RHEL: sudo yum install php-mbstring' : ''
            ];
            if (!$mbstringLoaded) $warnings++;
            
            // 6. 目录写入权限检查
            $writable = is_writable(__DIR__);
            $checks[] = [
                'title' => '目录写入权限',
                'status' => $writable ? 'success' : 'error',
                'message' => $writable ? '可写' : '不可写',
                'description' => $writable ? '可以创建上传目录和文件' : '需要写入权限以保存上传的文件',
                'install' => !$writable ? 'sudo chown -R www-data:www-data ' . __DIR__ . '
sudo chmod 775 ' . __DIR__ : ''
            ];
            if (!$writable) {
                $errors++;
                $allGood = false;
            }
            
            // 7. curl 扩展检查（用于客户端）
            $curlLoaded = extension_loaded('curl');
            $checks[] = [
                'title' => 'PHP cURL 扩展',
                'status' => $curlLoaded ? 'success' : 'warning',
                'message' => $curlLoaded ? '已安装' : '未安装',
                'description' => '用于一些网络请求功能',
                'install' => !$curlLoaded ? 'Ubuntu/Debian: sudo apt-get install php-curl
CentOS/RHEL: sudo yum install php-curl' : ''
            ];
            if (!$curlLoaded) $warnings++;
            
            // 显示检查结果
            echo '<h2 class="section-title">必需依赖</h2>';
            foreach (array_slice($checks, 0, 3) as $check) {
                displayCheck($check);
            }
            
            echo '<h2 class="section-title">可选依赖（推荐）</h2>';
            foreach (array_slice($checks, 3) as $check) {
                displayCheck($check);
            }
            
            // 显示汇总
            $allGood = ($errors === 0);
            ?>
            
            <div class="summary <?php echo $allGood ? 'all-good' : 'has-issues'; ?>">
                <?php if ($allGood && $warnings === 0): ?>
                    <h3>✅ 所有检查通过！</h3>
                    <p>您的服务器环境已准备就绪，可以正常使用所有功能。</p>
                    <a href="test_image.html" class="btn">测试图片生成</a>
                <?php elseif ($allGood): ?>
                    <h3>⚠️ 基本功能可用</h3>
                    <p>发现 <?php echo $warnings; ?> 个警告项，建议安装以获得最佳体验。</p>
                    <a href="test_image.html" class="btn">测试图片生成</a>
                <?php else: ?>
                    <h3>❌ 存在错误</h3>
                    <p>发现 <?php echo $errors; ?> 个错误，<?php echo $warnings; ?> 个警告。请先解决错误项。</p>
                <?php endif; ?>
            </div>
            
            <?php
            function displayCheck($check) {
                echo '<div class="check-item ' . $check['status'] . '">';
                echo '<div class="check-icon">';
                switch ($check['status']) {
                    case 'success':
                        echo '✅';
                        break;
                    case 'warning':
                        echo '⚠️';
                        break;
                    case 'error':
                        echo '❌';
                        break;
                }
                echo '</div>';
                echo '<div class="check-content">';
                echo '<div class="check-title">' . htmlspecialchars($check['title']) . '</div>';
                echo '<div class="check-description">';
                echo htmlspecialchars($check['message']);
                if ($check['description']) {
                    echo ' - ' . htmlspecialchars($check['description']);
                }
                echo '</div>';
                if (!empty($check['install'])) {
                    echo '<div class="install-command">' . nl2br(htmlspecialchars($check['install'])) . '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</body>
</html>
