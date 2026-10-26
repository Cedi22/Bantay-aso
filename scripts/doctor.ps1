<#
.SYNOPSIS
    Bantay Aso — Connection & Sync Diagnostic Doctor
.DESCRIPTION
    Verifies the 5 essential connectivity, path, SSH, and permission checks
    between the local workspace and the remote Virtualmin node (bantayaso.s3curity.net).
.PARAMETER Json
    Outputs machine-readable JSON format for API consumption.
#>

[CmdletBinding()]
param(
    [switch]$Json
)

$WorkspaceRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$EnvFile = Join-Path $WorkspaceRoot ".env"

# 1. Parse .env
$RemoteHost = "bantayaso.s3curity.net"
$RemoteUser = "aso"
$RemotePort = "22"
$RemotePath = "/project/app/"
$SshKeyName = "id_ed25519_bantayaso"

if (Test-Path $EnvFile) {
    Get-Content $EnvFile | ForEach-Object {
        $line = $_.Trim()
        if ($line -and -not $line.StartsWith("#") -and $line.Contains("=")) {
            $parts = $line.Split("=", 2)
            $k = $parts[0].Trim()
            $v = $parts[1].Trim().Trim("'""'")
            if ($k -eq "VIRTUALMIN_HOST" -and $v) { $RemoteHost = $v }
            if ($k -eq "VIRTUALMIN_USER" -and $v) { $RemoteUser = $v }
            if ($k -eq "VIRTUALMIN_SSH_PORT" -and $v) { $RemotePort = $v }
            if ($k -eq "VIRTUALMIN_REMOTE_PATH" -and $v) { $RemotePath = $v }
        }
    }
}
if (-not $RemotePath.EndsWith("/")) { $RemotePath += "/" }

$results = @{
    timestamp = (Get-Date -Format "yyyy-MM-ddTHH:mm:ssZ")
    workspace_root = $WorkspaceRoot
    remote_target = "${RemoteUser}@${RemoteHost}:${RemotePort}"
    remote_path = $RemotePath
    all_passed = $true
    checks = @()
}

function Add-CheckResult($id, $title, $passed, $details, $errorMsg = $null) {
    $item = @{
        id = $id
        title = $title
        passed = [bool]$passed
        details = $details
        error = $errorMsg
    }
    $script:results.checks += $item
    if (-not $passed) {
        $script:results.all_passed = $false
    }
}

# -------------------------------------------------------------
# Check 1: Workspace Path & Broken OneDrive Symlinks
# -------------------------------------------------------------
$check1Passed = $true
$check1Details = "Workspace directory valid at $WorkspaceRoot."
$check1Err = $null

if (-not (Test-Path $WorkspaceRoot)) {
    $check1Passed = $false
    $check1Err = "Workspace root does not exist: $WorkspaceRoot"
} else {
    $brokenLinks = @()
    Get-ChildItem -Path $WorkspaceRoot -Force | Where-Object { $_.LinkType } | ForEach-Object {
        $target = $_.Target
        if ($target -like "*OneDrive*" -and -not (Test-Path $target)) {
            $brokenLinks += "$($_.Name) -> $target"
        }
    }
    if ($brokenLinks.Count -gt 0) {
        $check1Passed = $false
        $check1Err = "Found broken OneDrive symlink(s): " + ($brokenLinks -join ", ")
    } else {
        $check1Details += " No broken OneDrive symlinks detected."
    }
}
Add-CheckResult 1 "Local Workspace & Symlink Integrity" $check1Passed $check1Details $check1Err

# -------------------------------------------------------------
# Check 2: WSL Ubuntu Tools (/usr/bin/rsync and /usr/bin/ssh)
# -------------------------------------------------------------
$check2Passed = $false
$check2Details = ""
$check2Err = $null

try {
    $wslCheck = wsl.exe -d Ubuntu -e bash -c "which rsync && which ssh" 2>&1
    if ($LASTEXITCODE -eq 0 -and $wslCheck -match "rsync" -and $wslCheck -match "ssh") {
        $check2Passed = $true
        $check2Details = "WSL Ubuntu /usr/bin/rsync and /usr/bin/ssh are installed and operational."
    } else {
        $check2Err = "Missing rsync or ssh in WSL Ubuntu: $wslCheck"
    }
} catch {
    $check2Err = "Failed to invoke WSL Ubuntu: $_"
}
Add-CheckResult 2 "WSL Ubuntu rsync and ssh Availability" $check2Passed $check2Details $check2Err

# -------------------------------------------------------------
# Check 3: SSH Key in WSL (~/.ssh/id_ed25519_bantayaso) with 600
# -------------------------------------------------------------
$check3Passed = $false
$check3Details = ""
$check3Err = $null

