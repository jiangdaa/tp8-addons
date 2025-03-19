<?php

declare(strict_types=1);

use app\common\exception\BusinessException;
use think\facade\Db;
use think\facade\Event;
use think\facade\Route;
use think\facade\App;
use think\helper\Str;

// 插件类库自动载入
spl_autoload_register(function ($class) {
    $class = ltrim($class, '\\');
    $dir = App::getRootPath();
    $namespace = 'addons';
    if (strpos($class, $namespace) === 0) {
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $namespace . $path;
        if (file_exists($dir)) {
            include $dir;
            return true;
        }
        return false;
    }
    return false;
});

if (!function_exists('hook')) {
    /**
     * 执行插件钩子
     *
     * 通过调用此函数,可以触发一个插件钩子,允许插件在特定的事件点插入自定义代码
     * 这是插件系统的核心功能之一,它使得主题和插件可以无侵入地扩展和修改应用程序的行为
     *
     * @param string $event 钩子的名称,标识要触发的事件
     * @param array|null $params 传递给钩子函数的参数,可以是单个参数或参数数组
     * @param bool $once 指定钩子是否只执行一次.如果设置为true,则在第一次触发后取消订阅
     * @return mixed 返回钩子执行的结果,通常是字符串拼接的结果,也可以是其他数据类型
     */
    function hook(string $event, array $params = null, bool $once = false)
    {
        // 触发事件,调用所有订阅了此事件的钩子函数,并根据$once参数决定是否只执行一次
        $result = Event::trigger($event, $params, $once);
        // 将所有钩子函数的返回值拼接成一个字符串并返回
        // 这样做是为了方便处理多个钩子函数返回的结果,尤其是当它们都是字符串时
        return join('', $result);
    }
}

if (!function_exists('get_addons_info')) {
    /**
     * 获取插件的基本信息
     *
     * 本函数用于通过插件名获取特定插件的基础信息
     * 如果插件不存在或无法实例化,则返回空数组;否则,返回插件实例的info方法返回的信息
     *
     * @param string $name 插件的名称.用于唯一标识一个插件
     * @return mixed|array 如果插件存在并成功实例化,返回插件的信息数组;否则,返回空数组
     */
    function get_addons_info(string $name): mixed
    {
        // 实例化指定名称的插件
        $addon = get_addons_instance($name);
        // 检查插件是否成功实例化,如果没有成功,返回空数组
        if (!$addon) {
            return [];
        }
        // 返回插件实例的信息数组
        return $addon->getInfo();
    }
}

if (!function_exists('set_addons_info')) {
    /**
     * 设置插件的配置信息
     * 本函数用于更新插件的配置信息,通过提供插件名称和一个新的配置数组来更新插件的信息
     * 如果插件不存在或无法实例化,则函数不会进行更新操作并返回空数组
     * 如果插件存在并成功更新信息,则返回插件实例的更新结果
     *
     * @param string $name 插件的名称.如果未提供名称,则默认为空字符串
     * @param array $array 一个包含插件新配置信息的数组.如果未提供数组,则默认为空数组
     * @return mixed|bool 如果插件不存在或无法实例化,返回空数组;如果成功更新插件信息,返回插件实例的更新结果
     */
    function set_addons_info(string $name = '', array $array = []): mixed
    {
        // 实例化指定名称的插件
        $addon = get_addons_instance($name);
        // 检查插件是否成功实例化
        if (!$addon) {
            return [];
        }
        // 调用插件实例的方法来更新插件信息
        return $addon->setInfo($name, $array);
    }
}

