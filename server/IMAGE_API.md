# VPS测试报告图片生成API

## 功能说明

服务端现在支持根据测试结果自动生成图片报告，方便在社交媒体、论坛等场景分享测试结果。

## 使用方法

### 1. 自动生成（推荐）

使用 `Nlbench.sh` 脚本进行测试后，结果上传成功时会自动生成图片链接。

输出示例：
```
测试结果已上传，您可以在以下链接查看：
https://bench.nodeloc.cc/result/2026/01/NL1736320123-ABC123.txt
Plain https://bench.nodeloc.cc/2026/01/NL1736320123-ABC123.txt
图片报告: https://bench.nodeloc.cc/image.php?file=NL1736320123-ABC123.txt&year=2026&month=01
```

### 2. 手动访问

如果已有测试结果文件，可以直接访问图片API：

**URL格式:**
```
https://bench.nodeloc.cc/image.php?file=<文件名>&year=<年份>&month=<月份>
```

**参数说明:**
- `file`: 测试结果文件名（例如：NL1736320123-ABC123.txt）
- `year`: 文件所在年份目录（例如：2026）
- `month`: 文件所在月份目录（例如：01）

**示例:**
```
https://bench.nodeloc.cc/image.php?file=NL1736320123-ABC123.txt&year=2026&month=01
```

## 图片内容

生成的图片包含以下测试数据：

### YABS 测试
- CPU 型号
- 内存容量
- 磁盘容量
- 磁盘速度

### IP 质量
- IP 类型（数据中心/住宅IP）
- 黑名单记录统计

### 流媒体解锁
- Netflix
- YouTube
- Disney+
- HBO
- TikTok

### 多线程/单线程测速
- 平均下载速度
- 平均上传速度

### 响应测试
- 平均延迟

## 图片规格

- **格式**: PNG
- **宽度**: 800px
- **高度**: 根据内容自适应（通常 600-1200px）
- **背景**: 白色
- **配色**: NodeLoc 品牌配色（蓝色主题）

## 技术实现

- 使用 PHP GD 库生成图片
- 支持中文字体渲染（需要 DejaVu Sans 字体）
- 自动解析测试结果中的关键指标
- 响应式高度设计，根据内容自动调整

## 字体支持

图片生成器会尝试使用以下字体：
- `/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf`

如果字体文件不存在，会自动降级使用 GD 内置字体。

### 安装字体（可选）

在 Ubuntu/Debian 系统上：
```bash
sudo apt-get install fonts-dejavu-core
```

在 CentOS/RHEL 系统上：
```bash
sudo yum install dejavu-sans-fonts
```

## 错误处理

如果生成失败，会返回错误信息图片：
- 文件不存在
- 无法读取文件
- 参数错误

## 缓存

- 图片动态生成，不缓存
- 每次访问都会重新解析数据并生成新图片
- 响应头设置 `no-cache` 确保获取最新结果

## 性能优化建议

如需在生产环境使用，建议：

1. **启用缓存**: 修改 `image.php` 添加文件缓存机制
2. **CDN 加速**: 将生成的图片上传到 CDN
3. **异步生成**: 上传时预生成图片并存储
4. **限流保护**: 添加访问频率限制

## 示例集成

### 在论坛中使用

BBCode:
```
[img]https://bench.nodeloc.cc/image.php?file=NL1736320123-ABC123.txt&year=2026&month=01[/img]
```

Markdown:
```markdown
![VPS测试报告](https://bench.nodeloc.cc/image.php?file=NL1736320123-ABC123.txt&year=2026&month=01)
```

HTML:
```html
<img src="https://bench.nodeloc.cc/image.php?file=NL1736320123-ABC123.txt&year=2026&month=01" alt="VPS测试报告">
```

## 更新日志

### v1.0.0 (2026-01-08)
- ✨ 初始版本发布
- ✨ 支持自动解析测试结果
- ✨ 生成美观的图片报告
- ✨ 集成到测试脚本工作流
