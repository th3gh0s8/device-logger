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
            <button id="registerDevice">Register Device</button>
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
                    url: 'device_tracker.php',
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
                    url: 'device_tracker.php',
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
                    url: 'device_tracker.php',
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
                    url: 'device_tracker.php',
                    method: 'POST',
                    data: {
                        action: 'login',
                        username: username,
                        password: password
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#accessStatus').html('<div class="access-allowed">Login successful. Please register your device.</div>');
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
                    url: 'device_tracker.php',
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
                    url: 'device_tracker.php',
                    method: 'POST',
                    data: {
                        action: 'status',
                        deviceId: deviceId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#accessStatus').html('<div class="access-allowed">Access granted. Your device is registered.</div>');
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
                $(this).prop('disabled', true).text('Registering...');
                
                $.ajax({
                    url: 'device_tracker.php',
                    method: 'POST',
                    data: {
                        action: 'register',
                        deviceId: deviceId,
                        userAgent: navigator.userAgent,
                        platform: navigator.platform
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#accessStatus').html('<div class="access-allowed">Device registered successfully. Access granted.</div>');
                        } else {
                            $('#accessStatus').html('<div class="access-denied">' + response.message + '</div>');
                        }
                        $(this).prop('disabled', false).text('Register Device');
                        checkActiveDevices(); // Refresh device list
                    },
                    error: function() {
                        alert('Error registering device');
                        $(this).prop('disabled', false).text('Register Device');
                    }
                });
            });
            
            // Check active devices
            $('#checkDevices').click(checkActiveDevices);
            
            // Cleanup devices
            $('#cleanupDevices').click(function() {
                $(this).prop('disabled', true).text('Cleaning up...');
                
                $.ajax({
                    url: 'device_tracker.php',
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
                    url: 'device_tracker.php',
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
                    url: 'device_tracker.php',
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
                    url: 'device_tracker.php',
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