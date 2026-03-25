<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit;
}

require_once 'config.php';

// Get user data
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Get role-specific data
$dashboard_data = [];
switch ($user_role) {
    case 'jobseeker':
        $stmt = mysqli_prepare($conn, "SELECT * FROM jobseekers WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $dashboard_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        break;
        
    case 'employer':
        $stmt = mysqli_prepare($conn, "SELECT * FROM employers WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $dashboard_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        break;
        
    case 'officer':
        $stmt = mysqli_prepare($conn, "SELECT * FROM peso_officers WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $dashboard_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        break;
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SkillBridge AI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .user-details h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 2px;
        }

        .user-details p {
            font-size: 14px;
            color: #666;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }

        .card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .card-icon {
            font-size: 30px;
            margin-bottom: 10px;
        }

        .card-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .card-label {
            color: #666;
            font-size: 14px;
        }

        .profile-card {
            grid-column: span 2;
        }

        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .actions-card {
            grid-column: span 2;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.3s;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .testing-tools {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .testing-tools h3 {
            color: #333;
            margin-bottom: 20px;
        }

        .test-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .test-section h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .test-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .test-btn:hover {
            background: #218838;
        }

        .test-result {
            margin-top: 10px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-card, .actions-card {
                grid-column: span 1;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <h1>SkillBridge AI Dashboard</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($user_name); ?></h3>
                    <p><?php echo ucfirst($user_role); ?></p>
                </div>
                <form method="post" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Profile Card -->
            <div class="card profile-card">
                <h3><span class="card-icon">👤</span> Profile Information</h3>
                <div class="profile-info">
                    <?php if ($dashboard_data): ?>
                        <?php foreach ($dashboard_data as $key => $value): ?>
                            <?php if ($key !== 'password' && $key !== 'id' && $value): ?>
                                <div class="info-item">
                                    <div class="info-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></div>
                                    <div class="info-value"><?php echo htmlspecialchars($value); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="info-item">
                            <div class="info-value">No profile data available</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="card">
                <h3><span class="card-icon">📊</span> Account Status</h3>
                <div class="card-value">Active</div>
                <div class="card-label">Since registration</div>
            </div>

            <div class="card">
                <h3><span class="card-icon">🔔</span> Notifications</h3>
                <div class="card-value">0</div>
                <div class="card-label">New messages</div>
            </div>

            <!-- Actions Card -->
            <div class="card actions-card">
                <h3><span class="card-icon">⚡</span> Quick Actions</h3>
                <div class="action-buttons">
                    <?php if ($user_role === 'jobseeker'): ?>
                        <button class="action-btn">📝 Update Profile</button>
                        <button class="action-btn">💼 Browse Jobs</button>
                        <button class="action-btn">📋 My Applications</button>
                        <button class="action-btn">⚙️ Account Settings</button>
                    <?php elseif ($user_role === 'employer'): ?>
                        <button class="action-btn">📝 Post Job</button>
                        <button class="action-btn">👥 Manage Applications</button>
                        <button class="action-btn">📊 View Analytics</button>
                        <button class="action-btn">⚙️ Company Settings</button>
                    <?php elseif ($user_role === 'officer'): ?>
                        <button class="action-btn">👥 Manage Users</button>
                        <button class="action-btn">📊 Reports</button>
                        <button class="action-btn">🔍 Verify Documents</button>
                        <button class="action-btn">⚙️ System Settings</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Testing Tools -->
        <div class="testing-tools">
            <h3>🧪 Testing Tools</h3>
            
            <div class="test-section">
                <h4>Session Information</h4>
                <button class="test-btn" onclick="showSessionInfo()">Show Session Data</button>
                <button class="test-btn" onclick="testSessionTimeout()">Test Session Timeout</button>
                <div id="session-result" class="test-result" style="display: none;"></div>
            </div>

            <div class="test-section">
                <h4>Database Connection</h4>
                <button class="test-btn" onclick="testDatabase()">Test DB Connection</button>
                <button class="test-btn" onclick="testUserQuery()">Test User Query</button>
                <div id="db-result" class="test-result" style="display: none;"></div>
            </div>

            <div class="test-section">
                <h4>Registration Flow</h4>
                <button class="test-btn" onclick="window.open('test_register_form.php', '_blank')">Test Registration</button>
                <button class="test-btn" onclick="window.open('register.html', '_blank')">Live Registration</button>
                <button class="test-btn" onclick="createTestUser()">Create Test User</button>
                <div id="reg-result" class="test-result" style="display: none;"></div>
            </div>

            <div class="test-section">
                <h4>System Info</h4>
                <button class="test-btn" onclick="showSystemInfo()">Show PHP Info</button>
                <button class="test-btn" onclick="checkPermissions()">Check Permissions</button>
                <div id="sys-result" class="test-result" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script>
        function showSessionInfo() {
            fetch('test_session.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('session-result').style.display = 'block';
                    document.getElementById('session-result').textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    document.getElementById('session-result').style.display = 'block';
                    document.getElementById('session-result').textContent = 'Error: ' + error.message;
                });
        }

        function testSessionTimeout() {
            document.getElementById('session-result').style.display = 'block';
            document.getElementById('session-result').textContent = 'Session timeout test - session will expire in 24 hours or when browser closes';
        }

        function testDatabase() {
            fetch('test_db_connection.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('db-result').style.display = 'block';
                    document.getElementById('db-result').textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    document.getElementById('db-result').style.display = 'block';
                    document.getElementById('db-result').textContent = 'Error: ' + error.message;
                });
        }

        function testUserQuery() {
            fetch('test_user_query.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('db-result').style.display = 'block';
                    document.getElementById('db-result').textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    document.getElementById('db-result').style.display = 'block';
                    document.getElementById('db-result').textContent = 'Error: ' + error.message;
                });
        }

        function createTestUser() {
            fetch('create_test_user.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('reg-result').style.display = 'block';
                    document.getElementById('reg-result').textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    document.getElementById('reg-result').style.display = 'block';
                    document.getElementById('reg-result').textContent = 'Error: ' + error.message;
                });
        }

        function showSystemInfo() {
            document.getElementById('sys-result').style.display = 'block';
            document.getElementById('sys-result').innerHTML = `
                PHP Version: <?php echo PHP_VERSION; ?><br>
                Server Time: <?php echo date('Y-m-d H:i:s'); ?><br>
                User Agent: <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?><br>
                Session ID: <?php echo session_id(); ?><br>
                Max Upload Size: <?php echo ini_get('upload_max_filesize'); ?><br>
                Post Max Size: <?php echo ini_get('post_max_size'); ?>
            `;
        }

        function checkPermissions() {
            fetch('check_permissions.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('sys-result').style.display = 'block';
                    document.getElementById('sys-result').textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    document.getElementById('sys-result').style.display = 'block';
                    document.getElementById('sys-result').textContent = 'Error: ' + error.message;
                });
        }
    </script>
</body>
</html>
