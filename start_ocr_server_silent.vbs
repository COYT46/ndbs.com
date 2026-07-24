' NDBS OCR — chạy ẩn, bỏ qua nếu đã healthy
Option Explicit
Dim WshShell, FSO, scriptDir, pythonExe, healthUrl, alreadyUp, cmd, http
Dim candidates, i

Set WshShell = CreateObject("WScript.Shell")
Set FSO = CreateObject("Scripting.FileSystemObject")
scriptDir = FSO.GetParentFolderName(WScript.ScriptFullName)
healthUrl = "http://127.0.0.1:8766/health"

alreadyUp = False
On Error Resume Next
Set http = CreateObject("MSXML2.ServerXMLHTTP.6.0")
If Err.Number <> 0 Then
  Err.Clear
  Set http = CreateObject("Microsoft.XMLHTTP")
End If
If Err.Number = 0 Then
  http.setTimeouts 800, 800, 800, 1500
  http.Open "GET", healthUrl, False
  http.Send
  If Err.Number = 0 Then
    If http.Status = 200 Then
      If InStr(http.responseText, "ndbs-recognize") > 0 Then alreadyUp = True
    End If
  End If
End If
Err.Clear
On Error GoTo 0

If alreadyUp Then WScript.Quit 0

pythonExe = ""
candidates = Array( _
  WshShell.ExpandEnvironmentStrings("%LOCALAPPDATA%") & "\Programs\Python\Python314\python.exe", _
  WshShell.ExpandEnvironmentStrings("%LOCALAPPDATA%") & "\Programs\Python\Python313\python.exe", _
  WshShell.ExpandEnvironmentStrings("%LOCALAPPDATA%") & "\Programs\Python\Python312\python.exe", _
  "C:\Windows\py.exe", _
  "python" _
)
For i = 0 To UBound(candidates)
  If candidates(i) = "python" Then
    pythonExe = "python"
    Exit For
  End If
  If FSO.FileExists(candidates(i)) Then
    pythonExe = candidates(i)
    Exit For
  End If
Next

If pythonExe = "" Then WScript.Quit 1

WshShell.CurrentDirectory = scriptDir
cmd = """" & pythonExe & """ """ & scriptDir & "\recognize_server.py"""
WshShell.Run cmd, 0, False
WScript.Quit 0
