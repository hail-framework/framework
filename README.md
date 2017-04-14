# Hail-Framework

基于 PHP 7.1 的 MVC 框架

## About 框架设计

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
- [PSR-2 Coding Style Guide](http://www.php-fig.org/psr/psr-2/)
- [PSR-4 Autoloading Standard](http://www.php-fig.org/psr/psr-4/)
- [PSR-6 Caching Interface](http://www.php-fig.org/psr/psr-6/)
- [PSR-7 HTTP message interfaces](http://www.php-fig.org/psr/psr-7/)
- [PSR-11 Container Interface](https://github.com/container-interop/fig-standards/blob/master/proposed/container.md)
- [PSR-14 Event Manager](https://github.com/php-fig/fig-standards/blob/master/proposed/event-manager.md)
- [PSR-15 HTTP Middlewares](https://github.com/php-fig/fig-standards/blob/master/proposed/http-middleware)
- [PSR-16 Simple Cache](http://www.php-fig.org/psr/psr-16/)
- [PSR-17 HTTP Factories](https://github.com/php-fig/fig-standards/tree/master/proposed/http-factory)
