<?php
error_reporting(0);

// web/ is symlinked into /var/www/apps/os5-sysinfo, so resolve through the link
$app_dir     = realpath(dirname(realpath(__FILE__)) . '/..');
$apps_root   = dirname($app_dir);
$conf_dir    = $apps_root . '/os5-sysinfo_conf';
$conf_file   = $conf_dir . '/os5-sysinfo.conf';
$status_file = $conf_dir . '/status.json';
$log_file    = $conf_dir . '/os5-sysinfo.log';
$out_file    = $conf_dir . '/os5-sysinfo.out';
$pid_file    = '/var/run/os5-sysinfo.pid';

$DEFAULTS = [
    'LISTENER' => '1', 'LISTEN_HOST' => '0.0.0.0', 'LISTEN_PORT' => '8085', 'API_KEY' => '',
    'OUTPUT_PATH' => '', 'INTERVAL' => '30', 'INCLUDE_SERIALS' => '0', 'INCLUDE_MODELS' => '1',
    'HEARTBEAT_URL' => '',
];

function load_conf($file, $defaults) {
    $cfg = $defaults;
    foreach (@file($file) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = strtoupper(trim($k));
        if (array_key_exists($k, $defaults)) $cfg[$k] = trim($v);
    }
    return $cfg;
}

function write_conf($file, $cfg) {
    $lines = ["# os5-sysinfo configuration (written by the Configure page)"];
    foreach ($cfg as $k => $v) $lines[] = "$k=$v";
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, implode("\n", $lines) . "\n") === false) return false;
    chmod($tmp, 0600);
    return rename($tmp, $file);
}

function running($pid_file) {
    if (!file_exists($pid_file)) return false;
    $pid = (int) trim(file_get_contents($pid_file));
    return $pid > 0 && file_exists("/proc/$pid");
}

function listener_up($host, $port) {
    $target = ($host === '0.0.0.0' || $host === '') ? '127.0.0.1' : $host;
    $c = @fsockopen($target, (int) $port, $errno, $errstr, 1);
    if (is_resource($c)) { fclose($c); return true; }
    return false;
}

function local_ips() {
    $ips = [];
    $out = shell_exec('ip -4 -o addr 2>/dev/null');
    if ($out && preg_match_all('/\binet (\d+\.\d+\.\d+\.\d+)/', $out, $m)) $ips = $m[1];
    if (!$ips) {
        $out = shell_exec('ifconfig 2>/dev/null');
        if ($out && preg_match_all('/inet (?:addr:)?(\d+\.\d+\.\d+\.\d+)/', $out, $m)) $ips = $m[1];
    }
    return array_values(array_unique(array_filter($ips, fn($ip) => $ip !== '127.0.0.1')));
}

function list_shares() {
    $shares = [];
    foreach (@scandir('/shares') ?: [] as $name) {
        if ($name === '' || $name[0] === '.' || preg_match('/^Volume_/', $name)) continue;
        $path = "/shares/$name";
        if (is_link($path) || is_dir($path)) $shares[] = ['name' => $name, 'target' => @readlink($path) ?: $path];
    }
    return $shares;
}

function tail_lines($path, $n = 80) {
    if (!file_exists($path)) return '(no log yet)';
    $lines = @file($path);
    if ($lines === false) return '(could not read log)';
    return implode('', array_slice($lines, -$n));
}

function validate_path($path, &$err) {
    if ($path === '') return true;
    if (strlen($path) > 255) { $err = 'Path too long.'; return false; }
    if (!preg_match('#^/(mnt/HD/HD_[a-d]2|shares)/[A-Za-z0-9 ._-]+(/[A-Za-z0-9 ._-]+)*\.json$#', $path)) {
        $err = 'Must be an absolute path under /shares/<share>/ or /mnt/HD/HD_a2/, ending in .json, using letters, digits, space . _ - only.';
        return false;
    }
    foreach (explode('/', $path) as $seg) if ($seg === '.' || $seg === '..') { $err = 'No . or .. segments.'; return false; }
    if (strpos($path, '/shares/') === 0) {
        $share = explode('/', substr($path, 8))[0];
        if (!file_exists("/shares/$share") || preg_match('/^Volume_/', $share)) { $err = "No such share: $share"; return false; }
    }
    if (preg_match('#/Nas_Prog/#', $path)) { $err = 'Do not write into Nas_Prog.'; return false; }
    return true;
}

