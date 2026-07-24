@echo off
cd /d "%~dp0"
echo Starting NDBS OCR in background...
wscript //nologo "%~dp0start_ocr_server_silent.vbs"
echo Waiting for models...
powershell -NoProfile -Command "for($i=0;$i -lt 60;$i++){ try { $r = Invoke-WebRequest -UseBasicParsing http://127.0.0.1:8766/health -TimeoutSec 2; if($r.Content -match 'ndbs-recognize'){ Write-Host OK; exit 0 } } catch {} Start-Sleep -Seconds 1 }; exit 1"
if errorlevel 1 (
  echo OCR chua san sang. Thu lai sau vai giay.
) else (
  echo OCR san sang tai http://127.0.0.1:8766
)
pause
