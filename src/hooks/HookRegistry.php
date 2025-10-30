<?php

namespace tp8a\hooks;

class HookRegistry
{
    private static array $hooks = [];
    private static array $hookNames = [];

    public static function register($hookName, $callback): void
    {
        if (!in_array($hookName, self::$hookNames)) {
            self::$hooks[$hookName][] = $callback;
        }
        self::$hookNames[] = $hookName;

    }

    public static function trigger($hookName, $params = [], &$return = null): void
    {
        // 检查是否存在该钩子
        if (isset(self::$hooks[$hookName])) {
            foreach (self::$hooks[$hookName] as $callback) {
                // 执行钩子回调函数
                $return = call_user_func_array($callback, $params);
            }
        }
    }
}
