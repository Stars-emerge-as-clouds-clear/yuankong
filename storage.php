<?php

// 文件存储工具类
class Storage {
    private $base_path;
    
    public function __construct() {
        // 设置存储目录
        $this->base_path = dirname(__FILE__) . '/storage';
        // 创建存储目录（如果不存在）
        $this->createDir($this->base_path);
        $this->createDir($this->base_path . '/devices');
        $this->createDir($this->base_path . '/commands');
        $this->createDir($this->base_path . '/users');
        $this->createDir($this->base_path . '/groups');
        $this->createDir($this->base_path . '/templates');
        $this->createDir($this->base_path . '/logs');
        $this->createDir($this->base_path . '/settings');
        $this->createDir($this->base_path . '/screenshots');
        $this->createDir($this->base_path . '/files');
        $this->createDir($this->base_path . '/processes');
        
        // 创建默认管理员用户（如果不存在）
        $this->createDefaultAdmin();
    }
    
    // 创建默认管理员用户
    private function createDefaultAdmin() {
        $admin_file = $this->base_path . '/users/admin.json';
        if (!file_exists($admin_file)) {
            $admin_data = array(
                'username' => 'admin',
                'password' => md5('admin123'), // 默认密码：admin123
                'role' => 'admin',
                'created_at' => date('Y-m-d H:i:s')
            );
            file_put_contents($admin_file, json_encode($admin_data, JSON_UNESCAPED_UNICODE));
        }
    }
    
