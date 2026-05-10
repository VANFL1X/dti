@echo off
REM Register a scheduled task that runs the birthday greetings script daily at 07:00
REM Run this file as Administrator.

REM Adjust PHP and project paths below if your XAMPP installation differs
SET PHP_PATH=C:\xampp\php\php.exe
SET SCRIPT_PATH=C:\xampp\htdocs\dti\api\birthday_greetings_notify.php

echo Creating scheduled task "DTI Birthday Greetings" to run daily at 07:00
schtasks /Create /SC DAILY /TN "DTI Birthday Greetings" /TR "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" /ST 07:00 /F

if %ERRORLEVEL% EQU 0 (
  echo Scheduled task created successfully.
) else (
  echo Failed to create scheduled task. Check paths and run as Administrator.
)

pause
