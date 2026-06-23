<?php

/**
 * PHPUnit 引导文件
 *
 * 加载 Composer autoload，并提供 config() 桩函数（独立测试环境无 TP/Hyperf 时使用）。
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('config')) {
    /**
     * 单元测试环境下的 config 桩函数（框架运行时由 TP/Hyperf 提供）
     */
    function config(string $key, $default = null)
    {
        return $default;
    }
}
