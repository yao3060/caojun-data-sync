# 使用说明

## 环境

-   linux server （Ubuntu LST）
-   PHP8+

## 安装

上传文件到服务器目录 `/var/www/fuyou-2021`

```shell
cd /var/www/fuyou-2021
cp .env.sample .env
composer install
composer dump-autoload -o
```

## 配置

正确填写 `.env` 中的变量。

## 使用

```shell
php src/index.php
```

## 在定时任务重使用

参考：

-   [How To Use Cron to Automate Tasks on Ubuntu 18.04](https://www.digitalocean.com/community/tutorials/how-to-use-cron-to-automate-tasks-ubuntu-1804)
-   [早上 1 点执行](https://crontab.guru/#0_1_*_*_*)

```shell
0 1 * * * /usr/bin/php /var/www/fuyou-2021/src/index.php > /dev/null 2>&1
```

## 查看日志

```shell
ls -la src/logs/
```
