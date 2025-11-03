<?php
session_start();

// Simple file-based storage for device tracking and user authentication
// In a real application, you would use a database
define('DEVICE_DB_FILE', 'devices.json');
define('USERS_DB_FILE', 'users.json');

// Ensure the database files exist
if (!file_exists(DEVICE_DB_FILE)) {
    file_put_contents(DEVICE_DB_FILE, json_encode([]));
}

if (!file_exists(USERS_DB_FILE)) {
    // Create a default user for testing: username "demo", password "password"
    $defaultUsers = [
        'demo' => [
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'created' => date('Y-m-d H:i:s')
        ]
    ];
    file_put_contents(USERS_DB_FILE, json_encode($defaultUsers));
}

function loadUsers() {
    $json = file_get_contents(USERS_DB_FILE);
    return json_decode($json, true) ?: [];
}

function saveUsers($users) {
    file_put_contents(USERS_DB_FILE, json_encode($users));
}

function authenticateUser($username, $password, $deviceId, $userAgent, $platform) {
    $users = loadUsers();
    
    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        // Check if user already has an active device
        $devices = loadDevices();
        
        if (isset($devices[$username]) && count($devices[$username]) > 0) {
            // Check if any device is still active (accessed in the last 30 minutes)
            $isActive = false;
            $currentTime = time();
            
            foreach ($devices[$username] as $deviceId => $device) {
                $lastAccessTime = strtotime($device['lastAccess']);
                
                // Consider device active if accessed in the last 30 minutes
                if (($currentTime - $lastAccessTime) < (30 * 60)) {
                    $isActive = true;
                    break;
                }
            }
            
            if ($isActive) {
                return [
                    'success' => false,
                    'message' => 'Access denied: User is already logged in on another device. Please logout from the other device first.'
                ];
            }
        }
        
        // Store user in session
        $_SESSION['username'] = $username;
        
        // Automatically register the device
        // First device for this user
        $devices[$username][$deviceId] = [
            'deviceId' => $deviceId,
            'firstAccess' => date('Y-m-d H:i:s'),
            'lastAccess' => date('Y-m-d H:i:s'),
            'userAgent' => $userAgent,
            'platform' => $platform
        ];
        saveDevices($devices);
        
        return [
            'success' => true,
            'message' => 'Login successful. Device automatically registered.'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Invalid username or password'
    ];
}

function logoutUser() {
    $username = getCurrentUser();
    
    if ($username) {
        // Remove the user's devices from tracking
        $devices = loadDevices();
        unset($devices[$username]);
        saveDevices($devices);
    }
    
    unset($_SESSION['username']);
    return [
        'success' => true,
        'message' => 'Logged out successfully'
    ];
}

function isLoggedIn() {
    return isset($_SESSION['username']) && !empty($_SESSION['username']);
}

function getCurrentUser() {
    return $_SESSION['username'] ?? null;
}

function loadDevices() {
    $json = file_get_contents(DEVICE_DB_FILE);
    return json_decode($json, true) ?: [];
}

function saveDevices($devices) {
    file_put_contents(DEVICE_DB_FILE, json_encode($devices));
}

function registerDevice($deviceId, $userAgent, $platform) {
    $devices = loadDevices();
    
    // Check if user is logged in
    $username = getCurrentUser();
    if (!$username) {
        return [
            'success' => false,
            'message' => 'You must be logged in to register a device'
        ];
    }
    
    // If there are already devices registered for this user
    if (isset($devices[$username]) && count($devices[$username]) >= 1) {
        // Check if the device is already registered
        $existingDevice = false;
        foreach ($devices[$username] as $id => $device) {
            if ($id === $deviceId) {
                $existingDevice = true;
                // Update the last access time
                $devices[$username][$id]['lastAccess'] = date('Y-m-d H:i:s');
                break;
            }
        }
        
        // If it's a new device and we already have one, deny access
        if (!$existingDevice) {
            // Check if the existing device is still active (accessed in the last 10 minutes)
            $isActive = false;
            $currentTime = time();
            
            foreach ($devices[$username] as $id => $device) {
                $lastAccessTime = strtotime($device['lastAccess']);
                
                // Consider device active if accessed in the last 10 minutes
                if (($currentTime - $lastAccessTime) < (10 * 60)) {
                    $isActive = true;
                    break;
                }
            }
            
            if ($isActive) {
                return [
                    'success' => false,
                    'message' => 'Access denied: Another device is already logged in for this user. Only one device allowed at a time.'
                ];
            } else {
                // The previous device is no longer active, so allow this new device
                $devices[$username] = []; // Clear old devices since they're inactive
                $devices[$username][$deviceId] = [
                    'deviceId' => $deviceId,
                    'firstAccess' => date('Y-m-d H:i:s'),
                    'lastAccess' => date('Y-m-d H:i:s'),
                    'userAgent' => $userAgent,
                    'platform' => $platform
                ];
            }
        }
    } else {
        // First device for this user
        $devices[$username][$deviceId] = [
            'deviceId' => $deviceId,
            'firstAccess' => date('Y-m-d H:i:s'),
            'lastAccess' => date('Y-m-d H:i:s'),
            'userAgent' => $userAgent,
            'platform' => $platform
        ];
    }
    
    saveDevices($devices);
    return [
        'success' => true,
        'message' => 'Device registered successfully'
    ];
}

