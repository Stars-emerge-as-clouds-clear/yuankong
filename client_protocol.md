# PHP远控协议说明

## 工作原理

本远控系统采用客户端主动轮询的方式工作：
1. 客户端定期向服务端发送请求
2. 服务端根据请求类型返回相应数据
3. 客户端执行命令并返回结果

## 通信协议

### 基础URL

所有请求都发送到服务端的 server.php 文件，通过 action 参数指定请求类型。

### 请求类型

#### 1. 设备上线

**请求URL**：
http://your-server/server.php?action=online&device_id=DEVICE_ID&device_name=DEVICE_NAME

**参数说明**：
- device_id：设备唯一标识（必填）
- device_name：设备名称（可选，默认：Unknown）

**返回结果**：
- Device registered：新设备注册成功
- Device updated：设备信息更新成功

#### 2. 获取命令

**请求URL**：
http://your-server/server.php?action=get_command&device_id=DEVICE_ID

**参数说明**：
- device_id：设备唯一标识（必填）

**返回结果**：
- JSON格式，包含命令ID和命令内容

#### 3. 提交命令结果

**请求类型**：POST

**请求URL**：
http://your-server/server.php?action=submit_result

**POST参数**：
- device_id：设备唯一标识（必填）
- command_id：命令ID（必填）
- result：命令执行结果（必填）

**返回结果**：
- Result submitted：结果提交成功

#### 4. 心跳请求

**请求URL**：
http://your-server/server.php?action=heartbeat&device_id=DEVICE_ID

**参数说明**：
- device_id：设备唯一标识（必填）

**返回结果**：
- Heartbeat updated：心跳更新成功

## 新增功能协议

### 1. 屏幕监控

屏幕监控功能支持定时自动截图、手动请求截图和自定义截图间隔，设备上线后自动执行截图任务。

#### 1.1 自动截图机制

- **默认设置**：设备上线后每1小时自动截图一次
- **触发条件**：设备在线且到达截图时间间隔
- **优先级**：手动截图请求优先级高于自动截图

#### 1.2 手动截图请求

服务端支持接收手动截图请求，设备下次获取命令时会执行截图操作。

#### 1.3 截图设置管理

支持自定义截图间隔时间（60秒-86400秒），可启用或禁用自动截图功能。

#### 1.4 获取屏幕截图命令

**请求URL**：
http://your-server/server.php?action=get_screenshot_command&device_id=DEVICE_ID

**参数说明**：
- device_id：设备唯一标识（必填）

**返回结果**：
- 当需要截图时：JSON格式，包含命令ID和命令类型
  ```json
  {
    "status": "success",
    "command_id": "screenshot_1234567890",
    "command_type": "screenshot"
  }
  ```
- 当不需要截图时：
  ```json
  {
    "status": "no_screenshot",
    "message": "No screenshot required"
  }
  ```

**触发场景**：
- 到达定时截图时间
- 收到手动截图请求

**说明**：
- 此API专门用于获取截图命令，与普通命令（cmd）完全分离
- 客户端应定期调用此API检查是否需要执行截图操作
- 手动截图请求优先级高于自动截图

#### 1.5 提交屏幕截图

**请求类型**：POST

**请求URL**：
http://your-server/server.php?action=screenshot

**POST参数**：
- device_id：设备唯一标识（必填）
- screenshot_data：Base64编码的截图数据（必填）
- width：截图宽度（可选）
- height：截图高度（可选）

**返回结果**：
- JSON格式，包含截图ID

#### 1.6 截图状态管理

- 服务端记录设备最后截图时间
- 自动计算下次截图时间
- 支持查看截图历史记录
- 支持批量管理截图设置

### 2. 文件管理

文件管理功能允许客户端获取、下载、上传和删除文件。服务端采用命令分发模式，返回命令让客户端执行，客户端执行后将结果返回给服务端。

#### 2.1 获取文件列表

