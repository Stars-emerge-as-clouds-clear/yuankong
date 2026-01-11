<?php
// 启动会话
session_start();

// 验证登录状态
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 未登录，重定向到登录页面
    header('Location: login.php');
    exit;
}

// 获取当前用户信息
$current_user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60"> <!-- 1分钟自动刷新 -->
    <title>PHP 远控后台管理</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PHP 远控后台管理</h1>
            <div class="user-info">
                <span>欢迎, <?php echo $current_user['username']; ?></span>
                <a href="?action=logout" class="logout-btn">退出登录</a>
            </div>
        </div>
        
        <!-- 功能导航 -->
        <div class="nav">
            <a href="?page=devices" class="nav-btn">设备管理</a>
            <a href="?page=commands" class="nav-btn">命令管理</a>
            <a href="?page=screenshots" class="nav-btn">屏幕监控</a>
            <a href="?page=files" class="nav-btn">文件管理</a>
            <a href="?page=processes" class="nav-btn">进程管理</a>
            <a href="?page=groups" class="nav-btn">分组管理</a>
            <a href="?page=templates" class="nav-btn">命令模板</a>
            <a href="?page=logs" class="nav-btn">操作日志</a>
            <a href="?page=settings" class="nav-btn">系统设置</a>
        </div>
        
        <?php
        // 处理退出登录
        if (isset($_GET['action']) && $_GET['action'] == 'logout') {
            // 引入文件存储类
            require_once 'storage.php';
            
            // 添加退出日志
            $storage->addLog(array(
                'username' => $current_user['username'],
                'action' => 'logout',
                'ip' => $_SERVER['REMOTE_ADDR'],
                'message' => '用户退出登录'
            ));
            
            // 销毁会话
            session_destroy();
            // 重定向到登录页面
            header('Location: login.php');
            exit;
        }
        ?>
        
        <?php
        // 引入文件存储类
        require_once 'storage.php';
        
        // 获取当前页面
        $page = isset($_GET['page']) ? $_GET['page'] : 'devices';
        
        // 处理命令发送
        if (isset($_POST['send_command'])) {
            $device_id = $_POST['device_id'];
            $command = $_POST['command'];
            
            if (!empty($device_id) && !empty($command)) {
                $storage->saveCommand(array(
                    'device_id' => $device_id,
                    'command' => $command,
                    'status' => 0
                ));
                echo '<div class="success">命令发送成功！</div>';
            }
        }
        
        // 处理设备信息更新
        if (isset($_POST['update_device'])) {
            $device_id = $_POST['device_id'];
            $device_name = $_POST['device_name'];
            $info = $_POST['info'];
            
            if (!empty($device_id) && !empty($device_name)) {
                $device = $storage->getDevice($device_id);
                if ($device) {
                    $device['device_name'] = $device_name;
                    $device['info'] = $info;
                    $storage->saveDevice($device_id, $device);
                    echo '<div class="success">设备信息更新成功！</div>';
                }
            }
        }
        
        // 设备管理页面
        if ($page == 'devices') {
            // 获取设备列表
            $devices = $storage->getAllDevices();
            
            echo '<h2>设备管理</h2>';
            echo '<div class="device-list">';
            
            foreach ($devices as $device) {
                // 检查设备是否离线（超过1分钟未发送心跳包）
                $last_online = strtotime($device['last_online']);
                $current_time = time();
                $is_online = $device['status'] && ($current_time - $last_online < 60); // 1分钟未心跳则离线
                
                echo '<div class="device-item">';
                echo '<div class="device-header">';
                echo '<h3>' . $device['device_name'] . '</h3>';
                echo '<span class="status ' . ($is_online ? 'online' : 'offline') . '">' . ($is_online ? '在线' : '离线') . '</span>';
                echo '</div>';
                echo '<div class="device-info">';
                echo '<p><strong>最后心跳:</strong> ' . $device['last_online'] . ' (' . ($current_time - $last_online) . '秒前)</p>';
                echo '<p><strong>设备ID:</strong> ' . $device['device_id'] . '</p>';
                echo '<p><strong>创建时间:</strong> ' . $device['created_at'] . '</p>';
                echo '<p><strong>最后在线:</strong> ' . $device['last_online'] . '</p>';
                echo '<p><strong>设备信息:</strong> ' . (isset($device['info']) ? $device['info'] : '无') . '</p>';
                
                echo '</div>';
                
                // 发送命令表单
                echo '<div class="send-command">';
                echo '<h4>发送命令</h4>';
                echo '<form method="post">';
                echo '<input type="hidden" name="device_id" value="' . $device['device_id'] . '">';
                echo '<textarea name="command" rows="3" placeholder="输入要执行的命令..."></textarea>';
                echo '<button type="submit" name="send_command" class="btn btn-primary">发送命令</button>';
                echo '</form>';
                echo '</div>';
                
                // 命令历史
                echo '<div class="command-history">';
                echo '<h4>命令历史</h4>';
                $commands = $storage->getDeviceCommands($device['device_id'], 5);
                
                if (empty($commands)) {
                    echo '<p>暂无命令历史</p>';
                } else {
                    echo '<table class="command-table">';
                    echo '<tr><th>命令</th><th>状态</th><th>创建时间</th><th>执行时间</th></tr>';
                    foreach ($commands as $cmd) {
                        $status_text = array(0 => '待执行', 1 => '执行中', 2 => '已完成')[$cmd['status']];
                        echo '<tr>';
                        echo '<td>' . substr($cmd['command'], 0, 50) . (strlen($cmd['command']) > 50 ? '...' : '') . '</td>';
                        echo '<td>' . $status_text . '</td>';
                        echo '<td>' . $cmd['created_at'] . '</td>';
                        echo '<td>' . (isset($cmd['execute_time']) ? $cmd['execute_time'] : '未执行') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                echo '</div>';
                
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        // 命令管理页面
        if ($page == 'commands') {
            // 获取所有命令
            $commands = $storage->getAllCommands();
            
            echo '<h2>命令管理</h2>';
            echo '<table class="commands-table">';
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>设备</th>';
            echo '<th>命令</th>';
            echo '<th>状态</th>';
            echo '<th>创建时间</th>';
            echo '<th>执行时间</th>';
            echo '<th>完成时间</th>';
            echo '<th>结果</th>';
            echo '</tr>';
            
            foreach ($commands as $cmd) {
                // 获取设备名称和状态
                $device = $storage->getDevice($cmd['device_id']);
                $device_name = $device ? $device['device_name'] : $cmd['device_id'];
                
                // 检查设备是否离线
                $is_online = false;
                if ($device) {
                    $last_online = strtotime($device['last_online']);
                    $is_online = time() - $last_online < 60;
                }
                
                $status_text = array(0 => '待执行', 1 => '执行中', 2 => '已完成')[$cmd['status']];
                echo '<tr>';
                echo '<td>' . $cmd['id'] . '</td>';
                echo '<td>' . $device_name . ' <span class="status ' . ($is_online ? 'online' : 'offline') . '">' . ($is_online ? '在线' : '离线') . '</span></td>';
                echo '<td><pre>' . $cmd['command'] . '</pre></td>';
                echo '<td>' . $status_text . '</td>';
                echo '<td>' . $cmd['created_at'] . '</td>';
                echo '<td>' . (isset($cmd['execute_time']) ? $cmd['execute_time'] : '未执行') . '</td>';
                echo '<td>' . (isset($cmd['finish_time']) ? $cmd['finish_time'] : '未完成') . '</td>';
                echo '<td><pre>' . (isset($cmd['result']) ? $cmd['result'] : '无') . '</pre></td>';
                echo '</tr>';
            }
            
            echo '</table>';
        }
        
        // 分组管理页面
        if ($page == 'groups') {
            // 处理分组添加
            if (isset($_POST['add_group'])) {
                $group_name = $_POST['group_name'];
                $group_desc = $_POST['group_desc'];
                
                if (!empty($group_name)) {
                    $group_id = 'group_' . uniqid();
                    $storage->saveGroup($group_id, array(
                        'name' => $group_name,
                        'description' => $group_desc
                    ));
                    echo '<div class="success">分组添加成功！</div>';
                }
            }
            
            echo '<h2>分组管理</h2>';
            
            // 添加分组表单
            echo '<div class="add-form">';
            echo '<h3>添加新分组</h3>';
            echo '<form method="post">';
            echo '<div class="form-row">';
            echo '<div class="form-group">';
            echo '<label>分组名称</label>';
            echo '<input type="text" name="group_name" placeholder="请输入分组名称" required>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>分组描述</label>';
            echo '<input type="text" name="group_desc" placeholder="请输入分组描述">';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>&nbsp;</label>';
            echo '<button type="submit" name="add_group" class="btn btn-primary">添加分组</button>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            
            // 分组列表
            $groups = $storage->getAllGroups();
            echo '<h3>分组列表</h3>';
            echo '<table class="groups-table">';
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>分组名称</th>';
            echo '<th>描述</th>';
            echo '<th>创建时间</th>';
            echo '<th>操作</th>';
            echo '</tr>';
            
            foreach ($groups as $group) {
                echo '<tr>';
                echo '<td>' . $group['id'] . '</td>';
                echo '<td>' . $group['name'] . '</td>';
                echo '<td>' . (isset($group['description']) ? $group['description'] : '') . '</td>';
                echo '<td>' . $group['created_at'] . '</td>';
                echo '<td>';
                echo '<a href="?page=groups&action=edit&id=' . $group['id'] . '" class="btn btn-small">编辑</a>';
                echo '<a href="?page=groups&action=delete&id=' . $group['id'] . '" class="btn btn-small btn-danger" onclick="return confirm(\'确定要删除吗？\')">删除</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
        // 命令模板页面
        if ($page == 'templates') {
            // 处理模板添加
            if (isset($_POST['add_template'])) {
                $template_name = $_POST['template_name'];
                $template_command = $_POST['template_command'];
                $template_desc = $_POST['template_desc'];
                
                if (!empty($template_name) && !empty($template_command)) {
                    $template_id = 'template_' . uniqid();
                    $storage->saveTemplate($template_id, array(
                        'name' => $template_name,
                        'command' => $template_command,
                        'description' => $template_desc
                    ));
                    echo '<div class="success">模板添加成功！</div>';
                }
            }
            
            echo '<h2>命令模板</h2>';
            
            // 添加模板表单
            echo '<div class="add-form">';
            echo '<h3>添加新模板</h3>';
            echo '<form method="post">';
            echo '<div class="form-row">';
            echo '<div class="form-group">';
            echo '<label>模板名称</label>';
            echo '<input type="text" name="template_name" placeholder="请输入模板名称" required>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>模板描述</label>';
            echo '<input type="text" name="template_desc" placeholder="请输入模板描述">';
            echo '</div>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>命令内容</label>';
            echo '<textarea name="template_command" rows="4" placeholder="请输入命令内容" required></textarea>';
            echo '</div>';
            echo '<button type="submit" name="add_template" class="btn btn-primary">添加模板</button>';
            echo '</form>';
            echo '</div>';
            
            // 模板列表
            $templates = $storage->getAllTemplates();
            echo '<h3>模板列表</h3>';
            echo '<table class="templates-table">';
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>模板名称</th>';
            echo '<th>描述</th>';
            echo '<th>命令内容</th>';
            echo '<th>创建时间</th>';
            echo '<th>操作</th>';
            echo '</tr>';
            
            foreach ($templates as $template) {
                echo '<tr>';
                echo '<td>' . $template['id'] . '</td>';
                echo '<td>' . $template['name'] . '</td>';
                echo '<td>' . (isset($template['description']) ? $template['description'] : '') . '</td>';
                echo '<td><pre>' . substr($template['command'], 0, 50) . (strlen($template['command']) > 50 ? '...' : '') . '</pre></td>';
                echo '<td>' . $template['created_at'] . '</td>';
                echo '<td>';
                echo '<a href="?page=templates&action=edit&id=' . $template['id'] . '" class="btn btn-small">编辑</a>';
                echo '<a href="?page=templates&action=delete&id=' . $template['id'] . '" class="btn btn-small btn-danger" onclick="return confirm(\'确定要删除吗？\')">删除</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
        // 操作日志页面
        if ($page == 'logs') {
            echo '<h2>操作日志</h2>';
            
            $logs = $storage->getAllLogs(100);
            echo '<table class="logs-table">';
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>用户名</th>';
            echo '<th>操作</th>';
            echo '<th>IP地址</th>';
            echo '<th>时间</th>';
            echo '<th>详情</th>';
            echo '</tr>';
            
            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . $log['id'] . '</td>';
                echo '<td>' . $log['username'] . '</td>';
                echo '<td>' . $log['action'] . '</td>';
                echo '<td>' . $log['ip'] . '</td>';
                echo '<td>' . $log['created_at'] . '</td>';
                echo '<td>' . $log['message'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
        // 屏幕监控页面
        if ($page == 'screenshots') {
            echo '<h2>屏幕监控</h2>';
            
            // 处理截图设置保存
            if (isset($_POST['save_screenshot_setting'])) {
                $device_id = $_POST['device_id'];
                $interval = intval($_POST['interval']);
                $enabled = isset($_POST['enabled']) ? true : false;
                
                $storage->saveScreenshotSetting($device_id, array(
                    'interval' => $interval,
                    'enabled' => $enabled
                ));
                echo '<div class="success">截图设置保存成功！</div>';
            }
            
            // 处理手动截图请求
            if (isset($_POST['request_screenshot'])) {
                $device_id = $_POST['device_id'];
                $storage->saveScreenshotRequest($device_id);
                echo '<div class="success">手动截图请求已发送！</div>';
            }
            
            // 获取设备列表
            $devices = $storage->getAllDevices();
            
            // 选择设备表单
            echo '<div class="device-selector">';
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="screenshots">';
            echo '<div class="form-row">';
            echo '<div class="form-group">';
            echo '<label>选择设备</label>';
            echo '<select name="device_id">';
            echo '<option value="">-- 选择设备 --</option>';
            foreach ($devices as $device) {
                $selected = isset($_GET['device_id']) && $_GET['device_id'] == $device['device_id'] ? 'selected' : '';
                echo '<option value="' . $device['device_id'] . '" ' . $selected . '>' . $device['device_name'] . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>&nbsp;</label>';
            echo '<button type="submit" class="btn btn-primary">查看截图</button>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            
            // 显示截图设置和手动截图请求
            if (isset($_GET['device_id']) && !empty($_GET['device_id'])) {
                $device_id = $_GET['device_id'];
                $screenshot_setting = $storage->getScreenshotSetting($device_id);
                
                // 截图设置表单
                echo '<div class="screenshot-settings">';
                echo '<h3>截图设置</h3>';
                echo '<form method="post">';
                echo '<input type="hidden" name="device_id" value="' . $device_id . '">';
                echo '<div class="form-row">';
                echo '<div class="form-group">';
                echo '<label>截图间隔（秒）</label>';
                echo '<input type="number" name="interval" value="' . $screenshot_setting['interval'] . '" min="10" step="10">';
                echo '<p class="help-text">默认3600秒（1小时），可设置为10秒以上</p>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label>启用自动截图</label>';
                echo '<input type="checkbox" name="enabled" value="1" ' . ($screenshot_setting['enabled'] ? 'checked' : '') . '>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label>&nbsp;</label>';
                echo '<button type="submit" name="save_screenshot_setting" class="btn btn-primary">保存设置</button>';
                echo '</div>';
                echo '</div>';
                echo '</form>';
                
                // 手动截图请求按钮
                echo '<form method="post" class="manual-screenshot-form">';
                echo '<input type="hidden" name="device_id" value="' . $device_id . '">';
                echo '<button type="submit" name="request_screenshot" class="btn btn-secondary">手动请求截图</button>';
                echo '<p class="help-text">客户端下次连接时会立即执行截图命令</p>';
                echo '</form>';
                echo '</div>';
                
                // 显示截图列表
                $screenshots = $storage->getDeviceScreenshots($device_id);
                
                if (empty($screenshots)) {
                    echo '<p>暂无截图记录</p>';
                } else {
                    echo '<div class="screenshots-list">';
                    foreach ($screenshots as $screenshot) {
                        echo '<div class="screenshot-item">';
                        echo '<div class="screenshot-info">';
                        echo '<h4>截图 ' . $screenshot['id'] . '</h4>';
                        echo '<p>时间: ' . $screenshot['created_at'] . '</p>';
                        echo '<p>大小: ' . round($screenshot['file_size'] / 1024, 2) . ' KB</p>';
                        echo '<p>分辨率: ' . $screenshot['width'] . 'x' . $screenshot['height'] . '</p>';
                        echo '</div>';
                        echo '<div class="screenshot-preview">';
                        echo '<img src="' . $screenshot['file_path'] . '" alt="Screenshot" style="max-width: 300px; max-height: 200px;">';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
        }
        
        // 文件管理页面
        if ($page == 'files') {
            echo '<h2>文件管理</h2>';
            
            // 获取设备列表
            $devices = $storage->getAllDevices();
            
            // 选择设备表单
            echo '<div class="device-selector">';
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="files">';
            echo '<div class="form-row">';
            echo '<div class="form-group">';
            echo '<label>选择设备</label>';
            echo '<select name="device_id">';
            echo '<option value="">-- 选择设备 --</option>';
            foreach ($devices as $device) {
                $selected = isset($_GET['device_id']) && $_GET['device_id'] == $device['device_id'] ? 'selected' : '';
                echo '<option value="' . $device['device_id'] . '" ' . $selected . '>' . $device['device_name'] . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>路径</label>';
            echo '<input type="text" name="path" value="' . (isset($_GET['path']) ? $_GET['path'] : '.') . '" placeholder="请输入文件路径">';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>&nbsp;</label>';
            echo '<button type="submit" class="btn btn-primary">查看文件</button>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            
            // 显示文件列表
            if (isset($_GET['device_id']) && !empty($_GET['device_id'])) {
                $device_id = $_GET['device_id'];
                $path = isset($_GET['path']) ? $_GET['path'] : '.';
                
                echo '<div class="file-list">';
                echo '<h3>文件列表 - ' . $path . '</h3>';
                echo '<div class="file-actions">';
                echo '<button class="btn btn-secondary" onclick="refreshFileList()">刷新列表</button>';
                echo '<button class="btn btn-primary" onclick="uploadFile()">上传文件</button>';
                echo '<button class="btn btn-danger" onclick="deleteSelectedFiles()">删除选中</button>';
                echo '</div>';
                echo '<table class="files-table">';
                echo '<tr>';
                echo '<th><input type="checkbox" id="select-all"></th>';
                echo '<th>名称</th>';
                echo '<th>类型</th>';
                echo '<th>大小</th>';
                echo '<th>修改时间</th>';
                echo '<th>操作</th>';
                echo '</tr>';
                echo '<tr>';
                echo '<td><input type="checkbox"></td>';
                echo '<td><a href="?page=files&device_id=' . $device_id . '&path=' . dirname($path) . '">..</a></td>';
                echo '<td>目录</td>';
                echo '<td>-</td>';
                echo '<td>-</td>';
                echo '<td>-</td>';
                echo '</tr>';
                echo '</table>';
                echo '</div>';
            }
        }
        
        // 进程管理页面
        if ($page == 'processes') {
            echo '<h2>进程管理</h2>';
            
            // 获取设备列表
            $devices = $storage->getAllDevices();
            
            // 选择设备表单
            echo '<div class="device-selector">';
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="processes">';
            echo '<div class="form-row">';
            echo '<div class="form-group">';
            echo '<label>选择设备</label>';
            echo '<select name="device_id">';
            echo '<option value="">-- 选择设备 --</option>';
            foreach ($devices as $device) {
                $selected = isset($_GET['device_id']) && $_GET['device_id'] == $device['device_id'] ? 'selected' : '';
                echo '<option value="' . $device['device_id'] . '" ' . $selected . '>' . $device['device_name'] . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>&nbsp;</label>';
            echo '<button type="submit" class="btn btn-primary">查看进程</button>';
            echo '<button type="button" class="btn btn-secondary" onclick="refreshProcessList()">刷新列表</button>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            
            // 显示进程列表
            if (isset($_GET['device_id']) && !empty($_GET['device_id'])) {
                $device_id = $_GET['device_id'];
                $processes = $storage->getDeviceProcesses($device_id);
                
                if (empty($processes)) {
                    echo '<p>暂无进程记录</p>';
                } else {
                    echo '<div class="processes-list">';
                    echo '<table class="processes-table">';
                    echo '<tr>';
                    echo '<th><input type="checkbox" id="select-all-processes"></th>';
                    echo '<th>PID</th>';
                    echo '<th>名称</th>';
                    echo '<th>CPU%</th>';
                    echo '<th>内存%</th>';
                    echo '<th>状态</th>';
                    echo '<th>用户</th>';
                    echo '<th>命令</th>';
                    echo '<th>操作</th>';
                    echo '</tr>';
                    foreach ($processes as $process) {
                        echo '<tr>';
                        echo '<td><input type="checkbox" class="process-checkbox" value="' . $process['pid'] . '"></td>';
                        echo '<td>' . $process['pid'] . '</td>';
                        echo '<td>' . $process['name'] . '</td>';
                        echo '<td>' . $process['cpu'] . '%</td>';
                        echo '<td>' . $process['memory'] . '%</td>';
                        echo '<td>' . $process['status'] . '</td>';
                        echo '<td>' . $process['user'] . '</td>';
                        echo '<td><pre>' . $process['command'] . '</pre></td>';
                        echo '<td>';
                        echo '<button class="btn btn-small btn-danger" onclick="killProcess(' . $process['pid'] . ')">终止</button>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '<div class="process-actions">';
                    echo '<button class="btn btn-danger" onclick="killSelectedProcesses()">终止选中</button>';
                    echo '</div>';
                    echo '</div>';
                }
            }
        }
        
        // 系统设置页面
        if ($page == 'settings') {
            // 处理设置保存
            if (isset($_POST['save_settings'])) {
                $settings = $_POST['settings'];
                foreach ($settings as $key => $value) {
                    $storage->saveSetting($key, $value);
                }
                echo '<div class="success">设置保存成功！</div>';
            }
            
            echo '<h2>系统设置</h2>';
            
            // 获取当前设置
            $current_settings = $storage->getAllSettings();
            
            echo '<form method="post">';
            echo '<div class="settings-form">';
            echo '<div class="form-group">';
            echo '<label>系统名称</label>';
            echo '<input type="text" name="settings[system_name]" value="' . (isset($current_settings['system_name']) ? $current_settings['system_name'] : 'PHP远控系统') . '" placeholder="请输入系统名称">';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>默认命令执行超时时间（秒）</label>';
            echo '<input type="number" name="settings[command_timeout]" value="' . (isset($current_settings['command_timeout']) ? $current_settings['command_timeout'] : '30') . '" placeholder="请输入超时时间">';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>设备心跳间隔（秒）</label>';
            echo '<input type="number" name="settings[heartbeat_interval]" value="' . (isset($current_settings['heartbeat_interval']) ? $current_settings['heartbeat_interval'] : '60') . '" placeholder="请输入心跳间隔">';
            echo '</div>';
            echo '<button type="submit" name="save_settings" class="btn btn-primary">保存设置</button>';
            echo '</div>';
            echo '</form>';
        }
        ?>
    </div>
</body>
</html>