function validate($in, $defaults, &$errors) {
    $cfg = $defaults;
    $errors = [];
    $cfg['LISTENER'] = (isset($in['LISTENER']) && $in['LISTENER'] === '1') ? '1' : '0';
    $cfg['INCLUDE_SERIALS'] = (isset($in['INCLUDE_SERIALS']) && $in['INCLUDE_SERIALS'] === '1') ? '1' : '0';
    $cfg['INCLUDE_MODELS'] = (isset($in['INCLUDE_MODELS']) && $in['INCLUDE_MODELS'] === '1') ? '1' : '0';

    $host = trim($in['LISTEN_HOST'] ?? '');
    if ($host === '') $host = '0.0.0.0';
    if (!preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host) || max(array_map('intval', explode('.', $host))) > 255) {
        $errors['LISTEN_HOST'] = 'Bind address must be 0.0.0.0 or an IPv4 address of this NAS.';
    }
    $cfg['LISTEN_HOST'] = $host;

    $port = (int) trim($in['LISTEN_PORT'] ?? '');
    if ($port < 1024 || $port > 65535 || in_array($port, [8000, 8543], true)) $errors['LISTEN_PORT'] = 'Port must be 1024-65535 and not 8000 or 8543 (used by the WD UI).';
    $cfg['LISTEN_PORT'] = (string) $port;

    $interval = (int) trim($in['INTERVAL'] ?? '');
    if ($interval < 5 || $interval > 3600) $errors['INTERVAL'] = 'Interval must be 5-3600 seconds.';
    $cfg['INTERVAL'] = (string) $interval;

    $key = trim($in['API_KEY'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{0,128}$/', $key)) $errors['API_KEY'] = 'Key may only contain letters, digits, _ and - (max 128).';
    elseif ($key !== '' && strlen($key) < 16) $errors['API_KEY'] = 'Key must be at least 16 characters (or empty for open access).';
    $cfg['API_KEY'] = $key;

    $path = trim($in['OUTPUT_PATH'] ?? '');
    if (!validate_path($path, $perr)) $errors['OUTPUT_PATH'] = $perr;
    $cfg['OUTPUT_PATH'] = $path;

    $hb = trim($in['HEARTBEAT_URL'] ?? '');
    if ($hb !== '' && !preg_match('#^https?://[^\s"\'<>]{1,500}$#', $hb)) $errors['HEARTBEAT_URL'] = 'Heartbeat must be an http(s) URL.';
    $cfg['HEARTBEAT_URL'] = $hb;

    if ($cfg['LISTENER'] === '0' && $cfg['OUTPUT_PATH'] === '') $errors['LISTENER'] = 'Enable the listener or set an output file, otherwise nothing is published.';
    return $cfg;
}

function restart_app($app_dir) {
    $cmd = 'sh ' . escapeshellarg($app_dir . '/stop.sh') . ' ' . escapeshellarg($app_dir) . ' ; '
         . 'sh ' . escapeshellarg($app_dir . '/start.sh') . ' ' . escapeshellarg($app_dir) . ' > /dev/null 2>&1 &';
    shell_exec($cmd);
}

if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $r = ['success' => false, 'message' => 'Unknown action'];
    $cfg = load_conf($conf_file, $DEFAULTS);

    if ($action === 'status') {
        $status = @json_decode(@file_get_contents($status_file), true);
        $public = $cfg;
        $public['has_key'] = $cfg['API_KEY'] !== '';
        unset($public['API_KEY']);
        $r = [
            'success'     => true,
            'running'     => running($pid_file),
            'listener_up' => $cfg['LISTENER'] === '1' && listener_up($cfg['LISTEN_HOST'], $cfg['LISTEN_PORT']),
            'conf'        => $public,
            'status'      => is_array($status) ? $status : null,
            'status_age'  => file_exists($status_file) ? time() - filemtime($status_file) : null,
            'shares'      => list_shares(),
            'local_ips'   => local_ips(),
            'log'         => tail_lines($log_file),
            'out'         => trim(tail_lines($out_file, 20)),
        ];
    } elseif ($action === 'save_config') {
        $new = validate($_POST, $DEFAULTS, $errors);
        // an empty submitted key keeps the existing one; the literal word CLEAR removes it
        if (($_POST['API_KEY'] ?? '') === '') $new['API_KEY'] = $cfg['API_KEY'];
        if (($_POST['API_KEY'] ?? '') === 'CLEAR') { $new['API_KEY'] = ''; unset($errors['API_KEY']); }
        if ($errors) {
            $r = ['success' => false, 'message' => 'Not saved: ' . implode(' ', $errors), 'errors' => $errors];
        } elseif (!write_conf($conf_file, $new)) {
            $r = ['success' => false, 'message' => 'Could not write the config file.'];
        } else {
            restart_app($app_dir);
            $r = ['success' => true, 'message' => 'Saved. Collector restarting; refresh in a few seconds.'];
        }
    } elseif ($action === 'restart') {
        restart_app($app_dir);
        $r = ['success' => true, 'message' => 'Restart issued.'];
    } elseif ($action === 'test_write') {
        $path = trim($_POST['path'] ?? '');
        if ($path === '' || !validate_path($path, $perr)) {
            $r = ['success' => false, 'message' => $perr ?: 'No path given.'];
        } else {
            $dir = dirname($path);
            @mkdir($dir, 0755, true);
            $probe = $path . '.write-test';
            if (@file_put_contents($probe, "{\"test\":true}\n") !== false) {
                @unlink($probe);
                $r = ['success' => true, 'message' => 'Writable: ' . $dir . (file_exists($path) ? ' (file exists, will be overwritten each interval)' : '')];
            } else {
                $r = ['success' => false, 'message' => 'Cannot write to ' . $dir];
            }
        }
    }
    echo json_encode($r);
    exit;
}
?>
<div class="os5-sysinfo-app">
    <style>
        .os5-sysinfo-app { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; text-align: left; color: #212529; box-sizing: border-box; }
        .os5-sysinfo-app * { box-sizing: border-box; }
        .os5-sysinfo-app header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; flex-wrap: wrap; gap: 8px; }
        .os5-sysinfo-app .brand { display: flex; align-items: center; gap: 12px; }
        .os5-sysinfo-app .logo { width: 40px; height: 40px; }
        .os5-sysinfo-app h1 { margin: 0; font-size: 1.6rem; color: #000; }
        .os5-sysinfo-app h2 { font-size: 1.15rem; margin: 0 0 12px 0; border-bottom: 1px solid #f1f1f1; padding-bottom: 8px; }
        .os5-sysinfo-app .card { border: 1px solid #dee2e6; border-radius: 8px; padding: 18px; margin-bottom: 18px; background: #fff; }
        .os5-sysinfo-app .btn { display: inline-block; cursor: pointer; border: 1px solid transparent; padding: 0.375rem 0.75rem; font-size: 0.9rem; border-radius: 0.25rem; color: #000 !important; background: #e9ecef; margin-left: 6px; }
        .os5-sysinfo-app .btn-primary { background: #007bff; border-color: #007bff; }
        .os5-sysinfo-app .btn-success { background: #28a745; border-color: #28a745; color: #fff !important; }
        .os5-sysinfo-app input[type=text], .os5-sysinfo-app input[type=number], .os5-sysinfo-app select { font-family: monospace; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; }
        .os5-sysinfo-app input.wide { width: 100%; }
        .os5-sysinfo-app .grid { display: grid; grid-template-columns: 180px 1fr; gap: 10px 14px; align-items: center; }
        .os5-sysinfo-app .grid label { font-weight: 600; }
        .os5-sysinfo-app .hint { color: #6c757d; font-size: 0.82rem; margin: -4px 0 6px 0; grid-column: 2; }
        .os5-sysinfo-app .muted { color: #6c757d; font-size: 0.85rem; }
        .os5-sysinfo-app .mono { font-family: monospace; }
        .os5-sysinfo-app .pill { display: inline-block; padding: 3px 9px; border-radius: 4px; font-weight: bold; font-size: 0.85rem; margin-right: 6px; }
        .os5-sysinfo-app .pill-ok { background: #d4edda; color: #155724; }
        .os5-sysinfo-app .pill-warn { background: #fff3cd; color: #856404; }
        .os5-sysinfo-app .pill-bad { background: #f8d7da; color: #721c24; }
        .os5-sysinfo-app .pill-off { background: #e9ecef; color: #495057; }
        .os5-sysinfo-app pre, .os5-sysinfo-app .log { background: #212529; color: #f8f9fa; padding: 10px; border-radius: 4px; max-height: 320px; overflow: auto; font-family: monospace; font-size: 0.8rem; white-space: pre-wrap; margin: 0; }
        .os5-sysinfo-app .stats { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 10px; }
        .os5-sysinfo-app .stat b { display: block; font-size: 1.1rem; }
        .os5-sysinfo-app .stat span { color: #6c757d; font-size: 0.8rem; }
        .os5-sysinfo-app .err { color: #721c24; font-size: 0.82rem; }
    </style>

    <header>
        <div class="brand">
            <img src="/apps/os5-sysinfo/os5-sysinfo.svg" class="logo" alt="">
            <h1>OS5 SysInfo</h1>
        </div>
        <div>
            <span id="si-run" class="pill pill-off">…</span>
            <span id="si-listen" class="pill pill-off">…</span>
            <button class="btn" onclick="siRefresh()">Refresh</button>
            <button class="btn btn-primary" onclick="siRestart()">Restart</button>
        </div>
    </header>

    <div class="card">
        <h2>Status</h2>
        <div id="si-summary" class="muted">Loading…</div>
        <div class="stats" id="si-stats"></div>
        <p class="muted" style="margin-top:12px">Endpoint: <span class="mono" id="si-url">…</span><br>
        Use the NAS <b>IP address</b>, not its name: the WD UI proxy rejects unknown host names, and dashboards should not depend on name resolution.</p>
    </div>

    <div class="card">
        <h2>Configuration</h2>
        <div class="grid">
            <label>HTTP listener</label>
            <div><input type="checkbox" id="f-LISTENER"> <span class="muted">serve JSON at /status and /health on the port below</span></div>

            <label>Bind address</label>
            <div><select id="f-LISTEN_HOST"></select> <input type="text" id="f-LISTEN_HOST_other" placeholder="or type an IPv4" style="width:180px"></div>
            <div class="hint">0.0.0.0 = every interface. Pick the Tailscale address to expose it only over Tailscale.</div>

            <label>Port</label>
            <div><input type="number" id="f-LISTEN_PORT" min="1024" max="65535" style="width:120px"> <span class="muted">the WD UI always advertises 8085 for this app, even if you change it here</span></div>

            <label>API key</label>
            <div><input type="text" id="f-API_KEY" class="wide" placeholder="leave empty to keep the current key; type CLEAR to remove it"></div>
            <div class="hint"><span id="si-keystate"></span> <a href="#" onclick="siGenKey();return false;">Generate a random key</a>. Consumers send it as <span class="mono">X-Api-Key: …</span> or <span class="mono">?key=…</span></div>

            <label>Output file</label>
            <div><select id="f-share"><option value="">— pick a share —</option></select> <button class="btn" onclick="siTestWrite()">Test write</button></div>
            <div style="grid-column:2"><input type="text" id="f-OUTPUT_PATH" class="wide" placeholder="empty = off, e.g. /shares/Public/os5-sysinfo/status.json"></div>
            <div class="hint">A copy of the JSON rewritten every interval, for readers on SMB/NFS/copyparty. Anyone with access to that share can read it.</div>

            <label>Interval (s)</label>
            <div><input type="number" id="f-INTERVAL" min="5" max="3600" style="width:120px"></div>

            <label>Disk details</label>
            <div><input type="checkbox" id="f-INCLUDE_MODELS"> model names &nbsp; <input type="checkbox" id="f-INCLUDE_SERIALS"> serial numbers</div>

            <label>Heartbeat URL</label>
            <div><input type="text" id="f-HEARTBEAT_URL" class="wide" placeholder="optional, e.g. an Uptime Kuma Push monitor URL"></div>
            <div class="hint">Fetched after every successful collection with <span class="mono">?status=up&amp;msg=&lt;health&gt;&amp;ping=&lt;ms&gt;</span> appended.</div>
        </div>
        <div id="si-errors" class="err"></div>
        <div style="text-align:right; margin-top:12px"><button class="btn btn-success" onclick="siSave()">Save &amp; restart</button></div>
    </div>

    <div class="card">
        <h2>Homepage widget</h2>
        <p class="muted">Paste into Homepage's <span class="mono">services.yaml</span>. Field names match the wd-os5-exporter project, so existing widgets work unchanged.</p>
        <pre id="si-homepage">…</pre>
    </div>

    <div class="card">
        <h2>Latest JSON <span class="muted mono"><?php echo htmlspecialchars($status_file); ?></span></h2>
        <pre id="si-json">…</pre>
    </div>

    <div class="card">
        <h2>Log <span class="muted mono"><?php echo htmlspecialchars($log_file); ?></span></h2>
        <div id="si-log" class="log">Loading…</div>
        <div id="si-out" class="log" style="margin-top:8px; display:none"></div>
    </div>

    <script>
    // Wrapped in a closure: this page is injected into the WD UI's own window, so nothing here
    // may leak into the global scope (a global `$` or `esc` breaks the dashboard's jQuery).
    (function () {
        var siUrl = "/apps/os5-sysinfo/index.php";
        var siConf = null;

        async function siApi(params) {
            let fd = new FormData();
            for (let k in params) fd.append(k, params[k]);
            try {
                let res = await fetch(siUrl, { method: 'POST', body: fd });
                return await res.json();
            } catch (e) { return { success: false, message: e.message }; }
        }
        function siEsc(s) { return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
        function el(id) { return document.getElementById(id); }
        function nasHost() { return (location.hostname || '').replace(/^\[|\]$/g, ''); }

        function endpointUrl(conf) {
            let host = (conf.LISTEN_HOST && conf.LISTEN_HOST !== '0.0.0.0') ? conf.LISTEN_HOST : nasHost();
            return 'http://' + host + ':' + conf.LISTEN_PORT + '/status';
        }

        function homepageYaml(conf) {
            let url = endpointUrl(conf);
            let y = "- Storage:\n    - " + (siStatusDoc && siStatusDoc.system && siStatusDoc.system.hostname ? siStatusDoc.system.hostname : "My Cloud") + ":\n" +
                    "        icon: wd.png\n        description: NAS health\n        widget:\n          type: customapi\n          url: " + url + "\n";
            if (conf.has_key) y += "          headers:\n            X-Api-Key: <your key>\n";
            y += "          mappings:\n            - field: health_display\n              label: Health\n            - field: storage_display\n              label: Storage\n" +
                 "            - field: temperature_display\n              label: Temps\n            - field: cpu_display\n              label: CPU\n            - field: ram_display\n              label: RAM\n";
            return y;
        }

        var siStatusDoc = null;
        function fillForm(conf, ips) {
            el('f-LISTENER').checked = conf.LISTENER === '1';
            let sel = el('f-LISTEN_HOST'); sel.innerHTML = '';
            let opts = ['0.0.0.0', '127.0.0.1'].concat(ips);
            if (opts.indexOf(conf.LISTEN_HOST) < 0) opts.push(conf.LISTEN_HOST);
            opts.forEach(ip => { let o = document.createElement('option'); o.value = ip; o.textContent = ip === '0.0.0.0' ? '0.0.0.0 (all interfaces)' : ip; sel.appendChild(o); });
            sel.value = conf.LISTEN_HOST; el('f-LISTEN_HOST_other').value = '';
            el('f-LISTEN_PORT').value = conf.LISTEN_PORT;
            el('f-API_KEY').value = '';
            el('si-keystate').textContent = conf.has_key ? 'A key is set.' : 'No key set: anyone who can reach the port can read the JSON.';
            el('f-OUTPUT_PATH').value = conf.OUTPUT_PATH;
            el('f-INTERVAL').value = conf.INTERVAL;
            el('f-INCLUDE_MODELS').checked = conf.INCLUDE_MODELS === '1';
            el('f-INCLUDE_SERIALS').checked = conf.INCLUDE_SERIALS === '1';
            el('f-HEARTBEAT_URL').value = conf.HEARTBEAT_URL;
        }

        function fillShares(shares) {
            let sel = el('f-share');
            let cur = sel.value;
            sel.innerHTML = '<option value="">— pick a share —</option>';
            shares.forEach(s => { let o = document.createElement('option'); o.value = s.name; o.textContent = s.name + '  (' + s.target + ')'; sel.appendChild(o); });
            sel.value = cur;
        }
        document.addEventListener('change', e => {
            if (e.target && e.target.id === 'f-share' && e.target.value) el('f-OUTPUT_PATH').value = '/shares/' + e.target.value + '/os5-sysinfo/status.json';
        });

        async function siRefresh() {
            const d = await siApi({ action: 'status' });
            if (!d.success) return;
            siConf = d.conf; siStatusDoc = d.status;
            const run = el('si-run'); run.textContent = d.running ? 'running' : 'stopped'; run.className = 'pill ' + (d.running ? 'pill-ok' : 'pill-bad');
            const li = el('si-listen');
            if (d.conf.LISTENER !== '1') { li.textContent = 'listener off'; li.className = 'pill pill-off'; }
            else { li.textContent = d.listener_up ? 'listening :' + d.conf.LISTEN_PORT : 'port ' + d.conf.LISTEN_PORT + ' not answering'; li.className = 'pill ' + (d.listener_up ? 'pill-ok' : 'pill-warn'); }
            el('si-url').textContent = d.conf.LISTENER === '1' ? endpointUrl(d.conf) + (d.conf.has_key ? '  (key required)' : '') : (d.conf.OUTPUT_PATH || 'nothing published');

            if (d.status) {
                let s = d.status, sev = s.health_severity || 'unknown';
                let cls = sev === 'ok' ? 'pill-ok' : (sev === 'critical' ? 'pill-bad' : 'pill-warn');
                el('si-summary').innerHTML = '<span class="pill ' + cls + '">' + siEsc(s.health_icon || '') + ' ' + siEsc(s.health_display || s.health) + '</span> ' +
                    'updated ' + siEsc(s.updated) + ' (' + d.status_age + 's ago)' + (s.error ? ' <span class="err">error: ' + siEsc(s.error) + '</span>' : '') +
                    (s.system && s.system.output_error ? ' <span class="err">output file: ' + siEsc(s.system.output_error) + '</span>' : '');
                let st = [['Storage', s.storage_display], ['CPU', s.cpu_display], ['RAM', s.ram_display], ['Temps', s.temperature_display], ['SMART', s.smart_display],
                          ['Disks', (s.disks || []).length], ['Uptime', s.system ? s.system.uptime_display : ''], ['Model', s.system ? s.system.model + ' / ' + s.system.firmware : '']];
                el('si-stats').innerHTML = st.map(x => '<div class="stat"><b>' + siEsc(x[1] == null ? 'n/a' : x[1]) + '</b><span>' + x[0] + '</span></div>').join('');
                el('si-json').textContent = JSON.stringify(s, null, 2);
            } else {
                el('si-summary').textContent = d.running ? 'No data yet, first collection pending…' : 'Collector not running.';
                el('si-stats').innerHTML = ''; el('si-json').textContent = '(no status.json yet)';
            }
            if (document.activeElement === document.body || !document.activeElement || document.activeElement.tagName === 'BUTTON') fillForm(d.conf, d.local_ips);
            fillShares(d.shares);
            el('si-homepage').textContent = homepageYaml(d.conf);
            const log = el('si-log'); log.textContent = d.log; log.scrollTop = log.scrollHeight;
            const out = el('si-out'); if (d.out) { out.style.display = 'block'; out.textContent = 'process output:\n' + d.out; } else out.style.display = 'none';
        }

        function siGenKey() {
            let a = new Uint8Array(16); crypto.getRandomValues(a);
            el('f-API_KEY').value = Array.from(a, b => b.toString(16).padStart(2, '0')).join('');
        }

        async function siSave() {
            let host = el('f-LISTEN_HOST_other').value.trim() || el('f-LISTEN_HOST').value;
            const d = await siApi({ action: 'save_config',
                LISTENER: el('f-LISTENER').checked ? '1' : '0', LISTEN_HOST: host, LISTEN_PORT: el('f-LISTEN_PORT').value,
                API_KEY: el('f-API_KEY').value.trim(), OUTPUT_PATH: el('f-OUTPUT_PATH').value.trim(), INTERVAL: el('f-INTERVAL').value,
                INCLUDE_MODELS: el('f-INCLUDE_MODELS').checked ? '1' : '0', INCLUDE_SERIALS: el('f-INCLUDE_SERIALS').checked ? '1' : '0',
                HEARTBEAT_URL: el('f-HEARTBEAT_URL').value.trim() });
            el('si-errors').textContent = d.success ? '' : d.message;
            alert(d.message);
            if (d.success) { el('f-API_KEY').value = ''; setTimeout(siRefresh, 4000); }
        }

        async function siTestWrite() {
            const d = await siApi({ action: 'test_write', path: el('f-OUTPUT_PATH').value.trim() });
            alert(d.message);
        }

        async function siRestart() {
            const d = await siApi({ action: 'restart' });
            alert(d.message);
            setTimeout(siRefresh, 4000);
        }

        setTimeout(siRefresh, 100);
        setInterval(siRefresh, 30000);
        window.siRefresh = siRefresh; window.siRestart = siRestart; window.siSave = siSave;
        window.siTestWrite = siTestWrite; window.siGenKey = siGenKey;
    })();
    </script>
</div>
