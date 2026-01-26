<?php
error_reporting(0);
// Session and auth handled by NAS UI
$tailscale_bin = realpath(__DIR__ . '/../tailscale');
$web_port = 8282;
$keepalive_file = '/tmp/tailscale-web.keepalive';

// Helper function to execute command with timeout
function exec_timeout($cmd, $timeout_seconds) {
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    $process = proc_open($cmd, $descriptorspec, $pipes);
    if (!is_resource($process)) {
        return "Failed to execute command";
    }
    
    // Set non-blocking
    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);
    
    $output = "";
    $error = "";
    $start_time = time();
    
    while (time() - $start_time < $timeout_seconds) {
        $status = proc_get_status($process);
        if (!$status['running']) {
            $output .= stream_get_contents($pipes[1]);
            $error .= stream_get_contents($pipes[2]);
            break;
        }
        
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;
        
        if (stream_select($read, $write, $except, 0, 200000) > 0) {
            foreach ($read as $stream) {
                if ($stream === $pipes[1]) {
                    $output .= stream_get_contents($pipes[1]);
                } elseif ($stream === $pipes[2]) {
                    $error .= stream_get_contents($pipes[2]);
                }
            }
        }
    }
    
    $status = proc_get_status($process);
    if ($status['running']) {
        // Kill the process
        proc_terminate($process);
        $output .= "\n(Command timed out)";
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    
    return $output . $error;
}

// Handle Status Polling
if (isset($_GET['status'])) {
    header('Content-Type: application/json');
    // Add timeout to prevent hanging if daemon is stuck
    $status_output = exec_timeout($tailscale_bin . ' status 2>&1', 3);
    $auth_url = null;
    if (preg_match('/https:\/\/login\.tailscale\.com\/a\/[a-zA-Z0-9]+/', $status_output, $matches)) {
        $auth_url = $matches[0];
    }
    
    // Check web UI status
    $web_pid = shell_exec('pgrep -f "tailscale web --listen 0.0.0.0:' . $web_port . '"');
    $ts_ip = trim(exec_timeout($tailscale_bin . ' ip --4 2>/dev/null', 2));
    
    echo json_encode([
        'auth_url' => $auth_url, 
        'status' => $status_output ?: 'Timeout or service not running',
        'web_running' => !!$web_pid,
        'ts_ip' => $ts_ip
    ]);
    exit;
}

// Handle AJAX heartbeat
if (isset($_GET['heartbeat'])) {
    if (file_exists($keepalive_file)) {
        touch($keepalive_file);
        echo "ok";
    } else {
        echo "stopped";
    }
    exit;
}

