# NodeLoc聚合测评脚本 [![Hits](https://hits.seeyoufarm.com/api/count/incr/badge.svg?url=https%3A%2F%2Fgithub.com%2Feverett7623%2Fnodeloc_vps_test%2Fblob%2Fmain%2FNlbench.sh&count_bg=%2379C83D&title_bg=%23555555&icon=&icon_color=%23E7E7E7&title=hits&edge_flat=false)](https://hits.seeyoufarm.com)

这是NodeLoc提供给各位用户的主机聚合测评脚本，可一键自动对主机进行Yabs、融合怪、IP质量、流媒体解锁，三网测速，回程路由等测评，测评结束后会生成一个txt文件，可在线自行复制内容 发到NodeLoc论坛。

**版本：** 2025-01-21 v1.2.7

**Github地址：** https://github.com/nodeloc/nodeloc_vps_test

**VPS 选购:** [NodeLoc VPS](https://www.nodeloc.com/vps)

### 使用方法
确保用户为ROOT，主机网络通畅，复制下面任意命令运行

**支持CentOS/Debian/Ubuntu/Deepin**

```bash
curl -o Nlbench.sh https://raw.githubusercontent.com/nodeloc/nodeloc_vps_test/main/Nlbench.sh && chmod +x Nlbench.sh && ./Nlbench.sh
```
** 短链接
```bash
curl -sSL abc.sd | bash
```

** 中国大陆 

```bash
curl -o Nlbench.sh https://ghfast.top/https://raw.githubusercontent.com/nodeloc/nodeloc_vps_test/main/Cnbench.sh && chmod +x Nlbench.sh && ./Nlbench.sh
```

### 效果图
#### 运行截图
![image](https://s.rmimg.com/2024/09/21/56db40f55c1d901066fe15973b70af06.png)

![image](https://s.rmimg.com/2024/09/21/b6a48d97e8124f452ef069901fe727d6.png)

![image](https://s.rmimg.com/2024/09/21/d697aac320074e6a0316aea2ae953efd.png)

#### 生成内容
**测试结束后将生成一个txt文件，点击或者复制到浏览器后，可直接点击复制到[NodeLoc论坛](https://www.nodeloc.com/)，无需进行更多操作**
![image](https://github.com/user-attachments/assets/543a7741-943d-412c-9db7-58e5c66754c2)
![image](https://github.com/user-attachments/assets/8f7b5cf7-a566-422b-9aca-56a7fbb237be)

### 🆕 图片报告功能

测试完成后，脚本会自动生成精美的图片报告，方便分享：

**特点：**
- 📊 自动解析测试结果中的关键指标
- 🎨 NodeLoc 品牌配色，美观大方
- 📱 自适应高度，内容清晰展示
- 🔗 一键复制图片链接

**使用方式：**
1. 运行测试脚本后，结果上传成功时会显示图片链接
2. 直接在论坛、社交媒体中使用图片链接分享
3. 支持 BBCode、Markdown、HTML 等多种格式

**图片内容包含：**
- YABS 测试（CPU、内存、磁盘）
- IP 质量检测
- 流媒体解锁状态
- 多线程/单线程测速
- 响应延迟测试

详细说明请查看：[图片生成API文档](server/IMAGE_API.md)

## 免责声明
* NodeLoc聚合测评脚本属于自用分享工具，本脚本仅为各类脚本聚合。
* 工具中所有脚本均来自互联网，未对官方脚本文件进行任何更改，不对脚本安全性负责。如果你比较在意安全，请勿使用各类一键脚本。

## 问题反馈

如果您在使用过程中遇到问题，或者有功能建议，欢迎通过 [GitHub Issues](https://github.com/everett7623/nodeloc_vps_test/issues) 提交反馈。

## 许可协议

本项目采用 [AGPL-3.0 license](LICENSE) 许可。

### 鸣谢
1. [Yabs脚本](https://yabs.sh)——[masonr](https://github.com/masonr)
2. [融合怪脚本](https://gitlab.com/spiritysdx/za/-/raw/main/ecs.sh)——[spirit lhl](https://gitlab.com/spiritysdx)
3. [IP质量测试脚本](https://IP.Check.Place)——[xykt](https://github.com/xykt/)
4. [流媒体测试脚本](https://media.ispvps.com)——[xykt](https://github.com/xykt/)
5. [响应测试脚本](https://nodebench.mereith.com/scripts/curltime.sh)——[nodebench](https://nodebench.mereith.com)
6. [测速脚本](https://bash.icu/speedtest)——[i-abc](https://github.com/i-abc)
7. [回程测试脚本](https://raw.githubusercontent.com/Chennhaoo/Shell_Bash/master/AutoTrace.sh)——[陈豪](https://github.com/Chennhaoo/)

特别感谢以上作者提供的脚本
