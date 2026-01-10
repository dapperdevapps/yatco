# YATCO Daily Sync Diagnostic Script for Windows PowerShell
# Run: powershell -ExecutionPolicy Bypass -File diagnose-sync.ps1

Write-Host "=== YATCO Daily Sync Diagnostic ===" -ForegroundColor Cyan
Write-Host ""

# Find WordPress root (assumes script is in plugin directory)
$wpRoot = $PSScriptRoot
while (-not (Test-Path "$wpRoot\wp-config.php")) {
    $parent = Split-Path -Parent $wpRoot
    if ($parent -eq $wpRoot) {
        Write-Host "ERROR: Cannot find WordPress root directory" -ForegroundColor Red
        Write-Host "Please set WP_ROOT variable in this script" -ForegroundColor Yellow
        exit 1
    }
    $wpRoot = $parent
}

Write-Host "WordPress root: $wpRoot" -ForegroundColor Gray
Write-Host ""

# Run diagnostic PHP script
if (Test-Path "$PSScriptRoot\diagnose-sync.php") {
    Write-Host "Running PHP diagnostics..." -ForegroundColor Cyan
    php "$PSScriptRoot\diagnose-sync.php"
    Write-Host ""
}

# Check sync lock directly via PHP
Write-Host "=== Quick Lock Check ===" -ForegroundColor Cyan
$phpCode = @"
require '$wpRoot/wp-load.php';
echo \"Lock exists: \";
\$lock = get_option('yatco_daily_sync_lock', false);
if (\$lock !== false) {
    \$age = time() - intval(\$lock);
    echo \"YES (age: {\$age}s / \" . round(\$age / 60, 1) . \" minutes)\\n\";
    if (\$age > 600) {
        echo \"WARNING: Lock is stale - should be cleared\\n\";
    }
} else {
    echo \"NO\\n\";
}
echo \"Auto-resume: \";
\$resume = get_option('yatco_daily_sync_auto_resume', false);
echo (\$resume !== false ? \"ENABLED\" : \"DISABLED\") . \"\\n\";
"@

php -r $phpCode
Write-Host ""

# Show fix options
Write-Host "=== Fix Options ===" -ForegroundColor Cyan
Write-Host "1. Clear stale locks (safe)"
Write-Host "2. Clear all locks (force)"
Write-Host "3. Disable auto-resume"
Write-Host "4. Exit"
Write-Host ""
$choice = Read-Host "Enter option (1-4)"

switch ($choice) {
    "1" {
        Write-Host "Clearing stale locks..." -ForegroundColor Yellow
        php -r "require '$wpRoot/wp-load.php'; `$lock = get_option('yatco_daily_sync_lock', false); if (`$lock !== false) { `$age = time() - intval(`$lock); if (`$age > 600) { delete_option('yatco_daily_sync_lock'); delete_option('yatco_daily_sync_process_id'); echo \"Cleared stale lock (age: `$age s)\n\"; } else { echo \"Lock is recent, not clearing\n\"; } } else { echo \"No lock found\n\"; }"
    }
    "2" {
        Write-Host "Clearing ALL locks..." -ForegroundColor Yellow
        php -r "require '$wpRoot/wp-load.php'; delete_option('yatco_daily_sync_lock'); delete_option('yatco_daily_sync_process_id'); echo \"All locks cleared\n\";"
    }
    "3" {
        Write-Host "Disabling auto-resume..." -ForegroundColor Yellow
        php -r "require '$wpRoot/wp-load.php'; delete_option('yatco_daily_sync_auto_resume'); delete_option('yatco_last_daily_sync_resume_time'); echo \"Auto-resume disabled\n\";"
    }
    "4" {
        Write-Host "Exiting..." -ForegroundColor Gray
        exit 0
    }
    default {
        Write-Host "Invalid option" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "=== Done ===" -ForegroundColor Green

