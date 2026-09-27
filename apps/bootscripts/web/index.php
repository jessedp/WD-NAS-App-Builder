<?php
error_reporting(0);

// Paths (web/ is symlinked into /var/www/apps/bootscripts, so resolve through the link)
$app_dir    = realpath(dirname(realpath(__FILE__)) . '/..');
$apps_root  = dirname($app_dir);
$conf_dir   = $apps_root . '/bootscripts_conf';
$scripts_d  = $conf_dir . '/scripts.d';
$examples_d = $conf_dir . '/examples';
$log_file   = $conf_dir . '/bootscripts.log';
$keys_file  = $conf_dir . '/ssh/authorized_keys';
$runner     = $app_dir . '/run_scripts.sh';

function tail_lines($path, $n = 80) {
    if (!file_exists($path)) return "(no log yet)";
    $lines = file($path);
    if ($lines === false) return "(could not read log)";
    return implode("", array_slice($lines, -$n));
}

function list_scripts($dir) {
    $out = [];
    foreach (glob($dir . '/*.sh') ?: [] as $f) {
        $out[] = [
            'name'  => basename($f),
            'size'  => filesize($f),
            'mtime' => date('Y-m-d H:i', filemtime($f)),
        ];
    }
    return $out;
}

function safe_name($name) {
    // a file name inside scripts.d: no paths, must end in .sh
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.sh$/', $name) ? $name : null;
}

function running($conf_dir) {
    $pidfile = $conf_dir . '/.run.lock/pid';
    if (!file_exists($pidfile)) return false;
    $pid = (int) trim(file_get_contents($pidfile));
    return $pid > 0 && file_exists("/proc/$pid");
}

