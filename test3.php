<?php
// Force UTF-8 encoding for proper output
header('Content-Type: text/html; charset=UTF-8');

// Full path to PowerShell (make sure the path is correct for your system)
$powerShellPath = 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

// Output header
echo "<h2>Testing PHP Functions</h2>";

// Test exec() with PowerShell's Get-Process command
echo "<h3>Output from exec() with PowerShell Get-Process:</h3>";
$output_exec = exec($powerShellPath . ' Get-Process');
echo "<pre>" . htmlspecialchars($output_exec) . "</pre>";

// Test shell_exec() with PowerShell's Get-Process command
echo "<h3>Output from shell_exec() with PowerShell Get-Process:</h3>";
$output_shell_exec = shell_exec($powerShellPath . ' Get-Process');
echo "<pre>" . htmlspecialchars($output_shell_exec) . "</pre>";

// Test system() with PowerShell's Get-Process command
echo "<h3>Output from system() with PowerShell Get-Process:</h3>";
echo "<pre>";
system($powerShellPath . ' Get-Process');
echo "</pre>";

// Test exec() with simple dir command (to list files in the current directory)
echo "<h3>Output from exec() with dir command:</h3>";
$output_exec_dir = exec('dir');
echo "<pre>" . htmlspecialchars($output_exec_dir) . "</pre>";

// Test shell_exec() with simple dir command (to list files in the current directory)
echo "<h3>Output from shell_exec() with dir command:</h3>";
$output_shell_exec_dir = shell_exec('dir');
echo "<pre>" . htmlspecialchars($output_shell_exec_dir) . "</pre>";

// Test system() with simple dir command (to list files in the current directory)
echo "<h3>Output from system() with dir command:</h3>";
echo "<pre>";
system('dir');
echo "</pre>";

// Test exec() with invalid command to check for errors
echo "<h3>Output from exec() with invalid command (test fail):</h3>";
$output_exec_fail = exec('non_existent_command');
echo "<pre>" . htmlspecialchars($output_exec_fail) . "</pre>";

?>
