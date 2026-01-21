<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <title>copyparty - Login Required</title>
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
<script src="/web/jquery/js/jquery-3.5.1.min.js"></script>
<script>
    // Verify authentication with the NAS backend
    $.ajax({
        type: "PUT",
        url: '/nas/v1/auth',
        statusCode: {
            403: function() {
                $('body').html('<h2 style="text-align:center; margin-top:40px;">Please login to the NAS to continue</h2>');
            },
            200: function() {
                window.location.href = 'http://' + window.location.hostname + ':3923/';
            }
        }
    });
</script>
<!DOCTYPE html>
<html>
<head>
    <title>copyparty</title>
</head>
<body>
    <p>Redirecting to copyparty...</p>
</body>
</html>