// Handle Start/Stop actions
if (isset($_POST['action'])) {
    $result = ['success' => false, 'message' => 'Unknown action'];
    
    if ($_POST['action'] === 'start_web') {
        $pid = shell_exec('pgrep -f "tailscale web --listen 0.0.0.0:' . $web_port . '"');
        if (!$pid) {
            touch($keepalive_file);
            // Start the web UI and a watchdog script that kills it if the keepalive file isn't touched for 5 minutes
            $cmd = "nohup sh -c '" . 
                   $tailscale_bin . " web --listen 0.0.0.0:" . $web_port . " & " . 
                   "while sleep 60; do " . 
                   "  if [ ! -f " . $keepalive_file . " ] || [ $(find " . $keepalive_file . " -mmin +5) ]; then " . 
                   "    pkill -f \"tailscale web --listen 0.0.0.0:" . $web_port . "\"; " . 
                   "    rm -f " . $keepalive_file . "; " . 
                   "    break; " . 
                   "  fi; " . 
                   "done" . 
                   "' > /dev/null 2>&1 &";
            shell_exec($cmd);
            sleep(2); // Give it a moment to start
            $result = ['success' => true, 'message' => 'Web UI started'];
        } else {
             $result = ['success' => true, 'message' => 'Web UI already running'];
        }
    } elseif ($_POST['action'] === 'stop_web') {
        shell_exec('pkill -f "tailscale web --listen 0.0.0.0:' . $web_port . '"');
        if (file_exists($keepalive_file)) unlink($keepalive_file);
        $result = ['success' => true, 'message' => 'Web UI stopped'];
    } elseif ($_POST['action'] === 'restart_app') {
        $app_dir = realpath(__DIR__ . '/..');
        shell_exec("sh " . escapeshellarg($app_dir . '/init.sh') . " restart " . escapeshellarg($app_dir) . " > /dev/null 2>&1 &");
        sleep(3); // Wait for restart
        $result = ['success' => true, 'message' => 'Application restarting...'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

$web_pid = shell_exec('pgrep -f "tailscale web --listen 0.0.0.0:' . $web_port . '"');
$ts_ip = trim(exec_timeout($tailscale_bin . ' ip --4 2>/dev/null', 2));
?>
<div class="tailscale-app">
    <style>
        /* Scope styles to .tailscale-app to prevent leaking into NAS UI */
        .tailscale-app {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 20px;
            text-align: left;
            max-width: 100%; /* Adapts to container */
            color: #212529;
            box-sizing: border-box;
        }
        .tailscale-app * { box-sizing: border-box; }
        
        .tailscale-app pre { background: #f8f9fa; padding: 15px; border-radius: 6px; overflow-x: auto; border: 1px solid #e9ecef; font-size: 0.875em; }
        .tailscale-app header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; flex-wrap: wrap; gap: 10px; }
        .tailscale-app .brand { display: flex; align-items: center; gap: 12px; }
        .tailscale-app .logo { width: 40px; height: 40px; object-fit: contain; }
        .tailscale-app h1 { margin: 0; font-size: 1.75rem; color: #000; }
        .tailscale-app .section { margin-top: 25px; margin-bottom: 25px; }
        .tailscale-app .info { color: #6c757d; font-size: 0.875em; }
        
        .tailscale-app .btn { display: inline-block; font-weight: 400; text-align: center; vertical-align: middle; cursor: pointer; border: 1px solid transparent; padding: 0.375rem 0.75rem; font-size: 0.9rem; line-height: 1.5; border-radius: 0.25rem; transition: all .15s ease-in-out; text-decoration: none; border: 1px solid #dee2e6; margin-left: 5px; }
        .tailscale-app .btn-muted { color: #6c757d; background-color: #f8f9fa; }
        .tailscale-app .btn-muted:hover { color: #495057; background-color: #e2e6ea; }
        .tailscale-app .btn-primary { color: #fff !important; background-color: #007bff; border-color: #007bff; font-weight: 600; }
        .tailscale-app .btn-primary:hover:not(:disabled) { background-color: #0069d9; border-color: #0062cc; }
        .tailscale-app .btn-danger { color: #fff !important; background-color: #dc3545; border-color: #dc3545; }
        .tailscale-app .btn-danger:hover:not(:disabled) { background-color: #c82333; }
        
        .tailscale-app .btn:disabled {
            cursor: not-allowed;
            filter: grayscale(1);
            opacity: 0.6;
            color: #666 !important;
            background-color: #e9ecef !important;
            border-color: #dee2e6 !important;
        }
        
        .tailscale-app .card { border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .tailscale-app .auth-card { background-color: #fff3cd; border-color: #ffeeba; color: #856404; display: none; }
        .tailscale-app .web-card { background-color: #f8f9fa; }
        .tailscale-app .status-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .tailscale-app .status-on { background-color: #28a745; }
        .tailscale-app .status-off { background-color: #6c757d; }
        
        #tailscale-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.7); z-index: 1000;
            display: none; align-items: center; justify-content: center;
        }
        .tailscale-spinner {
            border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%;
            width: 40px; height: 40px; animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
    <script>
        function showOverlay() { document.getElementById('tailscale-overlay').style.display = 'flex'; }
        function hideOverlay() { document.getElementById('tailscale-overlay').style.display = 'none'; }

        function performAction(action) {
            if (action === 'restart_app' && !confirm('Are you sure you want to restart Tailscale? This will interrupt connections.')) {
                return;
            }
            
            showOverlay();
            const formData = new FormData();
            formData.append('action', action);
            
            fetch('/apps/tailscale/index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Wait a bit if restarting, otherwise refresh status immediately
                    setTimeout(refreshStatus, action === 'restart_app' ? 5000 : 1000);
                } else {
                    alert('Error: ' + data.message);
                    hideOverlay();
                }
            })
            .catch(err => {
                console.error(err);
                hideOverlay();
            });
        }

        function refreshStatus() {
            showOverlay();
            fetch('/apps/tailscale/index.php?status=1')
            .then(response => response.json())
            .then(data => {
                // Update Status Text
                document.getElementById('status-output').textContent = data.status;
                
                // Update Auth Card
                const authCard = document.getElementById('auth-card');
                if (data.auth_url) {
                    authCard.style.display = 'block';
                    document.getElementById('auth-link').href = data.auth_url;
                    document.getElementById('auth-link').textContent = 'Login: ' + data.auth_url;
                } else {
                    authCard.style.display = 'none';
                }
                
                // Update Web UI Status
                const webStatusText = document.getElementById('web-status-text');
                const webStartBtn = document.getElementById('btn-start-web');
                const webStopBtn = document.getElementById('btn-stop-web');
                const webLinkContainer = document.getElementById('web-link-container');
                const webErrorText = document.getElementById('web-ui-error');
                
                if (data.web_running) {
                    webStatusText.innerHTML = '<span class="status-dot status-on"></span> Running on port <?php echo $web_port; ?>';
                    webStartBtn.style.display = 'none';
                    webStopBtn.style.display = 'inline-block';
                    
                    if (data.ts_ip) {
                        webLinkContainer.style.display = 'block';
                        const link = document.getElementById('web-ui-link');
                        link.href = 'http://' + data.ts_ip + ':<?php echo $web_port; ?>';
                        link.textContent = 'Open Web UI: http://' + data.ts_ip + ':<?php echo $web_port; ?>';
                        webErrorText.style.display = 'none';
                    } else {
                        webLinkContainer.style.display = 'none';
                        webErrorText.style.display = 'block';
                    }
                } else {
                    webStatusText.innerHTML = '<span class="status-dot status-off"></span> Not running';
                    webStartBtn.style.display = 'inline-block';
                    webStopBtn.style.display = 'none';
                    webLinkContainer.style.display = 'none';
                    webErrorText.style.display = 'none';
                    
                    webStartBtn.disabled = !data.ts_ip;
                }
                
                hideOverlay();
            })
            .catch(err => {
                console.error(err);
                hideOverlay();
            });
        }

        <?php if ($web_pid): ?>
        // Keep-alive heartbeat
        setInterval(function() {
            fetch('/apps/tailscale/index.php?heartbeat=1')
                .then(response => response.text())
                .then(data => {
                    if (data === 'stopped') refreshStatus();
                });
        }, 60000);
        <?php endif; ?>
    </script>

    <div id="tailscale-overlay"><div class="tailscale-spinner"></div></div>

    <header>
        <div class="brand">
            <img src="/apps/tailscale/tailscale.png" class="logo" alt="Tailscale Logo">
            <h1>Tailscale</h1>
        </div>
        <div>
            <button class="btn btn-muted" onclick="performAction('restart_app')">Restart Tailscale</button>
            <button class="btn btn-muted" onclick="refreshStatus()">Refresh Status</button>
        </div>
    </header>

    <div class="section">
        <div class="card web-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="margin: 0; font-size: 1.25rem;">Tailscale Web UI</h2>
                    <p class="info" style="margin: 5px 0 0 0;" id="web-status-text">
                        <?php if ($web_pid): ?>
                            <span class="status-dot status-on"></span> Running on port <?php echo $web_port; ?>
                        <?php else: ?>
                            <span class="status-dot status-off"></span> Not running
                        <?php endif; ?>
                    </p>
                </div>
                <div id="web-action-container">
                    <button id="btn-stop-web" class="btn btn-danger" style="display: <?php echo $web_pid ? 'inline-block' : 'none'; ?>" onclick="performAction('stop_web')">Stop Web UI</button>
                    <button id="btn-start-web" class="btn btn-primary" style="display: <?php echo $web_pid ? 'none' : 'inline-block'; ?>" <?php echo !$ts_ip ? 'disabled' : ''; ?> onclick="performAction('start_web')">Start Web UI</button>
                </div>
            </div>
            
            <div id="web-link-container" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6; display: <?php echo ($web_pid && $ts_ip) ? 'block' : 'none'; ?>;">
                <p>Web UI is active. It will automatically stop 5 minutes after you close this page.</p>
                <a id="web-ui-link" href="http://<?php echo $ts_ip; ?>:<?php echo $web_port; ?>" target="_blank" class="btn btn-primary">Open Web UI: http://<?php echo $ts_ip; ?>:<?php echo $web_port; ?></a>
            </div>
            <p id="web-ui-error" class="info" style="margin-top: 10px; color: #dc3545; display: <?php echo (!$web_pid && !$ts_ip) ? 'none' : ((!$ts_ip) ? 'block' : 'none'); ?>;">Cannot start Web UI: No Tailscale IP found. Is Tailscale logged in?</p>
        </div>
    </div>
    
    <div class="section status-box">
        <?php
            $auth_url = null;
            $status_output = "";

            if (file_exists($tailscale_bin)) {
                // Add timeout to initial status check too
                $status_output = exec_timeout($tailscale_bin . ' status 2>&1', 3);
                
                if (preg_match('/https:\/\/login\.tailscale\.com\/a\/[a-zA-Z0-9]+/', $status_output, $matches)) {
                    $auth_url = $matches[0];
                }
            } else {
                $status_output = "Tailscale binary not found.";
            }
        ?>

        <div id="auth-card" class="card auth-card" style="display: <?php echo $auth_url ? 'block' : 'none'; ?>;">
            <strong>Authentication Required</strong>
            <p>Tailscale needs you to log in to add this node:</p>
            <a id="auth-link" href="<?php echo $auth_url; ?>" target="_blank" class="btn btn-primary">
                Login: <?php echo htmlspecialchars($auth_url); ?>
            </a>
        </div>

        <h2 style="margin: 0 0 8px 0; font-size: 1.25rem;">CLI Status</h2>
        <pre id="status-output"><?php echo htmlspecialchars($status_output ?: 'No status output'); ?></pre>
    </div>

    <hr>

    <p class="info">To further configure Tailscale via command line, please SSH into your NAS:</p>
    <pre>$ cd <?php echo realpath(__DIR__ . '/..'); ?>

$ ./tailscale up</pre>
    <p class="info">Or consult the <a href="https://tailscale.com/docs/" target="_blank" style="color: #007bff;">Tailscale documentation</a>.</p>
</div>