**请求URL**：
http://your-server/server.php?action=file_list&device_id=DEVICE_ID&path=PATH

**参数说明**：
- device_id：设备唯一标识（必填）
- path：目录路径（可选，默认：.）

**返回结果**：
```json
{
  "status": "command",
  "command": "dir C:\\path\\to\\directory",
  "command_type": "file_list",
  "path": "C:\\path\\to\\directory"
}
```

**客户端处理流程**：
1. 收到命令后，执行`dir C:\path\to\directory`命令
2. 将命令执行结果通过命令结果提交接口返回给服务端
3. 服务端保存文件列表信息

#### 2.2 下载文件

**请求URL**：
http://your-server/server.php?action=file_download&device_id=DEVICE_ID&file_path=FILE_PATH

**参数说明**：
- device_id：设备唯一标识（必填）
- file_path：文件路径（必填）

**返回结果**：
```json
{
  "status": "command",
  "command": "type C:\\path\\to\\file.txt",
  "command_type": "file_download",
  "file_path": "C:\\path\\to\\file.txt"
}
```

**客户端处理流程**：
1. 收到命令后，执行`type C:\path\to\file.txt`命令
2. 将命令执行结果（文件内容）通过命令结果提交接口返回给服务端
3. 服务端保存文件内容

#### 2.3 上传文件

**请求类型**：POST

**请求URL**：
http://your-server/server.php?action=file_upload

**POST参数**：
- device_id：设备唯一标识（必填）
- file_path：目标文件路径（必填）
- file_content：Base64编码的文件内容（必填）

**返回结果**：
```json
{
  "status": "success",
  "file_id": "file_1234567890"
}
```

**服务端处理**：
1. 接收Base64编码的文件内容
2. 解码并保存到服务端uploads目录
3. 保存文件信息（路径、名称、大小、类型、MD5）
4. 返回文件ID

#### 2.4 删除文件

**请求URL**：
http://your-server/server.php?action=file_delete&device_id=DEVICE_ID&file_path=FILE_PATH

**参数说明**：
- device_id：设备唯一标识（必填）
- file_path：文件路径（必填）

**返回结果**：
```json
{
  "status": "command",
  "command": "del /f /q C:\\path\\to\\file.txt",
  "command_type": "file_delete",
  "file_path": "C:\\path\\to\\file.txt"
}
```

**客户端处理流程**：
1. 收到命令后，执行`del /f /q C:\path\to\file.txt`命令
2. 将命令执行结果通过命令结果提交接口返回给服务端
3. 服务端更新文件状态

#### 2.5 文件管理工作流程

1. **客户端请求获取文件列表**：客户端调用`file_list`接口获取指定目录的文件列表
2. **服务端返回命令**：服务端返回`dir`命令让客户端执行
3. **客户端执行命令并返回结果**：客户端执行命令后，将结果返回给服务端
4. **客户端请求下载文件**：客户端调用`file_download`接口下载指定文件
5. **服务端返回命令**：服务端返回`type`命令让客户端执行
6. **客户端执行命令并返回结果**：客户端执行命令后，将文件内容返回给服务端
7. **客户端上传文件**：客户端调用`file_upload`接口上传文件
8. **服务端保存文件**：服务端保存文件并返回文件ID
9. **客户端请求删除文件**：客户端调用`file_delete`接口删除指定文件
10. **服务端返回命令**：服务端返回`del`命令让客户端执行
11. **客户端执行命令并返回结果**：客户端执行命令后，将结果返回给服务端

#### 2.6 文件信息保存

服务端保存的文件信息包括：
- 文件路径
- 文件名
- 文件大小（字节）
- 文件类型（MIME类型）
- MD5哈希值
- 设备ID
- 上传时间

#### 2.7 注意事项

1. 服务端仅保存文件信息，不保存文件列表的详细内容
2. 文件下载功能依赖客户端执行`cat`命令，对于大文件可能存在性能问题
3. 文件上传时，服务端会将文件保存到uploads目录，建议定期清理
4. 删除文件时，服务端仅返回命令，实际删除操作由客户端执行
5. 客户端需要有足够的权限执行文件操作命令