if (!function_exists('get_addons_config')) {
    /**
     * 获取插件的配置信息
     *
     * 本函数用于检索指定插件的配置信息
     * 插件配置是插件开发者定义的一组参数,用于定制插件的行为或提供给插件使用者进行配置
     * 通过插件名获取插件实例后,调用插件实例的getConfig方法来获取配置信息
     *
     * @param string $name 插件的名称.这是识别插件的唯一标识符
     * @param bool $type 指定是否获取完整的配置信息.默认为false,表示只获取默认配置
     *                  如果设置为true,则会尝试获取完整的配置信息,包括可能的用户自定义配置
     * @return mixed|array 如果插件存在并成功获取配置,则返回配置信息,这可以是一个数组或其它类型的值
     *                    如果插件不存在或获取配置失败,则返回一个空数组
     */
    function get_addons_config(string $name, bool $type = false): mixed
    {
        // 获取指定插件的实例.
        $addon = get_addons_instance($name);
        // 检查插件实例是否获取成功.
        if (!$addon) {
            return [];
        }
        // 通过插件实例获取配置信息,根据$type的值决定获取默认配置还是完整配置.
        return $addon->getConfig($type);
    }
}

if (!function_exists('set_addons_config')) {
    /**
     * 设置插件的配置信息
     * 本函数用于更新指定插件的配置文件.如果插件存在,则将新配置信息写入插件的配置文件中
     * @param string $name 插件名称.如果未指定名称,则默认为空字符串
     * @param array $array 新的配置信息数组.如果未指定配置数组,则默认为空数组
     * @return mixed|bool 如果插件不存在,则返回空数组.如果插件存在且配置更新成功,则返回true.否则,返回false
     */
    function set_addons_config(string $name = '', array $array = []): mixed
    {
        // 获取指定插件的实例
        $addon = get_addons_instance($name);
        // 检查插件实例是否存在,如果不存在,则返回空数组
        if (!$addon) {
            return [];
        }
        // 调用插件实例的setConfig方法来更新插件的配置文件,并返回操作结果
        return $addon->setConfig($name, $array);
    }
}

if (!function_exists('get_addons_instance')) {
    /**
     * 获取插件的单例对象
     *
     * 本函数用于获取一个插件的单例实例
     * 如果插件已实例化,则直接返回已存在的实例;否则,尝试实例化插件类,并返回新的实例
     * 插件的实例化只会在第一次调用时发生,之后的调用都会返回相同的实例,实现了单例模式
     *
     * @param string $name 插件的名称.这是用于唯一标识插件的字符串
     * @return mixed|null 返回插件的实例对象,如果插件不存在或无法实例化,则返回null
     */
    function get_addons_instance($name): mixed
    {
        // 使用静态变量存储已实例化的插件,避免重复实例化
        static $_addons = [];
        // 检查是否已存在该插件的实例,如果存在直接返回
        if (isset($_addons[$name])) {
            return $_addons[$name];
        }
        // 通过插件名获取插件的类名
        $class = get_addons_class($name);
        // 检查插件类是否存在,如果存在则实例化插件类
        if (class_exists($class)) {
            // 实例化插件类,并传入应用实例作为构造函数的参数
            $_addons[$name] = new $class(app());
            return $_addons[$name];
        } else {
            // 如果插件类不存在,返回null
            return null;
        }
    }
}

if (!function_exists('get_addons_class')) {
    /**
     * 根据插件名和类型获取插件类的完整类名
     *
     * 该函数用于生成并返回指定插件的类名,根据插件名、类型和可选的类名片段
     * 主要用于在不同的插件管理和调用场景中,动态生成插件类的完全限定名
     *
     * @param string $name 插件的名称.这是用于唯一标识插件的字符串
     * @param string $type 类的类型.用于确定生成类名的命名空间.默认为'hook'
     * @param string|null $class 可选的类名片段.当需要指定插件中的特定类时使用,可以是类的路径片段
     * @return mixed|string 返回插件类的完全限定名,如果类不存在则返回空字符串
     */
    function get_addons_class(string $name, string $type = 'hook', string $class = null, string $module = ''): mixed
    {
        // 移除$name中的前后空格
        $name = trim($name);
        // 当$class提供并且包含点号时,处理为命名空间的数组形式
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);
            // 将数组中的最后一个元素转换为StudlyCaps格式,用于类名
            $class[count($class) - 1] = Str::studly(end($class));
            // 通过逆向操作将数组转换回字符串形式的命名空间
            $class = implode('\\', $class);
        } else {
            // 如果没有提供$class或者$class为null,将$name或$class转换为StudlyCaps格式,用于类名
            $class = Str::studly(is_null($class) ? $name : $class);
        }
        // 根据$type生成插件类的命名空间
        switch ($type) {
            // 如果$type为'controller',则生成控制器的命名空间
            case 'controller':
                if ($module) {
                    $namespace = '\\addons\\' . $name . '\\' . $module . '\\controller\\' . $class;
                } else {
                    $namespace = '\\addons\\' . $name . '\\controller\\' . $class;
                }
                break;
            // 默认情况下,生成插件基类的命名空间
            default:
                $namespace = '\\addons\\' . $name . '\\Plugin';
        }
        // 检查命名空间对应的类是否存在,如果存在则返回命名空间字符串,否则返回空字符串
        return class_exists($namespace) ? $namespace : '';
    }
}

