# 常见问题解答 (FAQ)

## 图片生成相关

### Q: 为什么图片生成报错 "open_basedir restriction in effect"？

**A:** 这是因为 PHP 配置了 `open_basedir` 限制（常见于宝塔面板等管理面板），PHP 无法访问系统字体目录。

**解决方案：**

1. **使用自动安装脚本（推荐）：**
   ```bash
   cd /www/wwwroot/bench.nodeloc.cc
   chmod +x install_fonts.sh
   ./install_fonts.sh
   ```

2. **手动下载字体到网站目录：**
   ```bash
   mkdir -p /www/wwwroot/bench.nodeloc.cc/fonts
   wget -O /www/wwwroot/bench.nodeloc.cc/fonts/DejaVuSans.ttf \
     https://github.com/dejavu-fonts/dejavu-fonts/raw/master/ttf/DejaVuSans.ttf
   chmod 644 /www/wwwroot/bench.nodeloc.cc/fonts/DejaVuSans.ttf
   ```

3. **或者在宝塔面板中：**
   - 进入网站设置 → 网站目录
   - 取消勾选 "防跨站攻击(open_basedir)"
   - 然后安装系统字体：`apt-get install fonts-dejavu-core`

**注意：** 即使没有字体文件，图片仍可以生成，只是会使用 GD 内置字体，中文显示效果较差。

---

### Q: 如何检查服务器环境是否满足要求？

**A:** 访问 `check_requirements.php` 页面：
```
http://your-domain/check_requirements.php
```

该页面会自动检测：
- PHP 版本
- PHP GD 扩展
- 字体文件
- open_basedir 限制
- 其他依赖

---

### Q: PHP GD 扩展未安装怎么办？

**A:** 根据操作系统安装：

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install php-gd
sudo systemctl restart apache2  # 或 nginx + php-fpm
```

**CentOS/RHEL:**
```bash
sudo yum install php-gd
sudo systemctl restart httpd  # 或 nginx + php-fpm
```

---

### Q: 图片生成速度很慢怎么办？

**A:** 考虑以下优化：

1. **启用图片缓存：**
   修改 `image.php`，添加缓存机制
   
2. **使用 CDN：**
   将生成的图片上传到 CDN

3. **预生成图片：**
   在上传测试结果时同时生成并保存图片

4. **使用更轻量的字体：**
   或完全使用内置字体

---

### Q: 能否支持其他图片格式（如 JPEG、WebP）？

**A:** 当前仅支持 PNG 格式。PNG 提供了最好的文本清晰度和透明度支持。

如需其他格式，可以修改 `image.php` 中的：
```php
// 将 imagepng() 改为
imagejpeg($image, null, 90);  // JPEG
// 或
imagewebp($image, null, 90);  // WebP
```

---

## 上传相关

### Q: 上传失败，提示 "Failed to create directory"？

**A:** 检查目录权限：
```bash
# 查看权限
ls -la /www/wwwroot/bench.nodeloc.cc

# 设置权限
sudo chown -R www-data:www-data /www/wwwroot/bench.nodeloc.cc
sudo chmod 775 /www/wwwroot/bench.nodeloc.cc
```

---

### Q: 文件大小限制是多少？

**A:** 当前限制为 **5MB**。

如需修改：
1. 编辑 `index.php`，修改文件大小检查
2. 修改 PHP 配置：
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```
3. 修改 Web 服务器配置（Nginx）：
   ```nginx
   client_max_body_size 10M;
   ```

---

### Q: 可以上传什么类型的文件？

**A:** 仅支持纯文本文件（.txt），其他格式会被拒绝。

---

## 字体相关

### Q: 支持哪些字体？

**A:** 当前仅支持 DejaVu Sans 字体。这是一个开源字体，支持多种语言包括中文。

---

### Q: 如何使用自定义字体？