### 3. 进程管理

#### 3.1 获取进程列表

**请求URL**：
http://your-server/server.php?action=process_list&device_id=DEVICE_ID

**参数说明**：
- device_id：设备唯一标识（必填）

**返回结果**：
```json
{
  "status": "command",
  "command": "tasklist",
  "command_type": "process_list"
}
```

**客户端处理流程**：
1. 收到命令后，执行`tasklist`命令获取Windows进程列表
2. 将命令执行结果通过命令结果提交接口返回给服务端
3. 服务端保存进程列表信息

#### 3.2 终止进程

**请求URL**：
http://your-server/server.php?action=process_kill&device_id=DEVICE_ID&pid=PID

**参数说明**：
- device_id：设备唯一标识（必填）
- pid：进程ID（必填）

**返回结果**：
```json
{
  "status": "command",
  "command": "taskkill /f /pid 1234",
  "command_type": "process_kill",
  "pid": 1234
}
```

**客户端处理流程**：
1. 收到命令后，执行`taskkill /f /pid 1234`命令终止指定进程
2. 将命令执行结果通过命令结果提交接口返回给服务端
3. 服务端更新进程状态

#### 3.3 进程管理工作流程

1. **客户端请求获取进程列表**：客户端调用`process_list`接口获取进程列表
2. **服务端返回命令**：服务端返回`tasklist`命令让客户端执行
3. **客户端执行命令并返回结果**：客户端执行命令后，将进程列表返回给服务端
4. **客户端请求终止进程**：客户端调用`process_kill`接口终止指定进程
5. **服务端返回命令**：服务端返回`taskkill`命令让客户端执行
6. **客户端执行命令并返回结果**：客户端执行命令后，将结果返回给服务端

#### 3.4 注意事项

1. `tasklist`命令返回Windows系统的完整进程列表
2. `taskkill /f /pid PID`命令强制终止指定PID的进程
3. 客户端需要有足够的权限执行进程管理命令
4. 终止进程时请谨慎操作，避免误杀系统关键进程

### 4. 系统信息

#### 4.1 获取系统信息

**请求URL**：
http://your-server/server.php?action=system_info&device_id=DEVICE_ID

**参数说明**：
- device_id：设备唯一标识（必填）

**返回结果**：
- JSON格式，包含命令和命令类型

#### 4.2 提交系统信息

**请求类型**：POST

**请求URL**：
http://your-server/server.php?action=submit_system_info

**POST参数**：
- device_id：设备唯一标识（必填）
- system_info：JSON格式的系统信息（必填）

**返回结果**：
- JSON格式，包含状态信息

## 安全建议

1. 使用HTTPS：在生产环境中，建议使用HTTPS加密通信
2. 添加认证：可以在请求中添加额外的认证参数
3. 限制IP访问：在服务端配置IP白名单
4. 命令过滤：在客户端添加命令过滤，防止执行危险命令
5. 加密通信：可以对命令和结果进行加密传输

## 扩展建议

1. 添加文件传输功能（已实现）
2. 实现屏幕监控（已实现）
3. 添加进程管理（已实现）
4. 实现远程桌面
5. 添加文件浏览器
6. 添加系统监控
7. 添加服务管理
8. 添加注册表管理（Windows）
9. 添加计划任务
10. 添加键盘记录

## 注意事项

1. 确保客户端有足够的权限执行命令
2. 定期清理服务端存储的命令和结果
3. 合理设置轮询间隔，避免占用过多网络资源
4. 考虑添加重试机制，处理网络不稳定情况
5. 屏幕截图可能会占用大量磁盘空间，建议定期清理
6. 文件传输时注意网络带宽和文件大小限制
7. 进程管理需要谨慎操作，避免误杀系统关键进程
