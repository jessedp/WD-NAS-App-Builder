<?php
session_start();

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Configuration Paths
$app_dir = realpath(__DIR__ . '/..');
$apps_root = dirname($app_dir);
$log_file = $app_dir . '/copyparty.log';
$conf_file = $apps_root . '/copyparty_conf/copyparty.conf';
$port = 3923;

// Helper function to check if Copyparty is running
function is_copyparty_running($host, $port) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}

// Check status
$is_running = is_copyparty_running('127.0.0.1', $port);

// Redirect if running and not in "recovery mode" (no post action)
// We add a query param ?recovery=1 to force stay on this page if needed, mostly for debugging or if the redirect logic loops.
// But the requirement is "The redirect should always happen automatically".
if ($is_running && !isset($_GET['recovery']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $host = $_SERVER['SERVER_NAME']; // Use the hostname the user accessed the NAS with
    header("Location: http://$host:$port");
    exit;
}

// Security check - identical to existing apps
if (!isset($_SESSION['username'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Copyparty - Login Required</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; text-align: left; max-width: 900px; margin: 0 auto; line-height: 1.5; color: #212529; background-color: #f8f9fa; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #dee2e6; margin-top: 40px; }
        header { border-bottom: 1px solid #dee2e6; padding-bottom: 20px; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; }
        .brand { display: flex; align-items: center; gap: 15px; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        h1 { margin: 0; font-size: 1.5rem; color: #212529; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid transparent; }
        .alert-danger { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">
                <img src="logo.png" class="logo" alt="Copyparty Logo" onerror="this.style.display=\'none\'">
                <div>
                    <h1>Copyparty - Recovery Dashboard</h1>
                </div>
            </div>
        </header>
        <div class="alert alert-danger">
            Please login to the NAS to continue.
        </div>
    </div>
</body>
</html>';
    exit;
}

// Handle Configuration Save
$message = '';
$message_type = ''; // success or error

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (file_exists($conf_file)) {
        // Backup existing config
        $backup_file = $conf_file . '.broken.' . date('Ymd_His');
        if (copy($conf_file, $backup_file)) {
            // Write new config
            $new_content = $_POST['config_content'];
            // Basic sanity check: ensure it's not empty
            if (trim($new_content) !== '') {
                // normalize line endings to \n
                $new_content = str_replace("\r\n", "\n", $new_content);
                
                if (file_put_contents($conf_file, $new_content) !== false) {
                    $message = "Configuration saved. Backup created at " . basename($backup_file) . ".";
                    $message_type = 'success';
                    
                    // Handle Restart
                    if (isset($_POST['restart_app'])) {
                        // Execute restart
                        $cmd_stop = "sh " . escapeshellarg($app_dir . '/stop.sh') . " " . escapeshellarg($app_dir) . " 2>&1";
                        $cmd_start = "sh " . escapeshellarg($app_dir . '/start.sh') . " " . escapeshellarg($app_dir) . " 2>&1";
                        
                        $output_stop = shell_exec($cmd_stop);
                        sleep(2); // Give it a moment to stop
                        $output_start = shell_exec($cmd_start);
                        sleep(3); // Give it a moment to start
                        
                        // Check if it came back up
                        if (is_copyparty_running('127.0.0.1', $port)) {
                            $host = $_SERVER['SERVER_NAME'];
                            $message .= "<br><strong>App restarted successfully! Redirecting...</strong>";
                            $message .= "<script>setTimeout(function(){ window.location.href = 'http://" . $host . ":" . $port . "'; }, 3000);</script>";
                        } else {
                            $message .= "<br><strong>Restart attempted but app is not responding. Check logs below.</strong>";
                            $message_type = 'warning';
                        }
                    } else {
                        $message .= "<br><strong>Please restart the app in the App Center to apply changes.</strong>";
                    }
                    
                } else {
                    $message = "Failed to write configuration file.";
                    $message_type = 'error';
                }
            } else {
                $message = "Configuration cannot be empty.";
                $message_type = 'error';
            }
        } else {
            $message = "Failed to create backup file. Configuration not saved.";
            $message_type = 'error';
        }
    } else {
        $message = "Configuration file not found at expected path: " . htmlspecialchars($conf_file);
        $message_type = 'error';
    }
}

// Read Log File (Last 50 lines)
$log_content = "Log file not found.";
if (file_exists($log_file)) {
    // efficient tail
    $lines = file($log_file);
    if ($lines !== false) {
        $lines = array_slice($lines, -50);
        $log_content = implode("", $lines);
    } else {
        $log_content = "Could not read log file.";
    }
}

// Read Config File
$config_content = "";
if (file_exists($conf_file)) {
    $config_content = file_get_contents($conf_file);
} else {
    $config_content = "# Configuration file not found at $conf_file";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Copyparty - Recovery Dashboard</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; text-align: left; max-width: 900px; margin: 0 auto; line-height: 1.5; color: #212529; background-color: #f8f9fa; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #dee2e6; }
        
        header { border-bottom: 1px solid #dee2e6; padding-bottom: 20px; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; }
        .brand { display: flex; align-items: center; gap: 15px; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        h1 { margin: 0; font-size: 1.5rem; color: #212529; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid transparent; }
        .alert-danger { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .alert-success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .alert-warning { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }
        
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 0.9em; }
        .status-running { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-stopped { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        h2 { font-size: 1.25rem; margin-top: 0; margin-bottom: 15px; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px; }
        
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        textarea { width: 100%; height: 300px; font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; font-size: 14px; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; resize: vertical; }
        
        pre { background: #212529; color: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        
        .btn { display: inline-block; font-weight: 400; text-align: center; vertical-align: middle; cursor: pointer; border: 1px solid transparent; padding: 0.375rem 0.75rem; font-size: 1rem; line-height: 1.5; border-radius: 0.25rem; transition: background-color .15s ease-in-out; text-decoration: none; }
        .btn-primary { color: #fff; background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
        
        .section { margin-bottom: 40px; }
        .refresh-link { font-size: 0.9em; text-decoration: none; color: #007bff; margin-left: 10px; }
        .refresh-link:hover { text-decoration: underline; }
    </style>
    <script src="/web/jquery/js/jquery-3.5.1.min.js"></script>
    <script>
        // Verify authentication with the NAS backend
        $.ajax({
            type: "PUT",
            url: '/nas/v1/auth',
            statusCode: {
                403: function() {
                    $('body').html('<div class="container"><header><div class="brand"><img src="logo.png" class="logo" alt="Copyparty Logo"><div><h1>Copyparty - Recovery Dashboard</h1></div></div></header><div class="alert alert-danger">Please login to the NAS to continue.</div></div>');
                }
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">
                <img src="logo.png" class="logo" alt="Copyparty Logo" onerror="this.style.display='none'">
                <div>
                    <h1>Copyparty - Recovery Dashboard</h1>
                </div>
            </div>
            <div>
                <?php if ($is_running): ?>
                    <span class="status-badge status-running">Running</span>
                <?php else: ?>
                    <span class="status-badge status-stopped">Stopped / Unreachable</span>
                <?php endif; ?>
                <a href="index.php" class="refresh-link">Refresh Status</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo ($message_type == 'success') ? 'success' : 'danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <strong>Troubleshooting:</strong> If Copyparty is not starting, check the log below. You can edit the configuration file directly here to fix errors. After saving, you must toggle the App OFF and ON in the NAS App Center.
        </div>

        <div class="section">
            <h2>System Log (Last 50 lines)</h2>
            <pre><?php echo htmlspecialchars($log_content); ?></pre>
        </div>

        <div class="section">
            <h2>Configuration Editor</h2>
            <p style="font-size: 0.9em; color: #666; margin-bottom: 10px;">
                File: <?php echo htmlspecialchars($conf_file); ?>
            </p>
            <form method="POST">
                <textarea name="config_content" spellcheck="false"><?php echo htmlspecialchars($config_content); ?></textarea>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="submit" name="save_config" class="btn">Save Only</button>
                    <button type="submit" name="save_config" value="1" onclick="this.form.appendChild(document.createElement('input')).setAttribute('type', 'hidden'); this.form.lastChild.setAttribute('name', 'restart_app'); this.form.lastChild.setAttribute('value', '1');" class="btn btn-primary">Save & Restart App</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>