if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $r = ['success' => false, 'message' => 'Unknown action'];

    if ($action === 'status') {
        $r = [
            'success'  => true,
            'running'  => running($conf_dir),
            'scripts'  => list_scripts($scripts_d),
            'examples' => list_scripts($examples_d),
            'log'      => tail_lines($log_file),
            'keys'     => file_exists($keys_file) ? file_get_contents($keys_file) : '',
        ];
    } elseif ($action === 'run') {
        shell_exec('sh ' . escapeshellarg($runner) . ' ' . escapeshellarg($conf_dir) . ' web > /dev/null 2>&1 &');
        $r = ['success' => true, 'message' => 'Run started.'];
    } elseif ($action === 'load') {
        $name = safe_name($_REQUEST['name'] ?? '');
        $from = ($_REQUEST['from'] ?? '') === 'examples' ? $examples_d : $scripts_d;
        if ($name && file_exists("$from/$name")) {
            $r = ['success' => true, 'name' => $name, 'content' => file_get_contents("$from/$name")];
        } else {
            $r['message'] = 'No such script.';
        }
    } elseif ($action === 'save') {
        $name = safe_name($_POST['name'] ?? '');
        if (!$name) {
            $r['message'] = 'Name must look like 10-something.sh (letters, digits, . _ - only).';
        } else {
            @mkdir($scripts_d, 0755, true);
            $content = str_replace("\r\n", "\n", $_POST['content'] ?? '');
            if (file_put_contents("$scripts_d/$name", $content) === false) {
                $r['message'] = 'Could not write the script.';
            } else {
                chmod("$scripts_d/$name", 0755);
                $r = ['success' => true, 'message' => "Saved scripts.d/$name"];
            }
        }
    } elseif ($action === 'delete') {
        $name = safe_name($_POST['name'] ?? '');
        if ($name && file_exists("$scripts_d/$name") && unlink("$scripts_d/$name")) {
            $r = ['success' => true, 'message' => "Deleted scripts.d/$name"];
        } else {
            $r['message'] = 'Could not delete the script.';
        }
    } elseif ($action === 'save_keys') {
        @mkdir(dirname($keys_file), 0700, true);
        $content = str_replace("\r\n", "\n", $_POST['content'] ?? '');
        if (file_put_contents($keys_file, $content) === false) {
            $r['message'] = 'Could not write authorized_keys.';
        } else {
            chmod($keys_file, 0600);
            // Apply straight away: this runs scripts.d, which installs the keys into root's ~/.ssh
            shell_exec('sh ' . escapeshellarg($runner) . ' ' . escapeshellarg($conf_dir) . ' web > /dev/null 2>&1 &');
            $r = ['success' => true, 'message' => 'Saved and installed. These keys will be restored on every boot.'];
        }
    }

    echo json_encode($r);
    exit;
}
?>
<div class="bootscripts-app">
    <style>
        .bootscripts-app { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; text-align: left; color: #212529; box-sizing: border-box; }
        .bootscripts-app * { box-sizing: border-box; }
        .bootscripts-app header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; }
        .bootscripts-app .brand { display: flex; align-items: center; gap: 12px; }
        .bootscripts-app .logo { width: 40px; height: 40px; }
        .bootscripts-app h1 { margin: 0; font-size: 1.6rem; color: #000; }
        .bootscripts-app h2 { font-size: 1.15rem; margin: 0 0 12px 0; border-bottom: 1px solid #f1f1f1; padding-bottom: 8px; }
        .bootscripts-app .card { border: 1px solid #dee2e6; border-radius: 8px; padding: 18px; margin-bottom: 18px; background: #fff; }
        .bootscripts-app .btn { display: inline-block; cursor: pointer; border: 1px solid transparent; padding: 0.375rem 0.75rem; font-size: 0.9rem; border-radius: 0.25rem; color: #000 !important; background: #e9ecef; margin-left: 6px; }
        .bootscripts-app .btn-primary { background: #007bff; border-color: #007bff; }
        .bootscripts-app .btn-success { background: #28a745; border-color: #28a745; color: #fff !important; }
        .bootscripts-app .btn-danger { background: #dc3545; border-color: #dc3545; }
        .bootscripts-app textarea { width: 100%; font-family: monospace; font-size: 0.85rem; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; resize: vertical; }
        .bootscripts-app input[type=text] { font-family: monospace; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; width: 260px; }
        .bootscripts-app .log { background: #212529; color: #f8f9fa; padding: 10px; border-radius: 4px; height: 260px; overflow-y: auto; font-family: monospace; font-size: 0.8rem; white-space: pre-wrap; }
        .bootscripts-app table { border-collapse: collapse; width: 100%; font-size: 0.9rem; }
        .bootscripts-app td, .bootscripts-app th { padding: 6px 8px; border-bottom: 1px solid #f1f1f1; text-align: left; }
        .bootscripts-app td.mono { font-family: monospace; }
        .bootscripts-app a { color: #007bff; text-decoration: none; cursor: pointer; }
        .bootscripts-app .muted { color: #6c757d; font-size: 0.85rem; }
        .bootscripts-app .pill { display: inline-block; padding: 3px 9px; border-radius: 4px; font-weight: bold; font-size: 0.85rem; }
        .bootscripts-app .pill-run { background: #fff3cd; color: #856404; }
        .bootscripts-app .pill-idle { background: #d4edda; color: #155724; }
        .bootscripts-app .row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    </style>

    <header>
        <div class="brand">
            <img src="/apps/bootscripts/bootscripts.svg" class="logo" alt="">
            <h1>Boot Scripts</h1>
        </div>
        <div>
            <span id="bs-state" class="pill pill-idle">idle</span>
            <button class="btn" onclick="bsRefresh()">Refresh</button>
            <button class="btn btn-success" onclick="bsRun()">Run now</button>
        </div>
    </header>

    <div class="card">
        <h2>Scripts in <span class="mono"><?php echo htmlspecialchars($scripts_d); ?></span></h2>
        <p class="muted">Every <code>*.sh</code> here runs as root, in name order, on each boot while this app is enabled, when you press Enable, and when you press Run now. One failing script does not stop the others; a script running longer than 5 minutes is killed.</p>
        <table id="bs-scripts"><tr><td class="muted">Loading…</td></tr></table>
        <p class="muted" style="margin-top:12px">Examples (click to open in the editor, then save under a name to enable): <span id="bs-examples"></span></p>
    </div>

    <div class="card">
        <h2>Editor</h2>
        <div class="row" style="margin-bottom:8px">
            <label>scripts.d/ <input type="text" id="bs-name" placeholder="10-something.sh"></label>
            <div>
                <button class="btn btn-primary" onclick="bsSave()">Save</button>
                <button class="btn btn-danger" onclick="bsDelete()">Delete</button>
            </div>
        </div>
        <textarea id="bs-editor" rows="14" spellcheck="false" placeholder="#!/bin/sh&#10;# your script"></textarea>
    </div>

    <div class="card">
        <h2>SSH keys for root</h2>
        <p class="muted">One public key per line. Make sure every key you want is listed, then press <b>Save keys</b>: they are installed into root's <code>~/.ssh/authorized_keys</code> right away and restored on every boot by <code>10-ssh-authorized-keys.sh</code>. The box is pre-filled with the keys currently on the NAS (persistent file merged with whatever was live), so a fresh install shows only keys that were already there.</p>
        <textarea id="bs-keys" rows="6" spellcheck="false" placeholder="ssh-ed25519 AAAA... you@laptop"></textarea>
        <div style="text-align:right; margin-top:8px"><button class="btn btn-primary" onclick="bsSaveKeys()">Save keys</button></div>
    </div>

    <div class="card">
        <h2>Log <span class="muted mono"><?php echo htmlspecialchars($log_file); ?></span></h2>
        <div id="bs-log" class="log">Loading…</div>
    </div>

    <script>
        var bsUrl = "/apps/bootscripts/index.php";

        async function bsApi(params) {
            let fd = new FormData();
            for (let k in params) fd.append(k, params[k]);
            try {
                let res = await fetch(bsUrl, { method: 'POST', body: fd });
                return await res.json();
            } catch (e) {
                return { success: false, message: e.message };
            }
        }

        function esc(s) { return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

        async function bsRefresh() {
            const d = await bsApi({ action: 'status' });
            if (!d.success) return;
            const st = document.getElementById('bs-state');
            st.textContent = d.running ? 'running' : 'idle';
            st.className = 'pill ' + (d.running ? 'pill-run' : 'pill-idle');

            let rows = '<tr><th>Script</th><th>Size</th><th>Modified</th></tr>';
            if (d.scripts.length === 0) rows += '<tr><td colspan="3" class="muted">No scripts yet. Open an example below or write one in the editor.</td></tr>';
            d.scripts.forEach(s => {
                rows += '<tr><td class="mono"><a onclick="bsLoad(\'' + esc(s.name) + '\', \'scripts\')">' + esc(s.name) + '</a></td><td>' + s.size + ' B</td><td>' + esc(s.mtime) + '</td></tr>';
            });
            document.getElementById('bs-scripts').innerHTML = rows;

            document.getElementById('bs-examples').innerHTML = d.examples.map(s =>
                '<a class="mono" onclick="bsLoad(\'' + esc(s.name) + '\', \'examples\')">' + esc(s.name) + '</a>').join(', ') || '(none)';

            document.getElementById('bs-log').textContent = d.log;
            const log = document.getElementById('bs-log'); log.scrollTop = log.scrollHeight;

            if (document.activeElement !== document.getElementById('bs-keys')) {
                document.getElementById('bs-keys').value = d.keys;
            }
            if (d.running) setTimeout(bsRefresh, 3000);
        }

        async function bsLoad(name, from) {
            const d = await bsApi({ action: 'load', name: name, from: from });
            if (!d.success) { alert(d.message); return; }
            document.getElementById('bs-name').value = d.name;
            document.getElementById('bs-editor').value = d.content;
            document.getElementById('bs-editor').focus();
        }

        async function bsSave() {
            const name = document.getElementById('bs-name').value.trim();
            const d = await bsApi({ action: 'save', name: name, content: document.getElementById('bs-editor').value });
            alert(d.message);
            bsRefresh();
        }

        async function bsDelete() {
            const name = document.getElementById('bs-name').value.trim();
            if (!name || !confirm('Delete scripts.d/' + name + '?')) return;
            const d = await bsApi({ action: 'delete', name: name });
            alert(d.message);
            if (d.success) { document.getElementById('bs-name').value = ''; document.getElementById('bs-editor').value = ''; }
            bsRefresh();
        }

        async function bsSaveKeys() {
            const d = await bsApi({ action: 'save_keys', content: document.getElementById('bs-keys').value });
            alert(d.message);
            setTimeout(bsRefresh, 1500);
        }

        async function bsRun() {
            const d = await bsApi({ action: 'run' });
            if (!d.success) alert(d.message);
            setTimeout(bsRefresh, 1500);
        }

        setTimeout(bsRefresh, 100);
    </script>
</div>