try {
    $keyPerms = (wsl.exe -d Ubuntu -e bash -c "[ -f ~/.ssh/$SshKeyName ] && stat -c %a ~/.ssh/$SshKeyName" 2>&1).Trim()
    if ($keyPerms -eq "600" -or $keyPerms -eq "400") {
        $check3Passed = $true
        $check3Details = "WSL key ~/.ssh/$SshKeyName exists with secure permissions ($keyPerms)."
    } elseif ($keyPerms) {
        # Attempt auto-chmod 600
        wsl.exe -d Ubuntu -e bash -c "chmod 600 ~/.ssh/$SshKeyName" | Out-Null
        $keyPerms2 = (wsl.exe -d Ubuntu -e bash -c "stat -c %a ~/.ssh/$SshKeyName" 2>&1).Trim()
        if ($keyPerms2 -eq "600") {
            $check3Passed = $true
            $check3Details = "WSL key ~/.ssh/$SshKeyName exists; permissions secured to 600."
        } else {
            $check3Err = "WSL key ~/.ssh/$SshKeyName has insecure permissions: $keyPerms"
        }
    } else {
        $check3Err = "WSL key ~/.ssh/$SshKeyName not found."
    }
} catch {
    $check3Err = "Failed checking WSL SSH key: $_"
}
Add-CheckResult 3 "WSL Private SSH Key Permissions (600)" $check3Passed $check3Details $check3Err

# -------------------------------------------------------------
# Check 4: Passwordless SSH Login (BatchMode=yes)
# -------------------------------------------------------------
$check4Passed = $false
$check4Details = ""
$check4Err = $null

try {
    $sshTest = (wsl.exe -d Ubuntu -e ssh -i "~/.ssh/$SshKeyName" -p $RemotePort -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 "${RemoteUser}@${RemoteHost}" "whoami" 2>&1).Trim()
    if ($LASTEXITCODE -eq 0 -and $sshTest -eq $RemoteUser) {
        $check4Passed = $true
        $check4Details = "Passwordless SSH login to ${RemoteUser}@${RemoteHost}:${RemotePort} succeeded (User verified: $sshTest)."
    } else {
        $check4Err = "SSH connection failed with exit code $LASTEXITCODE. Output: $sshTest"
    }
} catch {
    $check4Err = "SSH execution error: $_"
}
Add-CheckResult 4 "Passwordless SSH BatchMode Login" $check4Passed $check4Details $check4Err

# -------------------------------------------------------------
# Check 5: Remote Target Directory Writable
# -------------------------------------------------------------
$check5Passed = $false
$check5Details = ""
$check5Err = $null

try {
    $probeCmd = "touch ${RemotePath}.doctor_probe && rm -f ${RemotePath}.doctor_probe && echo WRITABLE"
    $probeTest = (wsl.exe -d Ubuntu -e ssh -i "~/.ssh/$SshKeyName" -p $RemotePort -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 "${RemoteUser}@${RemoteHost}" $probeCmd 2>&1).Trim()
    if ($LASTEXITCODE -eq 0 -and $probeTest -match "WRITABLE") {
        $check5Passed = $true
        $check5Details = "Remote directory $RemotePath is writable by $RemoteUser."
    } else {
        $check5Err = "Remote target directory is not writable. Output: $probeTest"
    }
} catch {
    $check5Err = "Remote target test error: $_"
}
Add-CheckResult 5 "Remote Target Directory Writable" $check5Passed $check5Details $check5Err

if ($Json) {
    Write-Output ($results | ConvertTo-Json -Depth 5)
} else {
    Write-Host "========================================================" -ForegroundColor Cyan
    Write-Host "   Bantay Aso - Connection and Sync Doctor" -ForegroundColor Green
    Write-Host "========================================================" -ForegroundColor Cyan
    Write-Host ("Workspace Root : " + $WorkspaceRoot) -ForegroundColor Gray
    Write-Host ("Remote Target  : " + $RemoteUser + "@" + $RemoteHost + ":" + $RemotePort) -ForegroundColor Gray
    Write-Host ("Remote Path    : " + $RemotePath) -ForegroundColor Gray
    Write-Host "--------------------------------------------------------" -ForegroundColor DarkGray

    foreach ($c in $results.checks) {
        $id = $c.id
        $title = $c.title
        if ($c.passed) {
            Write-Host " [$id/5] [PASS] $title" -ForegroundColor Green
            Write-Host "       $($c.details)" -ForegroundColor DarkGray
        } else {
            Write-Host " [$id/5] [FAIL] $title" -ForegroundColor Red
            Write-Host "       Error: $($c.error)" -ForegroundColor Yellow
        }
    }

    Write-Host "--------------------------------------------------------" -ForegroundColor DarkGray
    if ($results.all_passed) {
        Write-Host " [OK] ALL 5 DIAGNOSTIC CHECKS PASSED. Ready for live sync!" -ForegroundColor Green
    } else {
        Write-Host " [!] ONE OR MORE CHECKS FAILED. Please review above errors." -ForegroundColor Red
    }
    Write-Host "========================================================" -ForegroundColor Cyan
}

if ($results.all_passed) {
    exit 0
} else {
    exit 1
}
