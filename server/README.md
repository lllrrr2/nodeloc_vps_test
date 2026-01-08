# NodeLoc VPS Test Server

VPS测试结果服务端，用于接收、存储和展示测试结果。

## 功能特性

### 1. 结果上传 (index.php)
- 接收客户端上传的测试结果
- 自动按年月目录组织文件
- 生成唯一文件名
- 文件大小限制：5MB
- 仅支持纯文本格式

### 2. 结果展示 (bench.html)
- 美观的标签页展示测试结果
- 支持复制论坛代码
- 响应式设计
- 语法高亮

### 3. 🆕 图片生成 (image.php)
- 根据测试结果自动生成图片报告
- PNG格式，800px宽度
- 支持中文字体渲染
- 动态解析测试数据

### 4. 测试工具 (test_image.html)
- 图片生成功能测试页面
- 可视化操作界面
- 实时预览生成结果

## 文件结构

```
server/
├── index.php           # 上传接口
├── image.php          # 图片生成器
├── bench.html         # 结果展示页面
├── test_image.html    # 图片测试页面
├── .htaccess          # Apache配置
├── IMAGE_API.md       # 图片API文档
└── README.md          # 本文件

生成的文件目录结构：
├── 2026/
│   ├── 01/
│   │   ├── NL1736320123-ABC123.txt
│   │   └── ...
│   └── 02/
│       └── ...
```

## 部署要求

### 系统要求
- PHP 7.4 或更高版本
- Apache 或 Nginx Web服务器
- PHP GD 扩展（图片生成必需）

### PHP扩展
```bash
# Ubuntu/Debian
sudo apt-get install php-gd php-mbstring

# CentOS/RHEL
sudo yum install php-gd php-mbstring
```

### 字体支持（可选但推荐）
```bash
# Ubuntu/Debian
sudo apt-get install fonts-dejavu-core

# CentOS/RHEL
sudo yum install dejavu-sans-fonts
```

## 安装步骤

### 1. 上传文件
将 `server` 目录下的所有文件上传到Web服务器：

```bash
# 示例：上传到 /var/www/html/bench
cp -r server/* /var/www/html/bench/
```

### 2. 设置权限
确保Web服务器可以写入目录：

```bash
# 创建上传目录
mkdir -p /var/www/html/bench/{2026,2027}/{01..12}

# 设置权限
chown -R www-data:www-data /var/www/html/bench
chmod -R 755 /var/www/html/bench
chmod 775 /var/www/html/bench  # 允许创建年月目录
```

### 3. 配置Web服务器

#### Apache (.htaccess)
项目已包含 `.htaccess` 配置文件，确保启用了 `mod_rewrite`：

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx
在 Nginx 配置中添加：

```nginx
server {
    listen 80;
    server_name bench.example.com;
    root /var/www/html/bench;
    index index.php index.html;

    # 最大上传大小
    client_max_body_size 5M;

    # PHP处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 结果页面重写
    location ~ ^/result/(.*)$ {
        rewrite ^/result/(.*)$ /bench.html?file=$1 last;
    }

    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
    }

    # 文本文件缓存
    location ~* \.(txt)$ {
        expires 1h;
        add_header Cache-Control "public";
    }

    # 图片动态生成，不缓存
    location = /image.php {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }
}
```

### 4. 测试安装

访问以下URL测试功能：

```
# 图片生成测试页面
http://your-domain/test_image.html

# 上传测试
curl -X POST --data-binary @test.txt http://your-domain/index.php
```

## API接口

### 上传接口

**URL:** `POST /index.php`

**Content-Type:** `application/octet-stream` 或 `text/plain`

**请求体:** 测试结果文本内容

**响应:**
```
http://your-domain/2026/01/NL1736320123-ABC123.txt
Image: http://your-domain/image.php?file=NL1736320123-ABC123.txt&year=2026&month=01
```

**错误响应:**
- `404`: 未提供数据
- `500`: 文件过大（>5MB）或其他错误

### 图片生成接口

**URL:** `GET /image.php`

**参数:**
- `file`: 文件名（必需）
- `year`: 年份（必需）
- `month`: 月份（必需，两位数）

**响应:** PNG图片

**示例:**
```
http://your-domain/image.php?file=NL1736320123-ABC123.txt&year=2026&month=01
```

详细说明请查看：[IMAGE_API.md](IMAGE_API.md)

## 维护

### 清理旧文件

建议定期清理旧的测试结果：

```bash
# 删除90天前的文件
find /var/www/html/bench -name "*.txt" -mtime +90 -delete

# 删除空目录
find /var/www/html/bench -type d -empty -delete
```

可以添加到 crontab：

```bash
# 每天凌晨2点清理
0 2 * * * find /var/www/html/bench -name "*.txt" -mtime +90 -delete
```

### 监控磁盘空间

```bash
# 查看占用空间
du -sh /var/www/html/bench

# 统计文件数量
find /var/www/html/bench -name "*.txt" | wc -l
```

### 日志

PHP错误日志位置（根据配置不同）：
- Ubuntu/Debian: `/var/log/apache2/error.log` 或 `/var/log/nginx/error.log`
- CentOS/RHEL: `/var/log/httpd/error_log` 或 `/var/log/nginx/error.log`

## 安全建议

1. **限制上传大小**: 已在配置中限制为5MB
2. **文件类型验证**: 仅接受纯文本文件
3. **路径安全**: 使用 `basename()` 防止路径遍历
4. **访问控制**: 配置防火墙限制访问
5. **HTTPS**: 生产环境建议启用HTTPS
6. **速率限制**: 使用 fail2ban 或类似工具防止滥用

## 性能优化

### 启用缓存

在 `.htaccess` 中已配置：
- 文本文件：1小时缓存
- HTML文件：30分钟缓存
- 图片：不缓存（动态生成）

### 图片缓存方案（可选）

如果图片访问量大，建议修改 `image.php` 添加缓存：

```php
// 在 image.php 开头添加
$cacheDir = __DIR__ . '/cache/';
$cacheFile = $cacheDir . md5($filePath) . '.png';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    header('Content-Type: image/png');
    readfile($cacheFile);
    exit;
}

// ... 生成图片代码 ...

// 保存缓存
file_put_contents($cacheFile, $imageData);
```

### CDN加速

建议将生成的图片上传到CDN：
- 阿里云OSS
- 腾讯云COS
- 七牛云
- Cloudflare

## 故障排除

### 图片显示空白
1. 检查 PHP GD 扩展是否安装
2. 查看 PHP错误日志
3. 确认文件权限正确
4. 测试字体文件是否存在

### 上传失败
1. 检查目录权限（需要可写）
2. 确认文件大小不超过5MB
3. 查看 Web服务器错误日志
4. 检查 PHP 配置（`upload_max_filesize`, `post_max_size`）

### 文本文件无法访问
1. 检查文件是否存在
2. 确认年月目录结构正确
3. 查看 Web服务器错误日志

## 许可协议

本项目采用 AGPL-3.0 许可协议。

## 支持

如有问题，请提交 [GitHub Issue](https://github.com/nodeloc/nodeloc_vps_test/issues)