if (!function_exists('addons_url')) {
    /**
     * 生成插件的URL地址
     *
     * 该函数用于构建插件的路由URL,支持从当前请求上下文中解析插件、控制器和操作,也支持通过参数直接指定URL的各个部分
     * 可以设置URL的后缀和域名
     *
     * @param string $url 要生成的URL路径,可以是相对路径或者完整的URL字符串
     * @param array $param URL中的参数,以键值对形式提供
     * @param bool|string $suffix URL的后缀,可以是true（使用配置的默认后缀）或者具体的后缀字符串
     * @param bool|string $domain 是否使用域名,可以是true（使用配置的默认域名）或者具体的域名字符串
     * @return mixed|bool|string 返回生成的URL字符串,如果无法生成则返回false
     */
    function addons_url(string $url = '', array $param = [], bool|string $suffix = true, bool|string $domain = false): mixed
    {
        /* 获取当前应用的请求对象 */
        $request = app('request');
        /* 如果URL为空,尝试从当前请求中解析插件、控制器和操作 */
        if (empty($url)) {
            // 从请求中获取当前插件名
            // 生成 url 模板变量
            $addons = $request->addon;
            // 从请求中获取当前控制器名,并将其转换为点分隔的形式
            $controller = $request->controller();
            $controller = str_replace('/', '.', $controller);
            // 从请求中获取当前操作名
            $action = $request->action();
        } else {
            /* 对提供的URL字符串进行处理,以解析出插件、控制器和操作 */
            $url = Str::studly($url);
            $url = parse_url($url);
            /* 如果URL中包含协议（scheme）,则认为是完整的URL,并从中提取插件、控制器和操作 */
            if (isset($url['scheme'])) {
                $addons = strtolower($url['scheme']);
                $controller = $url['host'];
                $action = trim($url['path'], '/');
            } else {
                /* 如果URL中不包含协议,则认为是相对路径,从中解析出控制器和操作 */
                $route = explode('/', $url['path']);
                $addons = $request->addon;
                $action = array_pop($route);
                $controller = array_pop($route) ?: $request->controller();
                $controller = Str::snake((string)$controller);
                /* 如果URL中包含查询参数,则将其合并到参数数组中 */
                if (isset($url['query'])) {
                    parse_str($url['query'], $query);
                    $param = array_merge($query, $param);
                }
            }
        }
        /* 使用解析出的插件、控制器和操作,以及参数数组,构建URL,并根据需要设置后缀和域名 */
        return Route::buildUrl("@addons/{$addons}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}

if (!function_exists('addons_config')) {

    function addons_config(string $field)
    {
        $configPath = get_addon_path() . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($configPath)) {
            $res = require $configPath;
            return $res[$field] ?? null;
        }
        return null;
    }
}

if (!function_exists('addon_reg_menu')) {
    /**
     * 注册菜单
     * @param string $name
     * @param string $path
     * @param string $type
     * @param array $children
     * @param string $icon
     * @return string[]
     */
    function addon_reg_menu(string $name, string $path, string $type, array $children = [], string $icon = ''): array
    {
        if (!str_starts_with($path, '/addons')) {
            $addonName = addon_name();
            $path = "/addons/{$addonName}/{$path}";
        }
        $m = ['name' => $name, 'icon' => $icon, 'path' => $path, 'type' => $type];
        if (count($children)) {
            $m['children'] = $children;
        }
        return $m;
    }
}

if (!function_exists('addon_import_sql')) {

    /**
     * 导入插件sql
     * @return bool
     */
    function addon_import_sql(): bool
    {
        $fileName = 'install.sql';
        $sqlFile = root_path('addons/' . addon_name()) . DIRECTORY_SEPARATOR . $fileName;
        $dbConfig = addons_config('db');

        if (is_file($sqlFile)) {
            $lines = file($sqlFile);
            $templine = '';
            foreach ($lines as $line) {
                if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*') {
                    continue;
                }
                $templine .= $line;
                if (substr(trim($line), -1, 1) == ';') {
                    $templine = str_ireplace('__PREFIX__', $dbConfig['prefix'], $templine);
                    $templine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $templine);
                    try {
                        Db::execute($templine);
                    } catch (\PDOException $e) {
                        //$e->getMessage();
                    }
                    $templine = '';
                }
            }
        }
        return true;
    }
}

