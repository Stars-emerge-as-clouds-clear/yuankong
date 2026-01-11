<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - PHP 远控后台</title>
    <style>
        /* 登录页面样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        .login-container {
            background-color: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-container h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .btn {
            background-color: #3498db;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>PHP 远控后台登录</h1>
        
        <?php
        // 引入文件存储类
        require_once 'storage.php';
        
        // 处理登录请求
        $error = '';
        $success = '';
        
        if (isset($_POST['login'])) {
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            if (empty($username) || empty($password)) {
                $error = '用户名和密码不能为空';
            } else {
                $user = $storage->verifyUser($username, $password);
                if ($user) {
                    // 登录成功，设置会话
                    session_start();
                    $_SESSION['user'] = $user;
                    $_SESSION['logged_in'] = true;
                    
                    // 添加登录日志
                    $storage->addLog(array(
                        'username' => $username,
                        'action' => 'login',
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'message' => '用户登录成功'
                    ));
                    
                    // 重定向到后台首页
                    header('Location: index.php');
                    exit;
                } else {
                    $error = '用户名或密码错误';
                    
                    // 添加登录失败日志
                    $storage->addLog(array(
                        'username' => $username,
                        'action' => 'login_failed',
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'message' => '用户登录失败'
                    ));
                }
            }
        }
        
        if ($error) {
            echo '<div class="error">' . $error . '</div>';
        }
        
        if ($success) {
            echo '<div class="success">' . $success . '</div>';
        }
        ?>
        
        <form class="login-form" method="post">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" placeholder="请输入用户名" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
            </div>
            
            <button type="submit" name="login" class="btn">登录</button>
        </form>
        
        <div class="footer">
            <p>默认用户名：admin，默认密码：admin123</p>
        </div>
    </div>
</body>
</html>