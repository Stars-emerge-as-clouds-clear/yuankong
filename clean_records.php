<?php
// 清理所有记录脚本

// 设置存储目录
$base_path = dirname(__FILE__) . '/storage';

// 要清理的存储目录列表
$clean_dirs = array(
    'devices',
    'commands',
    'screenshots',
    'processes',
    'logs',
    'files'
);

// 要清理的根目录列表
$root_clean_dirs = array(
    'screenshots',
    'uploads'
);

echo "开始清理记录...<br>";

// 遍历存储目录并清理
foreach ($clean_dirs as $dir) {
    $dir_path = $base_path . '/' . $dir;
    if (is_dir($dir_path)) {
        // 获取目录中的所有文件
        $files = glob($dir_path . '/*');
        $count = 0;
        
        // 删除所有文件
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }
        
        echo "已清理 storage/{$dir} 目录下的 {$count} 条记录<br>";
    } else {
        echo "storage/{$dir} 目录不存在<br>";
    }
}

// 遍历根目录并清理
foreach ($root_clean_dirs as $dir) {
    $dir_path = dirname(__FILE__) . '/' . $dir;
    if (is_dir($dir_path)) {
        // 获取目录中的所有文件
        $files = glob($dir_path . '/*');
        $count = 0;
        
        // 删除所有文件
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }
        
        echo "已清理 {$dir} 目录下的 {$count} 条记录<br>";
    } else {
        echo "{$dir} 目录不存在<br>";
    }
}

echo "<br>清理完成！所有记录已删除。<br>";
echo "保留了以下内容：<br>";
echo "- 用户信息<br>";
echo "- 系统设置<br>";
echo "- 分组信息<br>";
echo "- 命令模板<br>";
?>