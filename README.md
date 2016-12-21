# Hail-Framework

基于 PHP7 的 MVC 框架

## About 框架设计

### 设计方向
1. 尽可能使用最新的 PHP
2. 减少依赖，除非十分必要不会 composer 依赖其他库
3. 第一目标是方便使用，其次才是功能覆盖
4. 增强性能，对程序执行速度时刻保持关注
5. 使用 PHP 扩展来提高性能
6. 使用 Zephir 将框架编译为扩展

### PHP版本依赖
- 框架希望使用尽可能新的PHP，追随随版本进步带来的性能提高，利用部分新的语法令开发更有效率
- 框架 1.0 之前，有极大的可能使用最新的 PHP 版本
- 框架 1.0 之后，当有新的PHP版本发布，框架会视情况提高依赖
- 当 PHP 版本依赖提高之后，主要开发将基于最新版的PHP进行，并保留一个依赖于PHP老版本的分支，只进行必要的维护

### 库的依赖
- 尽可能不使用 composer 依赖，避免引入太多无用的代码
- 框架会将一些第三方库代码引入，并进行适当的优化、修改以符合框架本身设计和功能需求
- 这些库版权理所当然依然属于库作者自己，引入中会尽量保留作者的版权声明，如果有遗漏请提醒: flyinghail@msn.com

### Zephir
- Zephir 是 PHP 开发者很好的工具，不过只有当框架已经比较完善的基础上，才会尝试使用 Zephir 提高性能
- 在打开 Opcache 的情况下， PHP 本身已经相当快，一些简单的功能，并不会比使用 C 的扩展慢很多
- 如果您追求极致的性能，可以先试试： [Phalcon](http://phalconphp.com/) ([github](https://github.com/phalcon/cphalcon)) 或者 [Ice](http://www.iceframework.org/) ([github](https://github.com/ice/framework))
