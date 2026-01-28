<?php
error_reporting(0);

// Configuration Paths
$app_dir = realpath(__DIR__ . '/..');
$apps_root = dirname($app_dir);
$log_file = $app_dir . '/copyparty.log';
$conf_file = $apps_root . '/copyparty_conf/copyparty.conf';
$port = 3923;

// Helper function to check if Copyparty is running
function is_copyparty_running($port) {
    $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}

// Helper: Tail log
function tail_custom($filepath, $lines = 50) {
    if (!file_exists($filepath)) return "Log file not found.";
    $file = file($filepath);
    if ($file === false) return "Could not read log file.";
    return implode("", array_slice($file, -$lines));
}

// Handle AJAX Actions
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $response = ['success' => false, 'message' => 'Unknown action'];

    if ($action === 'status') {
        $running = is_copyparty_running($port);
        // Get hostname from HTTP_HOST to construct correct link
        $host = $_SERVER['HTTP_HOST'];
        // Strip port if present
        $host = preg_replace('/:\d+$/', '', $host);
        $url = "http://$host:$port";
        
        $response = [
            'success' => true,
            'running' => $running,
            'url' => $url,
            'log' => tail_custom($log_file, 50)
        ];
    } elseif ($action === 'save_config') {
        if (isset($_POST['config'])) {
            // Backup
            if (file_exists($conf_file)) {
                copy($conf_file, $conf_file . '.bak.' . date('YmdHis'));
            }
            $content = str_replace("\r\n", "\n", $_POST['config']);
            // Basic validation?
            if (trim($content) !== '') {
                if (file_put_contents($conf_file, $content) !== false) {
                    $response = ['success' => true, 'message' => 'Configuration saved. Restart required.'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to write config file. Check permissions.'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Config cannot be empty.'];
            }
        }
    } elseif ($action === 'restart') {
        // Trigger restart via shell
        // Using init.sh restart logic if available, or stop/start
        $cmd = "sh " . escapeshellarg($app_dir . '/stop.sh') . " " . escapeshellarg($app_dir) . " ; " .
               "sh " . escapeshellarg($app_dir . '/start.sh') . " " . escapeshellarg($app_dir) . " > /dev/null 2>&1 &";
        shell_exec($cmd);
        $response = ['success' => true, 'message' => 'Restart command issued. Please wait.'];
    }

    echo json_encode($response);
    exit;
}