if (!function_exists('addon_db_tables')) {

    /**
     * 获取插件创建的表
     * @param string $name 插件名
     * @return array
     */
    function addon_db_tables(string $name): array
    {
        $regex = "/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z_]+)`?/mi";
        $sqlFile = root_path('addons/' . $name) . DIRECTORY_SEPARATOR . 'install.sql';
        $dbConfig = addons_config('db');
        $tables = [];
        if (is_file($sqlFile)) {
            preg_match_all($regex, file_get_contents($sqlFile), $matches);
            if ($matches && isset($matches[2]) && $matches[2]) {
                $prefix = $dbConfig['prefix'];
                $tables = array_map(function ($item) use ($prefix) {
                    return str_replace("__PREFIX__", $prefix, $item);
                }, $matches[2]);
            }
        }
        return $tables;
    }

}

if (!function_exists('addon_remove_sql')) {
    /**
     * 移除导入的sql
     * @return bool
     */
    function addon_remove_sql(): bool
    {
        $tables = addon_db_tables(addon_name());
        $dbConfig = addons_config('db');
        $prefix = $dbConfig['prefix'] ?? false;
        if (!$prefix) {
            return false;
        }
        try {
            //删除插件关联表
            foreach ($tables as $table) {
                //忽略非插件标识的表名
                if (!preg_match("/^{$prefix}/", $table)) {
                    continue;
                }
                Db::execute("DROP TABLE IF EXISTS `{$table}`");
            }
        } catch (\Exception $exception) {
            return false;
        }
        return true;
    }
}

