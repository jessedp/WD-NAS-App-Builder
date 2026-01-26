<?php
session_start();

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Security check
if (!isset($_SESSION['username'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Rclone - Login Required</title>
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
                <img src="logo.png" class="logo" alt="Rclone Logo" onerror="this.style.display=\'none\'">
                <div>
                    <h1>Rclone Dashboard</h1>
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

$app_dir = realpath(__DIR__ . '/..');
$rclone_bin = $app_dir . '/rclone';
$version_output = "Rclone binary not found at $rclone_bin";
if (file_exists($rclone_bin)) {
    $version_output = shell_exec($rclone_bin . ' version 2>&1');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Rclone Dashboard</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; text-align: left; max-width: 900px; margin: 0 auto; line-height: 1.5; color: #212529; background-color: #f8f9fa; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #dee2e6; }
        
        header { border-bottom: 1px solid #dee2e6; padding-bottom: 20px; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; }
        .brand { display: flex; align-items: center; gap: 15px; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        h1 { margin: 0; font-size: 1.5rem; color: #212529; }
        
        pre { background: #212529; color: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px; white-space: pre-wrap; word-break: break-all; }
        
        .section { margin-bottom: 40px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
    <script src="/web/jquery/js/jquery-3.5.1.min.js"></script>
    <script>
        // Verify authentication with the NAS backend
        $.ajax({
            type: "PUT",
            url: '/nas/v1/auth',
            statusCode: {
                403: function() {
                    location.reload();
                }
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">
                <img src="logo.png" class="logo" alt="Rclone Logo" onerror="this.style.display='none'">
                <div>
                    <h1>Rclone Dashboard</h1>
                </div>
            </div>
        </header>

        <div class="section">
            <h2>Current Version</h2>
            <pre><?php echo htmlspecialchars($version_output); ?></pre>
        </div>

        <div class="section">
            <h2>Configuration</h2>
            <p>To configure Rclone, you must access your NAS via SSH.</p>
            <p>Once logged in via SSH, run the following command to start the interactive configuration wizard:</p>
            <pre>$ rclone config</pre>
            <p>
                For more information, please visit the <a href="https://rclone.org/docs/" target="_blank">official Rclone documentation</a>.
            </p>
        </div>
    </div>
</body>
</html>