// Initial Data
$config_content = file_exists($conf_file) ? file_get_contents($conf_file) : "# Config file not found at $conf_file";
?>
<div class="copyparty-app">
    <style>
        .copyparty-app {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 20px;
            text-align: left;
            max-width: 100%;
            color: #212529;
            box-sizing: border-box;
        }
        .copyparty-app * { box-sizing: border-box; }
        
        .copyparty-app header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; }
        .copyparty-app .brand { display: flex; align-items: center; gap: 12px; }
        .copyparty-app .logo { width: 40px; height: 40px; object-fit: contain; }
        .copyparty-app h1 { margin: 0; font-size: 1.75rem; color: #000; }
        .copyparty-app a { color: #007bff; text-decoration: none; }
        .copyparty-app a:hover { text-decoration: underline; }
        
        .copyparty-app .section { margin-bottom: 25px; }
        .copyparty-app .card { border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; background-color: #fff; }
        .copyparty-app h2 { font-size: 1.25rem; margin-top: 0; margin-bottom: 15px; border-bottom: 1px solid #f1f1f1; padding-bottom: 10px; }
        
        .copyparty-app .btn { display: inline-block; font-weight: 400; text-align: center; vertical-align: middle; cursor: pointer; border: 1px solid transparent; padding: 0.375rem 0.75rem; font-size: 0.9rem; line-height: 1.5; border-radius: 0.25rem; color: #000 !important; }
        .copyparty-app .btn-primary { background-color: #007bff; border-color: #007bff; color: #000 !important; }
        .copyparty-app .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
        .copyparty-app .btn-success { background-color: #28a745; border-color: #28a745; color: #fff !important; }
        .copyparty-app .btn-danger { background-color: #dc3545; border-color: #dc3545; color: #000 !important; }
        .copyparty-app .btn-secondary { background-color: #6c757d; border-color: #6c757d; color: #000 !important; }
        
        .copyparty-app textarea { width: 100%; height: 300px; font-family: monospace; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; resize: vertical; }
        .copyparty-app .log-container { background: #212529; color: #f8f9fa; padding: 10px; border-radius: 4px; height: 200px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; margin-bottom: 10px; border: 1px solid #444; }
        
        .copyparty-app .status-indicator { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; margin-right: 10px; }
        .copyparty-app .status-running { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .copyparty-app .status-stopped { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        #cpp-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.7); z-index: 1000;
            display: none; align-items: center; justify-content: center;
        }
        .cpp-spinner {
            border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%;
            width: 40px; height: 40px; animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>

    <div id="cpp-overlay"><div class="cpp-spinner"></div></div>

    <header>
        <div class="brand">
            <img src="/apps/copyparty/logo.png" class="logo" alt="Copyparty Logo">
            <h1>Copyparty</h1>
        </div>
        <div>
            <button class="btn btn-secondary" onclick="refreshStatus()">Refresh Status</button>
        </div>
    </header>

    <div class="section">
        <div class="card">
            <h2>Status</h2>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div id="status-display">Checking...</div>
                <div id="action-buttons">
                    <button class="btn btn-primary" onclick="restartApp()">Restart App</button>
                </div>
            </div>
            <div id="link-container" style="margin-top: 15px; display: none;">
                <a id="app-link" href="#" target="_blank" class="btn btn-success">Open Copyparty Interface</a>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="card">
            <h2>Configuration</h2>
            <p>File: <?php echo htmlspecialchars($conf_file); ?></p>
            <textarea id="config-editor" spellcheck="false"><?php echo htmlspecialchars($config_content); ?></textarea>
            <div style="margin-top: 10px; text-align: right;">
                <button class="btn btn-primary" onclick="saveConfig()">Save Configuration</button>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="card">
            <h2>System Log</h2>
            <div id="log-display" class="log-container">Loading...</div>
        </div>
    </div>

    <script>
        var cppUrl = "/apps/copyparty/index.php";

        function showOverlay() { document.getElementById('cpp-overlay').style.display = 'flex'; }
        function hideOverlay() { document.getElementById('cpp-overlay').style.display = 'none'; }

        async function apiCall(params) {
            let formData = new FormData();
            for (let key in params) {
                formData.append(key, params[key]);
            }
            try {
                let response = await fetch(cppUrl, { method: 'POST', body: formData });
                return await response.json();
            } catch (e) {
                console.error(e);
                return { success: false, message: e.message };
            }
        }

        async function refreshStatus() {
            showOverlay();
            const data = await apiCall({ action: 'status' });
            hideOverlay();

            const statusDiv = document.getElementById('status-display');
            const linkDiv = document.getElementById('link-container');
            const logDiv = document.getElementById('log-display');

            if (data.success) {
                if (data.running) {
                    statusDiv.innerHTML = '<span class="status-indicator status-running">Running</span> on port 3923';
                    linkDiv.style.display = 'block';
                    document.getElementById('app-link').href = data.url;
                    document.getElementById('app-link').innerText = "Open " + data.url;
                } else {
                    statusDiv.innerHTML = '<span class="status-indicator status-stopped">Stopped</span>';
                    linkDiv.style.display = 'none';
                }
                logDiv.innerText = data.log;
            } else {
                statusDiv.innerText = "Error checking status";
            }
        }

        async function saveConfig() {
            if (!confirm("Save configuration?")) return;
            showOverlay();
            const content = document.getElementById('config-editor').value;
            const data = await apiCall({ action: 'save_config', config: content });
            hideOverlay();
            alert(data.message);
        }

        async function restartApp() {
            if (!confirm("Restart Copyparty? This will interrupt active transfers.")) return;
            showOverlay();
            const data = await apiCall({ action: 'restart' });
            alert(data.message);
            // Wait a bit then refresh
            setTimeout(() => {
                hideOverlay();
                refreshStatus();
            }, 5000);
        }

        // Init
        setTimeout(refreshStatus, 100);
    </script>
</div>
