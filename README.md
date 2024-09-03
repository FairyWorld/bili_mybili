## 🎥 Mybili

**bilibili 收藏夹下载工具**

主要是解决翻看收藏夹时，很多视频莫名其妙不见了的现象，也不知道原来的视频标题和内容，进而无法溯源和寻找备份。

**该工具能够将你的收藏夹全部备份下来。**

🛠️功能如下：

- ⏰ 每小时获取你的收藏夹所有视频，缓存标题、描述、封面等重要信息。
- 🚀 自动通过队列，将你收藏的视频按照最高画质下载一份到本地。
- 📺 提供友好的 web 页面展示你的收藏夹列表信息，以及进行在线播放预览。


## 📚 使用方法

该演示以最公共简单的方式创建一个服务，让你能够快速的体验到，你可以根据实际的需求和现实修改其中配置和部署方式。

- 程序只依赖 redis 数据库来存储你的收藏夹信息

### 🐳 1.通过 docker 部署于你 nas

创建存储目录
```bash
mkdir /mnt/user/mybili/data -p
mkdir /mnt/user/mybili/redis -p
```


复制一份 .env 修改 redis 配置为你自己的实际配置，将文件存储于 /mnt/user/mybili/.env

参考主要修改内容如下：
```
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=3
REDIS_PREFIX=
```


创建一个 docker 服务配置

 `/mnt/user/mybili/docker-compose.yml`

```yml
version: '3'

services: 
    mybili:
        image: ellermister/mybili
        ports:
            - "5151:443"
        volumes:
            - "./data:/app/storage/app/public"
            - "./.env:/app/.env"
        command: redis redis-server --save 60 1 --loglevel warning
    redis:
        image: redis
        volumes:
            - "./redis:/data"
        
```

### 🍪 2.获取 cookie

在你的浏览器安装插件

[Get cookies.txt LOCALLY](https://chrome.google.com/webstore/detail/cclelndahbckbenkjhflpdbgdldlbecc)

在你登录哔哩哔哩后，通过插件导出 cookie 文件。需要格式为：`Netscape`

访问 `https://your-ip:5151/cookie`

上传 cookie 文件，稍后将自动开始同步你的收藏夹了！🍡🍡🍡