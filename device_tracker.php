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

function authenticateUser($username, $password) {
    $users = loadUsers();
    
    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        // Store user in session
        $_SESSION['username'] = $username;
        return [
            'success' => true,
            'message' => 'Login successful'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Invalid username or password'
    ];
}

function logoutUser() {
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
            return [
                'success' => false,
                'message' => 'Access denied: Only one device allowed per user. Another device is already logged in.'
            ];
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
            return [
                'success' => false,
                'message' => 'Access denied: Another device is already logged in for this user. Only one device allowed.'
            ];
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

// Clear old sessions periodically
if (rand(1, 100) <= 10) { // 10% chance to clean up for demo purposes
    clearOldSessions();
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = [];
    
    switch ($action) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $response = [
                    'success' => false,
                    'message' => 'Username and password are required'
                ];
            } else {
                $response = authenticateUser($username, $password);
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
?>