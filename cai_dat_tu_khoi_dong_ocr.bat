@echo off
chcp 65001 >nul
REM =============================================================================
REM  NDBS - Cai dat OCR tu khoi dong Windows
REM =============================================================================
REM
REM  MUC DICH
REM  - Tao shortcut trong thu muc Startup cua Windows
REM  - Moi lan dang nhap Windows, OCR server tu chay an (khong can IDE)
REM  - Chi can bat XAMPP + vao web la nhan dien ~1 giay
REM
REM  HUONG DAN CAI (MOI MAY CHI CAN CHAY 1 LAN)
REM  1. Cai Python 3 (them vao PATH khi cai)
REM  2. Cai thu vien:
REM       pip install ultralytics opencv-python numpy easyocr
REM  3. Dam bao trong thu muc project co:
REM       - recognize.py
REM       - recognize_server.py
REM       - start_ocr_server_silent.vbs
REM       - best.pt
REM       - bestRead.pt (hoac bestread.pt)
REM  4. Bat XAMPP (Apache + MySQL)
REM  5. Double-click file nay: cai_dat_tu_khoi_dong_ocr.bat
REM  6. Doi thong bao "OCR SAN SANG" (lan dau nap model ~15-30s)
REM
REM  SAU KHI CAI
REM  - Hang ngay: chi bat XAMPP roi vao web
REM  - Khong can mo Cursor/IDE
REM  - Copy code sang may khac: phai chay lai file nay tren may do
REM
REM  GO CAI (neu can)
REM  - Xoa shortcut:
REM    %%APPDATA%%\Microsoft\Windows\Start Menu\Programs\Startup\NDBS_OCR_Server.lnk
REM  - Hoac: Win+R -> shell:startup -> xoa NDBS_OCR_Server.lnk
REM
REM  KIEM TRA OCR DANG CHAY
REM  - Mo trinh duyet: http://127.0.0.1:8766/health
REM  - Thay {"ok": true, "service": "ndbs-recognize"} la OK
REM
REM  LUU Y
REM  - Port mac dinh: 8766 (doi trong recognize_server.py / .env neu can)
REM  - Neu Python khong trong PATH, set PYTHON_PATH trong file .env
REM  - Log (neu loi): storage\logs\ocr_server.out.log / ocr_server.err.log
REM
REM =============================================================================

REM Cai dat: OCR tu chay moi khi dang nhap Windows (khong can IDE)
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "SRC=%~dp0start_ocr_server_silent.vbs"
set "LNK=%STARTUP%\NDBS_OCR_Server.lnk"

echo.
echo ========================================
echo   NDBS - Cai dat OCR tu khoi dong
echo ========================================
echo.
echo [Huong dan tom tat]
echo  1. Da cai Python + pip install ultralytics opencv-python numpy
echo  2. Co best.pt va bestRead.pt trong thu muc project
echo  3. Chay file nay 1 lan tren MOI may
echo  4. Sau do chi can bat XAMPP + vao web
echo.
echo Dang tao shortcut Startup...

if not exist "%SRC%" (
  echo [LOI] Khong tim thay: %SRC%
  echo Hay chay file nay trong thu muc ndbs.com
  pause
  exit /b 1
)

powershell -NoProfile -Command ^
  "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut('%LNK%'); $s.TargetPath = 'wscript.exe'; $s.Arguments = '//nologo \"\"\"%SRC%\"\"\"'; $s.WorkingDirectory = '%~dp0'; $s.WindowStyle = 7; $s.Save()"

echo.
echo Da cai dat tu khoi dong OCR.
echo Shortcut: %LNK%
echo.
echo Dang khoi dong OCR ngay bay gio...
wscript //nologo "%SRC%"
echo Doi nap model YOLO (co the 15-30 giay)...
powershell -NoProfile -Command "for($i=0;$i -lt 60;$i++){ try { $r = Invoke-WebRequest -UseBasicParsing http://127.0.0.1:8766/health -TimeoutSec 2; if($r.Content -match 'ndbs-recognize'){ Write-Host 'OCR SAN SANG'; exit 0 } } catch {} Start-Sleep -Seconds 1 }; Write-Host 'OCR chua san sang - xem storage/logs'"
echo.
echo Tu gio chi can bat XAMPP + vao web. Nhan dien se nhanh (~1s).
echo May khac: copy project roi chay lai file nay 1 lan.
pause
