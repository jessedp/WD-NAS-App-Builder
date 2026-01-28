<?php
// Ensure /opt/bin is in PATH for this script context if possible, or use absolute path
$opkg_bin = '/opt/bin/opkg';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Must be post";
    exit;
}

$method = isset($_REQUEST['method']) ? $_REQUEST['method'] : '';
if (empty($method)) {
    // Check POST data if not in query string (JS uses fetch with query string but POST method)
    // The JS fetch code: fetch(`${entwareUrl}?method=${method}&${queryString.toString()}`, { method: 'POST' });
    // So 'method' is in $_GET.
}

$package_name = isset($_REQUEST['package_name']) ? $_REQUEST['package_name'] : '';

// Validation
function validate_package_name($name) {
    if (!preg_match('/^[\w\.\+-]+$/', $name)) { // Fixed regex to allow + and remove weird "
        echo "Invalid package name " . htmlspecialchars($name);
        exit;
    }
    return $name;
}

function exec_opkg($args) {
    global $opkg_bin;
    $cmd = escapeshellcmd($opkg_bin) . ' ' . $args . ' 2>&1';
    
    // Set PATH to include /opt/bin just in case opkg needs it for sub-processes
    putenv("PATH=" . getenv("PATH") . ":/opt/bin:/opt/sbin");
    
    $output = shell_exec($cmd);
    return $output;
}



switch ($method) {
    case 'search':
        $name = validate_package_name($package_name);
        // opkg find name*
        // Escape the argument to prevent injection
        $safe_name = escapeshellarg($name . '*');
        // We construct the command string carefully
        // escapeshellcmd on the binary, but arguments need escaping too.
        // shell_exec takes a string.
        // Better: exec_opkg("find " . $safe_name)
        // But validate_package_name is strict, so it's relatively safe.
        // Python used f"{package_name}*"
        $output = exec_opkg("find " . escapeshellarg($name . '*'));
        echo $output;
        break;

    case 'install':
        $name = validate_package_name($package_name);
        $output = exec_opkg("install " . escapeshellarg($name));
        echo $output;
        echo "\nDone";
        break;

    case 'remove':
        $name = validate_package_name($package_name);
        $output = exec_opkg("remove " . escapeshellarg($name));
        echo $output;
        echo "\nDone";
        break;

    case 'list-installed':
        $output = exec_opkg("list-installed");
        echo $output;
        break;

    default:
        echo "Invalid method " . htmlspecialchars($method);
        break;
}

?>