**A:** 步骤：
1. 将 TTF 字体文件放到 `fonts/` 目录
2. 修改 `image.php` 中的字体路径数组
3. 确保字体文件权限为 644

---

### Q: 为什么中文显示为方块或乱码？

**A:** GD 库的内置字体不支持中文，只能显示 ASCII 字符。

**原因：**
- 未安装 TrueType 字体（DejaVu Sans）
- 使用了 GD 内置字体（`imagestring()`），它不支持 Unicode

**解决方案：**

1. **安装字体文件（推荐）：**
   ```bash
   cd /www/wwwroot/bench.nodeloc.cc
   chmod +x install_fonts.sh
   ./install_fonts.sh
   ```

2. **手动下载字体：**
   ```bash
   mkdir -p /www/wwwroot/bench.nodeloc.cc/fonts
   wget -O /www/wwwroot/bench.nodeloc.cc/fonts/DejaVuSans.ttf \
     https://github.com/dejavu-fonts/dejavu-fonts/raw/master/ttf/DejaVuSans.ttf
   ```

3. **检查字体是否生效：**
   访问 `check_requirements.php` 确认字体状态

**临时方案：**
- 如果不安装字体，图片会自动使用英文显示
- 所有中文标签会被翻译成英文，避免乱码
- 数据内容正常显示

---

## 性能和优化

### Q: 如何提升性能？

**A:** 建议：

1. **启用 OPcache：**
   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   ```

2. **使用 Nginx + PHP-FPM：**
   比 Apache + mod_php 性能更好

3. **启用 HTTP/2：**
   提升并发性能

4. **使用 Redis 缓存：**
   缓存生成的图片

---

## 安全相关

### Q: 如何防止滥用？

**A:** 建议措施：

1. **启用速率限制：**
   使用 Nginx limit_req 或 fail2ban

2. **添加 API 密钥认证**

3. **监控磁盘空间：**
   设置定期清理旧文件的 cron 任务

4. **使用 Cloudflare：**
   提供 DDoS 防护和速率限制

---

### Q: 如何限制访问来源？

**A:** 在 Nginx 配置中：
```nginx
location /image.php {
    # 仅允许特定 IP
    allow 1.2.3.4;
    deny all;
    
    # 或使用 Referer 检查
    valid_referers none blocked *.nodeloc.com;
    if ($invalid_referer) {
        return 403;
    }
}
```

---

## 宝塔面板相关

### Q: 在宝塔面板中如何部署？

**A:** 步骤：

1. **创建网站：**
   - 面板 → 网站 → 添加站点
   - 域名：bench.nodeloc.cc
   - PHP 版本：7.4+

2. **上传文件：**
   - 将 `server/` 目录所有文件上传到网站根目录

3. **安装 PHP 扩展：**
   - 软件商店 → PHP → 设置 → 安装扩展
   - 安装：gd、mbstring、curl

4. **安装字体：**
   ```bash
   cd /www/wwwroot/bench.nodeloc.cc
   chmod +x install_fonts.sh
   ./install_fonts.sh
   ```

5. **设置权限：**
   ```bash
   chown -R www:www /www/wwwroot/bench.nodeloc.cc
   chmod 775 /www/wwwroot/bench.nodeloc.cc
   ```

6. **配置 SSL（可选）：**
   - 网站设置 → SSL → Let's Encrypt

---

### Q: 宝塔面板中如何查看错误日志？

**A:** 
- 面板 → 网站 → 设置 → 日志
- 或直接查看：`/www/wwwlogs/bench.nodeloc.cc.error.log`

---

## 更多帮助

如果以上解答未能解决您的问题，请：

1. 访问 `check_requirements.php` 查看详细环境信息
2. 查看错误日志文件
3. 提交 [GitHub Issue](https://github.com/nodeloc/nodeloc_vps_test/issues)

记得提供：
- 操作系统版本
- PHP 版本
- Web 服务器类型和版本
- 完整错误信息
- `check_requirements.php` 的检查结果
