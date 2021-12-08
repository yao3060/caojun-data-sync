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

## 查看日志

```shell
ls -la src/logs/
```
