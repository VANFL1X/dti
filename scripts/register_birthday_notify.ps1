<#
PowerShell helper to register a scheduled task that runs birthday greetings daily.
Run this in an elevated PowerShell prompt.
#>

$php = 'C:\xampp\php\php.exe'
$script = 'C:\xampp\htdocs\dti\api\birthday_greetings_notify.php'
$taskName = 'DTI Birthday Greetings'

Write-Output "Creating scheduled task '$taskName' to run daily at 07:00"

$action = New-ScheduledTaskAction -Execute $php -Argument $script
$trigger = New-ScheduledTaskTrigger -Daily -At 7am

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -RunLevel Highest -Force

Write-Output "Done. Use Get-ScheduledTask -TaskName '$taskName' to verify."