if (!function_exists('addon_export_sql')) {

    /**
     * 导出插件的表数据和结构
     * @return void
     */
    function addon_export_sql(): void
    {
        $addonConfig = addons_config('db');

        $tablePrefix = '';
        if ($addonConfig && array_key_exists('prefix', $addonConfig) && $addonConfig['prefix']) {
            $tablePrefix = $addonConfig['prefix'];
        }
        if (empty($tablePrefix)) return;
        // 获取数据库连接
        $db = Db::connect();

        // 获取所有表
        $tables = $db->query("SHOW TABLES");

        // 创建 SQL 文件
        $sqlFile = get_addon_path() . DIRECTORY_SEPARATOR . time() . '.sql';
        file_put_contents($sqlFile, "SET NAMES utf8mb4;\n\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        // 遍历表
        $dbName = env('DATABASE.DB_NAME');
        foreach ($tables as $table) {
            $table_name = $table['Tables_in_' . $dbName]; // 获取表名
            if (str_starts_with($table_name, $tablePrefix)) {
                // 导出表结构
                $createTableSql = $db->query("SHOW CREATE TABLE `$table_name`")[0]['Create Table'];
                file_put_contents($sqlFile, "\nDROP TABLE IF EXISTS `$table_name`;" . "\n" . $createTableSql . ";\n\n", FILE_APPEND);

                // 导出数据
                $data = $db->query("SELECT * FROM `$table_name`");
                $insertSql = "BEGIN;\n";
                foreach ($data as $row) {
                    $columns = array_keys($row);
                    $values = array_map(function ($value) {
                        return is_null($value) ? 'NULL' : "'" . addslashes($value) . "'";
                    }, array_values($row));
                    $insertSql .= "INSERT INTO `$table_name` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $insertSql .= "COMMIT;";
                file_put_contents($sqlFile, $insertSql, FILE_APPEND);
            }
        }
        file_put_contents($sqlFile, "\n\nSET FOREIGN_KEY_CHECKS = 1;", FILE_APPEND);
    }
}

if (!function_exists('addon_resource_copy')) {

    /**
     * 复制插件包资源文件
     * @param $src
     * @param $dst
     * @return void
     */
    function addon_resource_copy($src, $dst): void
    {  // 原目录，复制到的目录
        if (!$src) {
            $src = get_addon_path() . DIRECTORY_SEPARATOR . 'assets';
        }
        if (!$dst) {
            $dst = public_path('addons' . addon_name());
        }
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                    addon_resource_copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                } else {
                    copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        closedir($dir);
    }

}

if (!function_exists('addon_resource_remove')) {

    /**
     * 删除插件资源文件
     * @param string|null $dir
     * @return void
     */
    function addon_resource_remove(string $dir = null): void
    {
        if (!$dir) {
            $dir = public_path('addons' . addon_name());
        }
        $dh = @opendir($dir);
        if (!$dh) return;
        while ($file = @readdir($dh)) {
            if ($file != "." && $file != "..") {
                $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                if (!is_dir($fullPath)) {
                    @unlink($fullPath);
                } else {
                    addon_resource_remove($fullPath);
                }
            }
        }
        closedir($dh);
        @rmdir($dir);
    }
}

if (!function_exists('get_addon_path')) {

    /**
     * 获取插件路径
     * @param string $addonName
     * @return string
     */
    function get_addon_path(string $addonName = null): string
    {
        if (!$addonName) {
            $addonName = addon_name();
        }
        return root_path('addons') . $addonName;
    }

}

if (!function_exists('addon_name')) {

    /**
     * 获取插件名称
     * @return array|mixed|string|null
     */
    function addon_name(): mixed
    {
        $addon = request()->param('addon');
        if (!$addon) {
            $backtrace = debug_backtrace();
            $keyword = DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR;
            // 获取第一个非内核函数的信息
            foreach ($backtrace as $item) {
                if (array_key_exists('file', $item)) {
                    $file = $item['file'];
                    if (str_contains($file, $keyword)) {
                        if (str_ends_with($file, 'Plugin.php')) {
                            $arr = explode(DIRECTORY_SEPARATOR, substr($file, strpos($file, $keyword)));
                            $addon = $arr[count($arr) - 2];
                            break;
                        } else {
                            list(, , $addon) = explode(DIRECTORY_SEPARATOR, substr($file, strpos($file, $keyword)));
                            break;
                        }
                    }
                }

            }
        }
        if (!$addon) {
            return null;
        }
        return $addon;
    }

}

if (!function_exists('addon_resource_copy')) {

    /**
     * 复制插件包资源文件
     * @param $src
     * @param $dst
     * @return void
     */
    function addon_resource_copy($src = null, $dst = null): void
    {  // 原目录，复制到的目录
        if (!$src) {
            $src = get_addon_path() . DIRECTORY_SEPARATOR . 'assets';
        }
        if (!$dst) {
            $dst = public_path('addons' . DIRECTORY_SEPARATOR . addon_name());
        }
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                    addon_resource_copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                } else {
                    copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        closedir($dir);
    }

}

if (!function_exists('addon_resource_remove')) {

    /**
     * 删除插件资源文件
     * @param string|null $dir
     * @return void
     */
    function addon_resource_remove(string $dir = null): void
    {
        if (!$dir) {
            $dir = public_path('addons' .DIRECTORY_SEPARATOR. addon_name());
        }
        $dh = @opendir($dir);
        if (!$dh) return;
        while ($file = @readdir($dh)) {
            if ($file != "." && $file != "..") {
                $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                if (!is_dir($fullPath)) {
                    @unlink($fullPath);
                } else {
                    addon_resource_remove($fullPath);
                }
            }
        }
        closedir($dh);
        @rmdir($dir);
    }
}
