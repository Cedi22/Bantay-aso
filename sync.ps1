<#
.SYNOPSIS
    Bantay Aso — Two-Way File Sync Script (PowerShell / WSL rsync)
.DESCRIPTION
    Synchronizes code and assets between local Windows repository and Virtualmin VPS
    using rsync over SSH keys with --update flag to prevent overwriting newer files.
.PARAMETER Direction
    'Both' (default: pull then push), 'Pull' (remote -> local), or 'Push' (local -> remote).
.PARAMETER DryRun
    Perform a dry run preview without modifying any files.
.PARAMETER IncludeDb
    If set, includes data/bantayaso.sqlite. By default, the SQLite database is excluded
    so background cron snapshots on the server are not overwritten.
#>

[CmdletBinding()]
param(
    [ValidateSet('Both', 'Pull', 'Push')]
    [string]$Direction = 'Both',

    [switch]$DryRun,

    [switch]$IncludeDb
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# 1. Parse .env Configuration
$EnvFile = Join-Path $ScriptDir ".env"
if (Test-Path $EnvFile) {
    Get-Content $EnvFile | ForEach-Object {
        $line = $_.Trim()
        if ($line -and -not $line.StartsWith("#") -and $line.Contains("=")) {
            $parts = $line.Split("=", 2)
            $key = $parts[0].Trim()
            $val = $parts[1].Trim().Trim("'""'")
            [System.Environment]::SetEnvironmentVariable($key, $val, 'Process')
        }
    }
}

$RemoteHost = [System.Environment]::GetEnvironmentVariable("VIRTUALMIN_HOST")
$RemoteUser = [System.Environment]::GetEnvironmentVariable("VIRTUALMIN_USER")
$RemotePort = [System.Environment]::GetEnvironmentVariable("VIRTUALMIN_SSH_PORT")
$RemotePath = [System.Environment]::GetEnvironmentVariable("VIRTUALMIN_REMOTE_PATH")
$SshKey     = [System.Environment]::GetEnvironmentVariable("SSH_KEY_PATH")

if (-not $RemoteHost) { $RemoteHost = "bantayaso.s3curity.net" }
if (-not $RemoteUser) { $RemoteUser = "aso" }
if (-not $RemotePort) { $RemotePort = "22" }
if (-not $RemotePath) { $RemotePath = "/project/app/" }
if (-not $SshKey)     { $SshKey = "~/.ssh/id_ed25519_bantayaso" }

if (-not $RemotePath.EndsWith("/")) { $RemotePath += "/" }

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   Bantay Aso - Two-Way Virtualmin Sync Engine" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ("Target Host   : " + $RemoteUser + "@" + $RemoteHost + ":" + $RemotePort) -ForegroundColor Gray
Write-Host ("Remote Path   : " + $RemotePath) -ForegroundColor Gray
Write-Host ("Direction     : " + $Direction) -ForegroundColor Yellow
if ($DryRun) {
    Write-Host "Mode          : [DRY-RUN / PREVIEW ONLY]" -ForegroundColor Magenta
} else {
    Write-Host "Mode          : [LIVE SYNCHRONIZATION]" -ForegroundColor Green
}
Write-Host "--------------------------------------------------------" -ForegroundColor DarkGray

# 2. Check for rsync (Native or WSL)
$UseWsl = $false
$foundRsync = Get-Command rsync.exe -ErrorAction SilentlyContinue
$RsyncCmd = if ($foundRsync) { $foundRsync.Source } else { $null }

if (-not $RsyncCmd) {
    $foundWsl = Get-Command wsl.exe -ErrorAction SilentlyContinue
    $WslCmd = if ($foundWsl) { $foundWsl.Source } else { $null }
    if ($WslCmd) {
        $UseWsl = $true
        Write-Host "[*] Using WSL Ubuntu rsync bridge..." -ForegroundColor Cyan
    } else {
        Write-Error "rsync was not found on your system and WSL is unavailable. Please install rsync or enable WSL."
    }
}

# 3. Build Excludes
$Excludes = @(
    "--exclude=.git/",
    "--exclude=node_modules/",
    "--exclude=.env",
    "--exclude=.env.*",
    "--exclude=*.log",
    "--exclude=__pycache__/",
    "--exclude=*.pyc",
    "--exclude=.vscode/",
    "--exclude=.idea/",
    "--exclude=Thumbs.db",
    "--exclude=.DS_Store",
    "--exclude=ecc-framework/",
    "--exclude=ECC",
    "--exclude=data/*.sqlite-journal",
    "--exclude=data/*.sqlite-wal",
    "--exclude=data/*.sqlite-shm"
)

if (-not $IncludeDb) {
    $Excludes += "--exclude=data/bantayaso.sqlite"
    Write-Host "[*] Safe State Mode: data/bantayaso.sqlite excluded (use -IncludeDb to override)" -ForegroundColor DarkGray
}

$LocalFolder = $ScriptDir.Replace("\", "/")
if (-not $LocalFolder.EndsWith("/")) { $LocalFolder += "/" }

$RemoteTarget = "${RemoteUser}@${RemoteHost}:${RemotePath}"

if ($UseWsl) {
    # Convert Windows C:\path to WSL /mnt/c/path
    $Drive = $ScriptDir.Substring(0, 1).ToLower()
    $SubPath = $ScriptDir.Substring(2).Replace("\", "/")
    $WslLocalPath = "/mnt/" + $Drive + $SubPath + "/"

    $WslKey = $SshKey
    if ($SshKey.StartsWith("C:") -or $SshKey.StartsWith("c:")) {
        $KeyDrive = $SshKey.Substring(0, 1).ToLower()
        $KeySub = $SshKey.Substring(2).Replace("\", "/")
        $WslKey = "/mnt/" + $KeyDrive + $KeySub
    }

    # Staging logic for WSL: Windows DrvFs (/mnt/c) has 0777 permissions which SSH strictly rejects with 'bad permissions'.
    # If the key is under /mnt/, copy to ~/.ssh inside WSL and enforce 0600 permissions.
    $setupKeyScript = 'mkdir -p ~/.ssh && chmod 700 ~/.ssh; ' +
        'KEY="' + $WslKey + '"; ' +
        'if [ -n "$KEY" ] && [ -f "$KEY" ]; then ' +
        '  if [[ "$KEY" == /mnt/* ]]; then ' +
        '    bname=$(basename "$KEY"); ' +
        '    cp -f "$KEY" "$HOME/.ssh/$bname" 2>/dev/null || true; ' +
        '    chmod 600 "$HOME/.ssh/$bname" 2>/dev/null || true; ' +
        '    echo "$HOME/.ssh/$bname"; ' +
        '  else ' +
        '    chmod 600 "$KEY" 2>/dev/null || true; ' +
        '    echo "$KEY"; ' +
        '  fi; ' +
        'else ' +
        '  for cand in "$HOME/.ssh/id_ed25519_bantayaso" "$HOME/.ssh/id_ed25519" "$HOME/.ssh/id_rsa"; do ' +
        '    if [ -f "$cand" ]; then ' +
        '      chmod 600 "$cand" 2>/dev/null || true; ' +
        '      echo "$cand"; ' +
        '      break; ' +
        '    fi; ' +
        '  done; ' +
        'fi'

    $ResolvedWslKey = (& wsl.exe -e bash -c $setupKeyScript).Trim()
    if ($ResolvedWslKey) {
        Write-Host "[*] Utilizing Ed25519 SSH Key: $ResolvedWslKey (Permissions: 0600 verified)" -ForegroundColor DarkCyan
        $SshOpt = "ssh -p $RemotePort -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -i '$ResolvedWslKey'"
    } else {
        Write-Host "[*] Utilizing default SSH agent / known keys" -ForegroundColor DarkCyan
        $SshOpt = "ssh -p $RemotePort -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10"
    }

    function Run-WslRsync($src, $dst, $label) {
        Write-Host ""
        Write-Host "[*] $label..." -ForegroundColor Cyan
        Write-Host "    Source: $src" -ForegroundColor DarkGray
        Write-Host "    Dest  : $dst" -ForegroundColor DarkGray
        $argsList = @("-e", "rsync", "-avz", "--update", "--omit-dir-times", "-i", "--human-readable", "--temp-dir=/tmp", "--no-perms", "--no-owner", "--no-group", "--chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r")
        if ($DryRun) { $argsList += "--dry-run" }
        $argsList += "-e"
        $argsList += $SshOpt
        $argsList += $Excludes
        $argsList += $src
        $argsList += $dst

        & wsl.exe $argsList
        $exitCode = $LASTEXITCODE
        if ($exitCode -ne 0 -and $exitCode -ne 24 -and $exitCode -ne 23) {
            Write-Error "[FAIL] rsync exited with code $exitCode during $label. Target: $src -> $dst (Host: $RemoteTarget, Port: $RemotePort, Key: $ResolvedWslKey)"
            exit $exitCode
        } else {
            Write-Host "[OK] $label succeeded." -ForegroundColor Green
        }
    }

    if ($Direction -eq 'Both' -or $Direction -eq 'Pull') {
        Run-WslRsync $RemoteTarget $WslLocalPath "PULL: Downloading newer files from Virtualmin"
    }

    if ($Direction -eq 'Both' -or $Direction -eq 'Push') {
        Run-WslRsync $WslLocalPath $RemoteTarget "PUSH: Uploading newer local files to Virtualmin"
    }

} else {
    $WinKey = $SshKey
    if ($WinKey.StartsWith("~")) {
        $WinKey = Join-Path $HOME ($WinKey.Substring(1).TrimStart("/\"))
    }
    if (-not (Test-Path $WinKey)) {
        $candidates = @(
            (Join-Path $HOME ".ssh\id_ed25519_bantayaso"),
            (Join-Path $HOME ".ssh\id_ed25519"),
            (Join-Path $HOME ".ssh\id_rsa")
        )
        foreach ($c in $candidates) {
            if (Test-Path $c) { $WinKey = $c; break }
        }
    }
    if ($WinKey -and (Test-Path $WinKey)) {
        Write-Host "[*] Utilizing Ed25519 SSH Key: $WinKey" -ForegroundColor DarkCyan
        $SshOpt = "ssh -p $RemotePort -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -i `"$WinKey`""
    } else {
        $SshOpt = "ssh -p $RemotePort -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10"
    }

    function Run-NativeRsync($src, $dst, $label) {
        Write-Host ""
        Write-Host "[*] $label..." -ForegroundColor Cyan
        Write-Host "    Source: $src" -ForegroundColor DarkGray
        Write-Host "    Dest  : $dst" -ForegroundColor DarkGray
        $rsyncArgs = @("-avz", "--update", "--omit-dir-times", "-i", "--human-readable", "--temp-dir=/tmp", "--no-perms", "--no-owner", "--no-group", "-e", $SshOpt)
        if ($DryRun) { $rsyncArgs += "--dry-run" }
        $rsyncArgs += $Excludes
        $rsyncArgs += $src
        $rsyncArgs += $dst

        & $RsyncCmd $rsyncArgs
        $exitCode = $LASTEXITCODE
        if ($exitCode -ne 0 -and $exitCode -ne 24 -and $exitCode -ne 23) {
            Write-Error "[FAIL] rsync exited with code $exitCode during $label. Target: $src -> $dst (Host: $RemoteTarget, Port: $RemotePort, Key: $WinKey)"
            exit $exitCode
        } else {
            Write-Host "[OK] $label succeeded." -ForegroundColor Green
        }
    }

    if ($Direction -eq 'Both' -or $Direction -eq 'Pull') {
        Run-NativeRsync $RemoteTarget $LocalFolder "PULL: Downloading newer files from Virtualmin"
    }

    if ($Direction -eq 'Both' -or $Direction -eq 'Push') {
        Run-NativeRsync $LocalFolder $RemoteTarget "PUSH: Uploading newer local files to Virtualmin"
    }
}

Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   Two-Way File Synchronization Complete" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Cyan
