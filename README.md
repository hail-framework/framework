# Hail-Framework

[![Latest Stable Version](https://poser.pugx.org/hail/framework/version)](https://packagist.org/packages/hail/framework)
[![Latest Unstable Version](https://poser.pugx.org/hail/framework/v/unstable)](//packagist.org/packages/hail/framework)
[![Total Downloads](https://poser.pugx.org/hail/framework/downloads)](https://packagist.org/packages/hail/framework)
[![Monthly Downloads](https://poser.pugx.org/hail/framework/d/monthly)](https://packagist.org/packages/hail/framework)
[![License](https://poser.pugx.org/hail/framework/license)](https://packagist.org/packages/hail/framework)

基于 PHP 7.1 的 MVC 框架

## Installation

### Composer (recommended)

``composer require "hail/framework:dev-master"``

## 框架设计

### 设计方向
1. 尽可能使用最新的 PHP
2. 减少依赖，除非十分必要不会 composer 依赖其他库
3. 第一目标是方便使用，其次才是功能覆盖
4. 持续优化，对代码效率时刻保持关注
5. 使用 PHP 扩展得到更好的性能
6. 使用 Zephir 将框架编译为扩展

### PHP版本依赖
- PHP 版本更新往往会带来性能、代码质量、开发效率的提高，所以框架希望尽可能的使用最新的版本
- 框架 1.0 之前，有极大的可能使用最新的 PHP 版本
- 框架 1.0 之后，当有新的 PHP 版本发布，会审视新版本对性能和开发的影响，再确定是否提高依赖
- 当 PHP 版本依赖提高之后，主要开发将基于最新版本进行，并保留一个老版本的分支，只进行必要的维护

### 库的依赖
- 尽可能不使用 composer 依赖，避免引入并不会使用到的功能
- 框架会将一些第三方库代码引用，并进行适当的修改以符合框架本身设计与功能需求
- 这些库版权理所当然依然属于库作者自己，引入中会尽量保留作者的版权声明，如果有遗漏请提醒: flyinghail@msn.com

### Zephir
- Zephir 是 PHP 开发很好的补充，不过只有当框架已经比较完善的基础上，才会尝试使用 Zephir 提高性能
- 在打开 Opcache 的情况下， PHP 本身已经相当快，一些简单的功能，并不会比使用 C 扩展慢很多
- 如果您追求极致的性能，可以先试试： [Phalcon](http://phalconphp.com/) ([github](https://github.com/phalcon/cphalcon)) 或者 [Ice](http://www.iceframework.org/) ([github](https://github.com/ice/framework))

### 遵循 PSR
- [PSR-2 Coding Style Guide](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
- [PSR-3 Logger Interface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md)
- [PSR-4 Autoloading Standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-meta.md)
- [PSR-6 Caching Interface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-6-cache.md)
- [PSR-7 HTTP message interfaces](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md)
- [PSR-11 Container Interface](https://github.com/php-fig/fig-standards/blob/master/accepted/PPSR-11-container.md)
- [PSR-14 Event Manager](https://github.com/php-fig/fig-standards/blob/master/proposed/event-manager.md)
- [PSR-15 HTTP Handlers](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-15-request-handlers.md)
- [PSR-16 Simple Cache](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-16-simple-cache.md)
- [PSR-18 HTTP Client](https://github.com/php-fig/fig-standards/tree/master/proposed/http-client/)

## 框架功能

### Optimize & OptimizeTrait
- 自动选择 PHP 缓存 extension： ['yac', 'pcache', 'xcache', 'wincache', 'apcu']，类内缓存，最大限度的减少性能损失

### Config
- 可以使用 Yaml 或者 PHP 进行配置
- 优先使用 [yaml extension](http://pecl.php.net/package/yaml)
- 从 Yaml 生成 PHP 配置缓存，避免重复解析 Yaml 结构
- 使用 OptimizeTrait 减少文件读取带来的性能损失

### Factory
- 基于配置构造对象
- 继承框架的默认配置
- 同配置从 Factory 得到的对象唯一

### Container & Dependency Injection
- 基于配置预生成静态 Container，性能几乎等同于手写代码
- 可动态配置、添加、替换已有的 Component
- 基于 Reflection 进行依赖注入，不支持 auto-wiring，所有依赖必须是基于已配置的 Component

### Router
- 基于树形结构，查询一个节点的时间复杂度为 O(log n)，速度均匀，没有所谓的最坏情况
- 支持参数和单节点的正则匹配
- 利用 ['app', 'controller', 'action'] 参数调用框架 Controller，也可以使用 Clouser 
- 使用 OptimizeTrait 缓存路由树结构，避免每次访问重新构造路由表

### I18N
- 使用 gettext 处理多语言
- 优先使用 [gettext extension](http://php.net/manual/gettext.installation.php)

### Database
- 通过 PDO 支持 MySQL、PostgreSQL、Sybase、Oracle、SQL Server、Sqlite
- 基于数组生成 SQL 语句，自动 prepare
- 支持 [php-cp extension](https://github.com/swoole/php-cp) *[未测试]*
- 提供简单 ORM 支持 *[未测试]*
- 基于命令行提供 Migration 工具 *[未测试]*

### Redis
- 简单的 PHP Native Redis Client
- 优先使用 [phpredis extension](http://pecl.php.net/package/redis/)
- 支持 [php-cp extension](https://github.com/swoole/php-cp) *[未测试]*
- 支持 Redis Cluster *[未测试]*
- 支持 Redis Sentinel *[未测试]* 

### Template
- 直接使用原生 PHP 作为模板语言
- 使用 VUE.js 作为默认的 JS 动态处理库
- 支持编译简单的 VUE.js 模板语法为原生模板 *[未测试]*
- 支持 TAL 语法, 语法参考 [PHPTAL](https://phptal.org/)，未 100% 实现 *[未测试]* 

### Swoole
- 基于命令行的 Http Server *[未测试]*

### Console
- 基于命令行工具进行项目优化、Migration、服务管理等

### 有用的库
项目中如果有需要可以自行 composer 安装

- [Mobile Detect](https://github.com/serbanghita/Mobile-Detect) 通过 User-Agent 和 header 检测移动设备
- [Crawler Detect](https://github.com/JayBizzle/Crawler-Detect) 通过 User-Agent 和 header 检测爬虫
- [Spout](https://github.com/box/spout) 读写 Excel 文件 (CSV, XLSX, ODS)，速度快，支持超大文件，占用内存小