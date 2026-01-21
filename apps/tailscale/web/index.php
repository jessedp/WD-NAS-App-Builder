<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Tailscale - Login Required</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 40px; text-align: center; color: #212529; }
    </style>
</head>
<body>
    <h2>Please login to the NAS to continue</h2>
</body>
</html>';
    exit;
}

$tailscale_bin = realpath(__DIR__ . '/../tailscale');
$web_port = 8282;
$keepalive_file = '/tmp/tailscale-web.keepalive';

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
            sleep(1); // Give it a second to start
        }
    } elseif ($_POST['action'] === 'stop_web') {
        shell_exec('pkill -f "tailscale web --listen 0.0.0.0:' . $web_port . '"');
        if (file_exists($keepalive_file)) unlink($keepalive_file);
    }
    header("Location: index.php");
    exit;
}

$web_pid = shell_exec('pgrep -f "tailscale web --listen 0.0.0.0:' . $web_port . '"');
$ts_ip = trim(shell_exec($tailscale_bin . ' ip --4 2>/dev/null'));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tailscale</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; text-align: left; max-width: 800px; margin: 0 auto; line-height: 1.5; color: #212529; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 6px; overflow-x: auto; border: 1px solid #e9ecef; font-size: 0.875em; }
        header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .logo { width: 40px; height: 40px; object-fit: contain; }
        h1 { margin: 0; font-size: 1.75rem; color: #000; }
        .section { margin-top: 25px; margin-bottom: 25px; }
        .info { color: #6c757d; font-size: 0.875em; }
        
        .btn { display: inline-block; font-weight: 400; text-align: center; vertical-align: middle; cursor: pointer; border: 1px solid transparent; padding: 0.375rem 0.75rem; font-size: 0.9rem; line-height: 1.5; border-radius: 0.25rem; transition: all .15s ease-in-out; text-decoration: none; border: 1px solid #dee2e6; }
        .btn-muted { color: #6c757d; background-color: #f8f9fa; }
        .btn-muted:hover { color: #495057; background-color: #e2e6ea; }
        .btn-primary { color: #fff; background-color: #007bff; border-color: #007bff; font-weight: 600; }
        .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
        .btn-danger { color: #fff; background-color: #dc3545; border-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        
        .card { border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .auth-card { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }
        .web-card { background-color: #f8f9fa; }
        .status-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .status-on { background-color: #28a745; }
        .status-off { background-color: #6c757d; }
    </style>
    <?php if ($web_pid): ?>
    <script>
        // Keep-alive heartbeat
        setInterval(function() {
            fetch('?heartbeat=1')
                .then(response => response.text())
                .then(data => {
                    if (data === 'stopped') window.location.reload();
                });
        }, 60000);
    </script>
    <?php endif; ?>
</head>
<body>
    <header>
        <div class="brand">
            <img src="tailscale.png" class="logo" alt="Tailscale Logo">
            <h1>Tailscale</h1>
        </div>
        <button class="btn btn-muted" onclick="window.location.reload();">Refresh Status</button>
    </header>

    <div class="section">
        <div class="card web-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="margin: 0; font-size: 1.25rem;">Tailscale Web UI</h2>
                    <p class="info" style="margin: 5px 0 0 0;">
                        <?php if ($web_pid): ?>
                            <span class="status-dot status-on"></span> Running on port <?php echo $web_port; ?>
                        <?php else: ?>
                            <span class="status-dot status-off"></span> Not running
                        <?php endif; ?>
                    </p>
                </div>
                <form method="POST">
                    <?php if ($web_pid): ?>
                        <input type="hidden" name="action" value="stop_web">
                        <button type="submit" class="btn btn-danger">Stop Web UI</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="start_web">
                        <button type="submit" class="btn btn-primary" <?php echo !$ts_ip ? 'disabled' : ''; ?>>Start Web UI</button>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if ($web_pid && $ts_ip): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                    <p>Web UI is active. It will automatically stop 5 minutes after you close this page.</p>
                    <a href="http://<?php echo $ts_ip; ?>:<?php echo $web_port; ?>" target="_blank" class="btn btn-primary">Open Web UI: http://<?php echo $ts_ip; ?>:<?php echo $web_port; ?></a>
                </div>
            <?php elseif (!$ts_ip): ?>
                <p class="info" style="margin-top: 10px; color: #dc3545;">Cannot start Web UI: No Tailscale IP found. Is Tailscale logged in?</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="section status-box">
        <?php
            $auth_url = null;
            $status_output = "";

            if (file_exists($tailscale_bin)) {
                $pid = shell_exec('pgrep tailscaled');
                if (!$pid) {
                    echo "<p style='color: #dc3545; font-weight: bold;'>Warning: tailscaled daemon does not appear to be running.</p>";
                }
                
                $status_output = shell_exec($tailscale_bin . ' status 2>&1');
                
                if (preg_match('/https:\/\/login\.tailscale\.com\/a\/[a-zA-Z0-9]+/', $status_output, $matches)) {
                    $auth_url = $matches[0];
                }
            } else {
                $status_output = "Tailscale binary not found.";
            }
        ?>

        <?php if ($auth_url): ?>
            <div class="card auth-card">
                <strong>Authentication Required</strong>
                <p>Tailscale needs you to log in to add this node:</p>
                <a href="<?php echo $auth_url; ?>" target="_blank" class="btn btn-primary">
                    Login: <?php echo htmlspecialchars($auth_url); ?>
                </a>
            </div>
        <?php endif; ?>

        <h2 style="margin: 0 0 8px 0; font-size: 1.25rem;">CLI Status</h2>
        <pre><?php echo htmlspecialchars($status_output ?: 'No status output'); ?></pre>
    </div>

    <hr>

    <p class="info">To further configure Tailscale via command line, please SSH into your NAS:</p>
    <pre>$ cd <?php echo realpath(__DIR__ . '/..'); ?>

$ ./tailscale up</pre>
    <p class="info">Or consult the <a href="https://tailscale.com/docs/" target="_blank" style="color: #007bff;">Tailscale documentation</a>.</p>
</body>
</html>
