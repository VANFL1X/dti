<#
PowerShell helper to register a scheduled task that runs the weekly notifier.
Run this in an elevated PowerShell prompt.
#>

$php = 'C:\xampp\php\php.exe'
$script = 'C:\xampp\htdocs\dti\api\weekly_activities_notify.php'
$taskName = 'DTI Weekly Activities'

Write-Output "Creating scheduled task '$taskName' to run every Monday at 07:00"

$action = New-ScheduledTaskAction -Execute $php -Argument $script
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Monday -At 7am

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -RunLevel Highest -Force

Write-Output "Done. Use Get-ScheduledTask -TaskName '$taskName' to verify."