function checkDeviceStatus($deviceId) {
    $devices = loadDevices();
    $username = getCurrentUser();
    
    if (!$username) {
        return [
            'success' => false,
            'message' => 'You must be logged in to check device status'
        ];
    }
    
    // Check if this user has this specific device registered
    if (isset($devices[$username]) && isset($devices[$username][$deviceId])) {
        // Update the last access time
        $devices[$username][$deviceId]['lastAccess'] = date('Y-m-d H:i:s');
        saveDevices($devices);
        
        return [
            'success' => true,
            'message' => 'Access granted'
        ];
    } else {
        // Check if this user has any devices registered
        if (isset($devices[$username]) && count($devices[$username]) > 0) {
            // Check if any of the registered devices are still active
            $isActive = false;
            $currentTime = time();
            
            foreach ($devices[$username] as $id => $device) {
                $lastAccessTime = strtotime($device['lastAccess']);
                
                // Consider device active if accessed in the last 10 minutes
                if (($currentTime - $lastAccessTime) < (10 * 60)) {
                    $isActive = true;
                    break;
                }
            }
            
            if ($isActive) {
                return [
                    'success' => false,
                    'message' => 'Access denied: Another device is already logged in for this user. Only one device allowed at a time.'
                ];
            } else {
                // No active devices - allow access but require device registration
                return [
                    'success' => false,
                    'message' => 'No active device registered. Please register your device first.'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'No device registered. Please register your device first.'
            ];
        }
    }
}

function listDevices() {
    $devices = loadDevices();
    $username = getCurrentUser();
    
    if (!$username) {
        return [
            'success' => false,
            'message' => 'You must be logged in to view devices'
        ];
    }
    
    if (isset($devices[$username])) {
        $deviceList = array_values($devices[$username]);
        return [
            'success' => true,
            'devices' => $deviceList
        ];
    } else {
        return [
            'success' => false,
            'message' => 'No devices registered for this user'
        ];
    }
}

function clearOldSessions() {
    // Remove user devices that haven't been active in more than 24 hours
    $devices = loadDevices();
    $currentTime = time();
    $removedCount = 0;
    
    foreach ($devices as $username => $deviceList) {
        $latestAccess = 0;
        foreach ($deviceList as $device) {
            $deviceTime = strtotime($device['lastAccess']);
            if ($deviceTime > $latestAccess) {
                $latestAccess = $deviceTime;
            }
        }
        
        // If no activity in 24 hours, remove the user's devices
        if (($currentTime - $latestAccess) > (24 * 60 * 60)) {
            unset($devices[$username]);
            $removedCount++;
        }
    }
    
    saveDevices($devices);
    return $removedCount;
}

function clearAllDevices() {
    // Clear all device records
    $devices = [];
    saveDevices($devices);
    return true;
}

function clearInactiveDevices($hours = 2) {
    // Remove devices that haven't been active in specified hours
    $devices = loadDevices();
    $currentTime = time();
    $removedCount = 0;
    
    foreach ($devices as $username => $deviceList) {
        $activeDevices = [];
        foreach ($deviceList as $deviceId => $device) {
            $deviceTime = strtotime($device['lastAccess']);
            // If device was active within the specified hours, keep it
            if (($currentTime - $deviceTime) <= ($hours * 60 * 60)) {
                $activeDevices[$deviceId] = $device;
            } else {
                $removedCount++;
            }
        }
        
        // If no active devices left for this user, remove the user
        if (empty($activeDevices)) {
            unset($devices[$username]);
        } else {
            $devices[$username] = $activeDevices;
        }
    }
    
    saveDevices($devices);
    return $removedCount;
}

// Handle API requests if this is an AJAX call
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    $response = [];
    
    switch ($action) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $deviceId = $_POST['deviceId'] ?? '';
            $userAgent = $_POST['userAgent'] ?? '';
            $platform = $_POST['platform'] ?? '';
            
            if (empty($username) || empty($password)) {
                $response = [
                    'success' => false,
                    'message' => 'Username and password are required'
                ];
            } else {
                $response = authenticateUser($username, $password, $deviceId, $userAgent, $platform);
            }
            break;
            
        case 'logout':
            $response = logoutUser();
            break;
            
        case 'register':
            $deviceId = $_POST['deviceId'] ?? '';
            $userAgent = $_POST['userAgent'] ?? '';
            $platform = $_POST['platform'] ?? '';
            
            if (empty($deviceId)) {
                $response = [
                    'success' => false,
                    'message' => 'Device ID is required'
                ];
            } else {
                $response = registerDevice($deviceId, $userAgent, $platform);
            }
            break;
            
        case 'status':
            $deviceId = $_POST['deviceId'] ?? '';
            
            if (empty($deviceId)) {
                $response = [
                    'success' => false,
                    'message' => 'Device ID is required'
                ];
            } else {
                $response = checkDeviceStatus($deviceId);
            }
            break;
            
        case 'list':
            $response = listDevices();
            break;
            
        case 'cleanup':
            $type = $_POST['type'] ?? 'old';
            $param = $_POST['param'] ?? 24;
            
            switch ($type) {
                case 'all':
                    $result = clearAllDevices();
                    $response = [
                        'success' => $result,
                        'message' => $result ? 'All devices cleared' : 'Error clearing devices'
                    ];
                    break;
                    
                case 'inactive':
                    $hours = intval($param);
                    $removedCount = clearInactiveDevices($hours);
                    $response = [
                        'success' => true,
                        'message' => "Removed $removedCount inactive devices (inactive for more than $hours hours)"
                    ];
                    break;
                    
                case 'old':
                default:
                    $removedCount = clearOldSessions();
                    $response = [
                        'success' => true,
                        'message' => "Removed $removedCount old sessions (inactive for more than 24 hours)"
                    ];
            }
            break;
            
        case 'check_login':
            $response = [
                'success' => isLoggedIn(),
                'username' => getCurrentUser()
            ];
            break;
            
        default:
            $response = [
                'success' => false,
                'message' => 'Invalid action'
            ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Clear old sessions periodically (only for non-AJAX requests to avoid interfering with API calls)
if (rand(1, 100) <= 10) { // 10% chance to clean up for demo purposes
    clearOldSessions();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Tracker - Single Device Access</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .login-section {
            background-color: #f0f8ff;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            display: block;
        }
        
        .device-info {
            background-color: #e8f4fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            display: none;
        }
        
        .access-denied {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        
        .access-allowed {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        
        .hidden {
            display: none;
        }
        
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        
        button.logout {
            background-color: #f44336;
        }
        
        button.logout:hover {
            background-color: #d32f2f;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .device-list {
            margin: 20px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .user-display {
            margin-bottom: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Device Tracking System</h1>
        <p>This system allows only one device per user. Each user can only be logged in on one device at a time.</p>
        
        <div id="loginSection" class="login-section">
            <h3>Login</h3>
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" placeholder="Enter your username">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" placeholder="Enter your password">
            </div>
            <button id="loginBtn">Login</button>
            <p>Default login: demo / password</p>
        </div>
        
        <div id="userDisplay" class="user-display hidden">
            Logged in as: <span id="currentUsername"></span>
            <button id="logoutBtn" class="logout">Logout</button>
        </div>
        
        <div id="deviceInfo" class="device-info">
            <h3>Your Device Information</h3>
            <p><strong>Device ID:</strong> <span id="deviceId">Detecting...</span></p>
            <p><strong>User Agent:</strong> <span id="userAgent"></span></p>
            <p><strong>Platform:</strong> <span id="platform"></span></p>
            <button id="registerDevice">Re-register Device</button>
            <button id="checkDevices">Check Active Devices</button>
            <button id="cleanupDevices">Clean Up Old Devices</button>
        </div>
        
        <div id="accessStatus"></div>
        
        <div id="deviceList" class="device-list" style="display:none;">
            <h3>Active Devices</h3>
            <button id="cleanupInactiveDevices" data-hours="2">Clean Up Devices Inactive > 2 Hours</button>
            <button id="cleanupAllDevices">Remove All Devices</button>
            <table>
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>First Access</th>
                        <th>Last Access</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody id="deviceTableBody">
                </tbody>
            </table>
        </div>

        <script>
            // Advanced cleanup functions
            $('#cleanupInactiveDevices').click(function() {
                const hours = $(this).data('hours');
                $(this).prop('disabled', true).text('Cleaning up...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'cleanup',
                        type: 'inactive',
                        param: hours
                    },
                    success: function(response) {
                        alert(response.message);
                        checkActiveDevices(); // Refresh device list
                        $('#cleanupInactiveDevices').prop('disabled', false).text('Clean Up Devices Inactive > ' + hours + ' Hours');
                    },
                    error: function() {
                        alert('Error cleaning up devices');
                        $('#cleanupInactiveDevices').prop('disabled', false).text('Clean Up Devices Inactive > ' + hours + ' Hours');
                    }
                });
            });
            
            $('#cleanupAllDevices').click(function() {
                if (!confirm('Are you sure you want to remove ALL devices? This will clear all records.')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('Removing All...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'cleanup',
                        type: 'all'
                    },
                    success: function(response) {
                        alert(response.message);
                        checkActiveDevices(); // Refresh device list
                        $('#cleanupAllDevices').prop('disabled', false).text('Remove All Devices');
                    },
                    error: function() {
                        alert('Error removing devices');
                        $('#cleanupAllDevices').prop('disabled', false).text('Remove All Devices');
                    }
                });
            });
        </script>
    </div>

    <script>
        $(document).ready(function() {
            // Generate a unique device fingerprint
            function generateDeviceId() {
                // Combine various browser properties to create a unique fingerprint
                const fingerprint = navigator.userAgent + 
                                   navigator.platform + 
                                   screen.width + 
                                   screen.height + 
                                   screen.colorDepth + 
                                   navigator.language + 
                                   navigator.cookieEnabled + 
                                   navigator.onLine;
                
                // Simple hash function to generate a consistent ID
                let hash = 0;
                for (let i = 0; i < fingerprint.length; i++) {
                    const char = fingerprint.charCodeAt(i);
                    hash = ((hash << 5) - hash) + char;
                    hash = hash & hash; // Convert to 32-bit integer
                }
                
                return Math.abs(hash).toString(16);
            }
            
            // Store device ID in localStorage
            let deviceId = localStorage.getItem('deviceId');
            if (!deviceId) {
                deviceId = generateDeviceId();
                localStorage.setItem('deviceId', deviceId);
            }
            
            $('#deviceId').text(deviceId);
            $('#userAgent').text(navigator.userAgent);
            $('#platform').text(navigator.platform);
            
            // Check login status on page load
            checkLoginStatus();
            
            function checkLoginStatus() {
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'check_login'
                    },
                    success: function(response) {
                        if (response.success) {
                            // User is logged in
                            $('#currentUsername').text(response.username);
                            $('#userDisplay').removeClass('hidden');
                            $('#loginSection').hide();
                            $('#deviceInfo').show();
                            
                            // Now check device status
                            checkDeviceStatus();
                        } else {
                            // User is not logged in
                            $('#userDisplay').addClass('hidden');
                            $('#loginSection').show();
                            $('#deviceInfo').hide();
                            $('#accessStatus').html('');
                        }
                    },
                    error: function() {
                        $('#accessStatus').html('<div class="access-denied">Error checking login status. Please refresh the page.</div>');
                    }
                });
            }
            
            // Login handler
            $('#loginBtn').click(function() {
                const username = $('#username').val();
                const password = $('#password').val();
                
                if (!username || !password) {
                    alert('Please enter both username and password');
                    return;
                }
                
                $(this).prop('disabled', true).text('Logging in...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'login',
                        username: username,
                        password: password,
                        deviceId: deviceId,
                        userAgent: navigator.userAgent,
                        platform: navigator.platform
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#accessStatus').html('<div class="access-allowed">Login successful. Device automatically registered.</div>');
                            checkLoginStatus();
                        } else {
                            $('#accessStatus').html('<div class="access-denied">' + response.message + '</div>');
                        }
                        $('#loginBtn').prop('disabled', false).text('Login');
                    },
                    error: function() {
                        $('#accessStatus').html('<div class="access-denied">Error during login. Please try again.</div>');
                        $('#loginBtn').prop('disabled', false).text('Login');
                    }
                });
            });
            
            // Logout handler
            $('#logoutBtn').click(function() {
                $(this).prop('disabled', true).text('Logging out...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'logout'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#accessStatus').html('<div class="access-allowed">Logged out successfully.</div>');
                            checkLoginStatus();
                        } else {
                            $('#accessStatus').html('<div class="access-denied">' + response.message + '</div>');
                        }
                        $('#logoutBtn').prop('disabled', false).text('Logout');
                    },
                    error: function() {
                        $('#accessStatus').html('<div class="access-denied">Error during logout. Please try again.</div>');
                        $('#logoutBtn').prop('disabled', false).text('Logout');
                    }
                });
            });
            
            // Check device status
            function checkDeviceStatus() {
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'status',
                        deviceId: deviceId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#accessStatus').html('<div class="access-allowed">Access granted. Device is registered and active.</div>');
                        } else {
                            $('#accessStatus').html('<div class="access-denied">' + response.message + '</div>');
                        }
                        checkActiveDevices(); // Load device list
                    },
                    error: function() {
                        $('#accessStatus').html('<div class="access-denied">Error checking device status. Please refresh the page.</div>');
                    }
                });
            }
            
            // Register device on the server
            $('#registerDevice').click(function() {
                $(this).prop('disabled', true).text('Re-registering...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'register',
                        deviceId: deviceId,
                        userAgent: navigator.userAgent,
                        platform: navigator.platform
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#accessStatus').html('<div class="access-allowed">Device re-registered successfully. Access granted.</div>');
                        } else {
                            $('#accessStatus').html('<div class="access-denied">' + response.message + '</div>');
                        }
                        $(this).prop('disabled', false).text('Re-register Device');
                        checkActiveDevices(); // Refresh device list
                    },
                    error: function() {
                        alert('Error re-registering device');
                        $(this).prop('disabled', false).text('Re-register Device');
                    }
                });
            });
            
            // Check active devices
            $('#checkDevices').click(checkActiveDevices);
            
            // Cleanup devices
            $('#cleanupDevices').click(function() {
                $(this).prop('disabled', true).text('Cleaning up...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'cleanup',
                        type: 'old'
                    },
                    success: function(response) {
                        alert(response.message);
                        checkActiveDevices(); // Refresh device list
                        $(this).prop('disabled', false).text('Clean Up Old Devices');
                    }.bind(this),
                    error: function() {
                        alert('Error cleaning up devices');
                        $(this).prop('disabled', false).text('Clean Up Old Devices');
                    }
                });
            });
            
            function checkActiveDevices() {
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'list'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '';
                            for (let device of response.devices) {
                                html += '<tr>' +
                                        '<td>' + device.deviceId + '</td>' +
                                        '<td>' + device.firstAccess + '</td>' +
                                        '<td>' + device.lastAccess + '</td>' +
                                        '<td>' + device.userAgent + '</td>' +
                                        '</tr>';
                            }
                            $('#deviceTableBody').html(html);
                            $('#deviceList').show();
                        } else {
                            $('#deviceList').hide();
                        }
                    },
                    error: function() {
                        $('#deviceList').hide();
                    }
                });
            }
            
            // Advanced cleanup functions
            $('#cleanupInactiveDevices').click(function() {
                const hours = $(this).data('hours');
                $(this).prop('disabled', true).text('Cleaning up...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'cleanup',
                        type: 'inactive',
                        param: hours
                    },
                    success: function(response) {
                        alert(response.message);
                        checkActiveDevices(); // Refresh device list
                        $('#cleanupInactiveDevices').prop('disabled', false).text('Clean Up Devices Inactive > ' + hours + ' Hours');
                    },
                    error: function() {
                        alert('Error cleaning up devices');
                        $('#cleanupInactiveDevices').prop('disabled', false).text('Clean Up Devices Inactive > ' + hours + ' Hours');
                    }
                });
            });
            
            $('#cleanupAllDevices').click(function() {
                if (!confirm('Are you sure you want to remove ALL devices? This will clear all records.')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('Removing All...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'cleanup',
                        type: 'all'
                    },
                    success: function(response) {
                        alert(response.message);
                        checkActiveDevices(); // Refresh device list
                        $('#cleanupAllDevices').prop('disabled', false).text('Remove All Devices');
                    },
                    error: function() {
                        alert('Error removing devices');
                        $('#cleanupAllDevices').prop('disabled', false).text('Remove All Devices');
                    }
                });
            });
        });
    </script>
</body>
</html>