    // 创建目录
    private function createDir($dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // 保存设备信息
    public function saveDevice($device_id, $device_data) {
        $file_path = $this->base_path . '/devices/' . $device_id . '.json';
        $device_data['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($file_path, json_encode($device_data, JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    // 获取设备信息
    public function getDevice($device_id) {
        $file_path = $this->base_path . '/devices/' . $device_id . '.json';
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            return json_decode($content, true);
        }
        return null;
    }
    
    // 获取所有设备
    public function getAllDevices() {
        $devices = array();
        $device_files = glob($this->base_path . '/devices/*.json');
        
        foreach ($device_files as $file) {
            $content = file_get_contents($file);
            $device = json_decode($content, true);
            $devices[] = $device;
        }
        
        // 按在线状态和最后更新时间排序
        usort($devices, function($a, $b) {
            if ($a['status'] != $b['status']) {
                return $b['status'] - $a['status'];
            }
            return strtotime($b['updated_at']) - strtotime($a['updated_at']);
        });
        
        return $devices;
    }
    
    // 保存命令
    public function saveCommand($command_data) {
        $command_id = uniqid('cmd_', true);
        $command_data['id'] = $command_id;
        $command_data['created_at'] = date('Y-m-d H:i:s');
        
        // 保存到命令文件
        $file_path = $this->base_path . '/commands/' . $command_id . '.json';
        file_put_contents($file_path, json_encode($command_data, JSON_UNESCAPED_UNICODE));
        
        return $command_id;
    }
    
    // 获取设备的待执行命令
    public function getPendingCommand($device_id) {
        $command_files = glob($this->base_path . '/commands/*.json');
        $pending_commands = array();
        
        foreach ($command_files as $file) {
            $content = file_get_contents($file);
            $command = json_decode($content, true);
            
            if ($command['device_id'] == $device_id && $command['status'] == 0) {
                $pending_commands[] = $command;
            }
        }
        
        // 按创建时间排序，返回最早的命令
        usort($pending_commands, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        return count($pending_commands) > 0 ? $pending_commands[0] : null;
    }
    
    // 更新命令状态
    public function updateCommandStatus($command_id, $status, $result = '') {
        $file_path = $this->base_path . '/commands/' . $command_id . '.json';
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $command = json_decode($content, true);
            
            $command['status'] = $status;
            
            if ($status == 1) {
                $command['execute_time'] = date('Y-m-d H:i:s');
            } elseif ($status == 2) {
                $command['finish_time'] = date('Y-m-d H:i:s');
                $command['result'] = $result;
            }
            
            file_put_contents($file_path, json_encode($command, JSON_UNESCAPED_UNICODE));
            return true;
        }
        return false;
    }
    
    // 获取所有命令
    public function getAllCommands() {
        $commands = array();
        $command_files = glob($this->base_path . '/commands/*.json');
        
        foreach ($command_files as $file) {
            $content = file_get_contents($file);
            $command = json_decode($content, true);
            $commands[] = $command;
        }
        
        // 按创建时间倒序排序
        usort($commands, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $commands;
    }
    
    // 获取设备的命令历史
    public function getDeviceCommands($device_id, $limit = 5) {
        $commands = array();
        $command_files = glob($this->base_path . '/commands/*.json');
        
        foreach ($command_files as $file) {
            $content = file_get_contents($file);
            $command = json_decode($content, true);
            
            if ($command['device_id'] == $device_id) {
                $commands[] = $command;
            }
        }
        
        // 按创建时间倒序排序，返回最近的几条
        usort($commands, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($commands, 0, $limit);
    }
    
    // 用户验证
    public function verifyUser($username, $password) {
        $user_file = $this->base_path . '/users/' . $username . '.json';
        if (file_exists($user_file)) {
            $content = file_get_contents($user_file);
            $user = json_decode($content, true);
            if ($user && $user['password'] == md5($password)) {
                return $user;
            }
        }
        return false;
    }
    
    // 获取用户信息
    public function getUser($username) {
        $user_file = $this->base_path . '/users/' . $username . '.json';
        if (file_exists($user_file)) {
            $content = file_get_contents($user_file);
            return json_decode($content, true);
        }
        return null;
    }
    
    // 保存用户信息
    public function saveUser($username, $user_data) {
        $user_file = $this->base_path . '/users/' . $username . '.json';
        $user_data['updated_at'] = date('Y-m-d H:i:s');
        if (!isset($user_data['created_at'])) {
            $user_data['created_at'] = date('Y-m-d H:i:s');
        }
        file_put_contents($user_file, json_encode($user_data, JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    // 获取所有用户
    public function getAllUsers() {
        $users = array();
        $user_files = glob($this->base_path . '/users/*.json');
        
        foreach ($user_files as $file) {
            $content = file_get_contents($file);
            $user = json_decode($content, true);
            $users[] = $user;
        }
        return $users;
    }
    
    // 添加设备分组管理方法
    public function saveGroup($group_id, $group_data) {
        $group_file = $this->base_path . '/groups/' . $group_id . '.json';
        $group_data['id'] = $group_id;
        $group_data['updated_at'] = date('Y-m-d H:i:s');
        if (!isset($group_data['created_at'])) {
            $group_data['created_at'] = date('Y-m-d H:i:s');
        }
        file_put_contents($group_file, json_encode($group_data, JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    public function getGroup($group_id) {
        $group_file = $this->base_path . '/groups/' . $group_id . '.json';
        if (file_exists($group_file)) {
            $content = file_get_contents($group_file);
            return json_decode($content, true);
        }
        return null;
    }
    
    public function getAllGroups() {
        $groups = array();
        $group_files = glob($this->base_path . '/groups/*.json');
        
        foreach ($group_files as $file) {
            $content = file_get_contents($file);
            $group = json_decode($content, true);
            $groups[] = $group;
        }
        return $groups;
    }
    
    // 添加命令模板管理方法
    public function saveTemplate($template_id, $template_data) {
        $template_file = $this->base_path . '/templates/' . $template_id . '.json';
        $template_data['id'] = $template_id;
        $template_data['updated_at'] = date('Y-m-d H:i:s');
        if (!isset($template_data['created_at'])) {
            $template_data['created_at'] = date('Y-m-d H:i:s');
        }
        file_put_contents($template_file, json_encode($template_data, JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    public function getTemplate($template_id) {
        $template_file = $this->base_path . '/templates/' . $template_id . '.json';
        if (file_exists($template_file)) {
            $content = file_get_contents($template_file);
            return json_decode($content, true);
        }
        return null;
    }
    
    public function getAllTemplates() {
        $templates = array();
        $template_files = glob($this->base_path . '/templates/*.json');
        
        foreach ($template_files as $file) {
            $content = file_get_contents($file);
            $template = json_decode($content, true);
            $templates[] = $template;
        }
        return $templates;
    }
    
    // 添加系统日志方法
    public function addLog($log_data) {
        $log_id = uniqid('log_', true);
        $log_data['id'] = $log_id;
        $log_data['created_at'] = date('Y-m-d H:i:s');
        $log_file = $this->base_path . '/logs/' . $log_id . '.json';
        file_put_contents($log_file, json_encode($log_data, JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    public function getAllLogs($limit = 50) {
        $logs = array();
        $log_files = glob($this->base_path . '/logs/*.json');
        
        foreach ($log_files as $file) {
            $content = file_get_contents($file);
            $log = json_decode($content, true);
            $logs[] = $log;
        }
        
        // 按创建时间倒序排序
        usort($logs, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($logs, 0, $limit);
    }
    
    // 添加系统设置方法
    public function saveSetting($key, $value) {
        $setting_file = $this->base_path . '/settings/settings.json';
        $settings = array();
        
        if (file_exists($setting_file)) {
            $content = file_get_contents($setting_file);
            $settings = json_decode($content, true);
        }
        
        $settings[$key] = $value;
        file_put_contents($setting_file, json_encode($settings, JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    public function getSetting($key, $default = null) {
        $setting_file = $this->base_path . '/settings/settings.json';
        
        if (file_exists($setting_file)) {
            $content = file_get_contents($setting_file);
            $settings = json_decode($content, true);
            if (isset($settings[$key])) {
                return $settings[$key];
            }
        }
        
        return $default;
    }
    
    public function getAllSettings() {
        $setting_file = $this->base_path . '/settings/settings.json';
        
        if (file_exists($setting_file)) {
            $content = file_get_contents($setting_file);
            return json_decode($content, true);
        }
        
        return array();
    }
    
    // 屏幕截图管理方法
    public function saveScreenshot($device_id, $screenshot_data) {
        $screenshot_id = 'screenshot_' . uniqid();
        $screenshot_file = $this->base_path . '/screenshots/' . $screenshot_id . '.json';
        
        $screenshot_info = array(
            'id' => $screenshot_id,
            'device_id' => $device_id,
            'created_at' => date('Y-m-d H:i:s'),
            'file_path' => $screenshot_data['file_path'],
            'file_size' => $screenshot_data['file_size'],
            'width' => $screenshot_data['width'],
            'height' => $screenshot_data['height']
        );
        
        file_put_contents($screenshot_file, json_encode($screenshot_info, JSON_UNESCAPED_UNICODE));
        return $screenshot_id;
    }
    
    public function getScreenshot($screenshot_id) {
        $screenshot_file = $this->base_path . '/screenshots/' . $screenshot_id . '.json';
        if (file_exists($screenshot_file)) {
            $content = file_get_contents($screenshot_file);
            return json_decode($content, true);
        }
        return null;
    }
    
    public function getDeviceScreenshots($device_id, $limit = 20) {
        $screenshots = array();
        $screenshot_files = glob($this->base_path . '/screenshots/*.json');
        
        foreach ($screenshot_files as $file) {
            $content = file_get_contents($file);
            $screenshot = json_decode($content, true);
            if ($screenshot['device_id'] == $device_id) {
                $screenshots[] = $screenshot;
            }
        }
        
        // 按创建时间倒序排序
        usort($screenshots, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($screenshots, 0, $limit);
    }
    
    // 文件管理方法
    public function saveFileInfo($device_id, $file_info) {
        $file_id = 'file_' . uniqid();
        $file_info_file = $this->base_path . '/files/' . $file_id . '.json';
        
        $file_data = array(
            'id' => $file_id,
            'device_id' => $device_id,
            'created_at' => date('Y-m-d H:i:s'),
            'file_path' => $file_info['file_path'],
            'file_name' => $file_info['file_name'],
            'file_size' => $file_info['file_size'],
            'file_type' => $file_info['file_type'],
            'md5' => $file_info['md5']
        );
        
        file_put_contents($file_info_file, json_encode($file_data, JSON_UNESCAPED_UNICODE));
        return $file_id;
    }
    
    public function getFileInfo($file_id) {
        $file_info_file = $this->base_path . '/files/' . $file_id . '.json';
        if (file_exists($file_info_file)) {
            $content = file_get_contents($file_info_file);
            return json_decode($content, true);
        }
        return null;
    }
    
    // 进程管理方法
    public function saveProcessInfo($device_id, $process_info) {
        $process_id = 'process_' . uniqid();
        $process_file = $this->base_path . '/processes/' . $process_id . '.json';
        
        $process_data = array(
            'id' => $process_id,
            'device_id' => $device_id,
            'created_at' => date('Y-m-d H:i:s'),
            'pid' => $process_info['pid'],
            'name' => $process_info['name'],
            'cpu' => $process_info['cpu'],
            'memory' => $process_info['memory'],
            'status' => $process_info['status'],
            'user' => $process_info['user'],
            'command' => $process_info['command']
        );
        
        file_put_contents($process_file, json_encode($process_data, JSON_UNESCAPED_UNICODE));
        return $process_id;
    }
    
    public function getDeviceProcesses($device_id, $limit = 50) {
        $processes = array();
        $process_files = glob($this->base_path . '/processes/*.json');
        
        foreach ($process_files as $file) {
            $content = file_get_contents($file);
            $process = json_decode($content, true);
            if ($process['device_id'] == $device_id) {
                $processes[] = $process;
            }
        }
        
        // 按创建时间倒序排序
        usort($processes, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($processes, 0, $limit);
    }
    
    // 保存设备系统信息
    public function saveSystemInfo($device_id, $system_info) {
        $device = $this->getDevice($device_id);
        if ($device) {
            // 保存客户端传递的完整系统信息，确保不为null
            $device['system_info'] = $system_info !== null ? $system_info : array();
            $device['system_info_updated_at'] = date('Y-m-d H:i:s');
            
            // 将系统信息内容保存到设备info字段，用于设备信息显示
            if (is_array($system_info)) {
                // 将数组格式的系统信息转换为字符串
                $info_str = '';
                foreach ($system_info as $key => $value) {
                    if (!empty($info_str)) {
                        $info_str .= ', ';
                    }
                    $info_str .= $key . ': ' . (is_array($value) ? json_encode($value) : $value);
                }
                $device['info'] = !empty($info_str) ? $info_str : '系统信息上传成功';
            } else {
                // 直接保存字符串格式的系统信息
                $device['info'] = !empty(trim($system_info)) ? $system_info : '系统信息上传成功';
            }
            
            // 更新设备信息
            $this->saveDevice($device_id, $device);
            return true;
        }
        return false;
    }
    
    // 保存截图设置
    public function saveScreenshotSetting($device_id, $setting) {
        $device = $this->getDevice($device_id);
        if ($device) {
            $device['screenshot_setting'] = $setting;
            $this->saveDevice($device_id, $device);
            return true;
        }
        return false;
    }
    
    // 获取截图设置
    public function getScreenshotSetting($device_id) {
        $device = $this->getDevice($device_id);
        if ($device && isset($device['screenshot_setting'])) {
            return $device['screenshot_setting'];
        }
        // 默认设置：1小时截图一次
        return array(
            'interval' => 3600, // 默认1小时
            'enabled' => true
        );
    }
    
    // 保存手动截图请求
    public function saveScreenshotRequest($device_id) {
        $request_id = 'screenshot_req_' . uniqid();
        $request_file = $this->base_path . '/screenshots/requests/' . $request_id . '.json';
        
        // 确保请求目录存在
        $this->createDir($this->base_path . '/screenshots/requests');
        
        $request_data = array(
            'id' => $request_id,
            'device_id' => $device_id,
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 0 // 0: 待处理, 1: 已处理
        );
        
        file_put_contents($request_file, json_encode($request_data, JSON_UNESCAPED_UNICODE));
        return $request_id;
    }
    
    // 获取未处理的截图请求
    public function getPendingScreenshotRequest($device_id) {
        $request_dir = $this->base_path . '/screenshots/requests';
        if (!is_dir($request_dir)) {
            return null;
        }
        
        $request_files = glob($request_dir . '/*.json');
        foreach ($request_files as $file) {
            $request = json_decode(file_get_contents($file), true);
            if ($request['device_id'] == $device_id && $request['status'] == 0) {
                // 更新为已处理
                $request['status'] = 1;
                $request['processed_at'] = date('Y-m-d H:i:s');
                file_put_contents($file, json_encode($request, JSON_UNESCAPED_UNICODE));
                return $request;
            }
        }
        return null;
    }
}

// 创建全局存储实例
$storage = new Storage();
?>