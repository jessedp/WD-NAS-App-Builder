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
        .status-box { margin-top: 20px; }
        .info { color: #6c757d; font-size: 0.875em; }
        
        .btn { display: inline-block; font-weight: 400; text-align: center; vertical-align: middle; cursor: pointer; border: 1px solid transparent; padding: 0.375rem 0.75rem; font-size: 0.9rem; line-height: 1.5; border-radius: 0.25rem; transition: all .15s ease-in-out; text-decoration: none; }
        .btn-muted { color: #6c757d; background-color: #f8f9fa; border-color: #dee2e6; }
        .btn-muted:hover { color: #495057; background-color: #e2e6ea; border-color: #dae0e5; }
        .btn-primary { color: #fff; background-color: #007bff; border-color: #007bff; font-weight: 600; }
        .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
        
        .auth-card { background-color: #fff3cd; border: 1px solid #ffeeba; padding: 20px; border-radius: 8px; margin-bottom: 20px; color: #856404; }
        .auth-card strong { display: block; margin-bottom: 8px; }
        .url-hint { font-size: 0.8em; opacity: 0.8; display: block; margin-top: 4px; font-family: monospace; }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <img src="/tailscale/tailscale.png" class="logo" alt="Tailscale Logo">
            <h1>Tailscale</h1>
        </div>
        <button class="btn btn-muted" onclick="window.location.reload();">Refresh Status</button>
    </header>
    
    <div class="status-box">
        <?php
            $tailscale_bin = realpath(__DIR__ . '/../tailscale');
            $auth_url = null;
            $status_output = "";

            if (file_exists($tailscale_bin)) {
                // Check if tailscaled is running
                $pid = shell_exec('pgrep tailscaled');
                if (!$pid) {
                    echo "<p style='color: #dc3545; font-weight: bold;'>Warning: tailscaled daemon does not appear to be running.</p>";
                }
                
                $status_output = shell_exec($tailscale_bin . ' status 2>&1');
                
                // Search for authentication URL
                if (preg_match('/https:\/\/login\.tailscale\.com\/a\/[a-zA-Z0-9]+/', $status_output, $matches)) {
                    $auth_url = $matches[0];
                }
            } else {
                $status_output = "Tailscale binary not found at: " . $tailscale_bin;
            }
        ?>

        <?php if ($auth_url): ?>
            <div class="auth-card">
                <strong>Authentication Required</strong>
                <p>Tailscale needs you to log in to add this node:</p>
                <a href="<?php echo $auth_url; ?>" target="_blank" class="btn btn-primary">
                    Login: <?php echo htmlspecialchars($auth_url); ?>
                </a>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <h2 style="margin: 0; font-size: 1.25rem;">Daemon Status</h2>
        </div>
        <pre><?php echo htmlspecialchars($status_output ?: 'No status output (maybe Tailscale is not logged in?)'); ?></pre>
    </div>

    <hr>

    <p>To further configure Tailscale via command line, please SSH into your NAS:</p>
    <pre>
$ cd <?php echo realpath(__DIR__ . '/..'); ?>

$ ./tailscale up
    </pre>
    <p class="info">Or consult the <a href="https://tailscale.com/docs/" target="_blank" style="color: #007bff;">Tailscale documentation</a>.</p>
</body>
</html>
