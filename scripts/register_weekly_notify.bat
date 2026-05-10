@echo off
REM Register a scheduled task that runs the weekly notification PHP script every Monday at 07:00
REM Run this file as Administrator.

REM Adjust PHP and project paths below if your XAMPP installation differs
SET PHP_PATH=C:\xampp\php\php.exe
SET SCRIPT_PATH=C:\xampp\htdocs\dti\api\weekly_activities_notify.php

echo Creating scheduled task "DTI Weekly Activities" to run every Monday at 07:00
schtasks /Create /SC WEEKLY /D MON /TN "DTI Weekly Activities" /TR "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" /ST 07:00 /F

if %ERRORLEVEL% EQU 0 (
  echo Scheduled task created successfully.
) else (
  echo Failed to create scheduled task. Check paths and run as Administrator.
)

pause
