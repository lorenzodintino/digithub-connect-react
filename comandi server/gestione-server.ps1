param(
    [Parameter(Mandatory)]
    [ValidateSet('Install', 'Stop', 'RestartWorker')]
    [string] $Action
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$artisanPath = Join-Path $projectRoot 'artisan'
$workerStatePath = Join-Path $projectRoot 'storage/framework/atrio-worker.json'
$schedulerStatePath = Join-Path $projectRoot 'storage/framework/atrio-scheduler.json'

function Invoke-Checked {
    param([string] $Executable, [string[]] $CommandArguments)

    & $Executable @CommandArguments
    if ($LASTEXITCODE -ne 0) {
        throw "$([System.IO.Path]::GetFileName($Executable)) $($CommandArguments[0]) fallito (codice $LASTEXITCODE)."
    }
}

function Get-ProjectProcess {
    param([string] $StatePath)

    if (-not (Test-Path -LiteralPath $StatePath -PathType Leaf)) {
        return
    }
    $state = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
    $process = Get-Process -Id $state.id -ErrorAction SilentlyContinue
    if ($process -and $process.ProcessName -eq 'php' -and
        $process.StartTime.ToFileTimeUtc().ToString() -eq $state.startedAt) {
        return $process
    }
}

function Stop-ProjectScheduler {
    $scheduler = Get-ProjectProcess $schedulerStatePath
    if ($scheduler) {
        Stop-Process -InputObject $scheduler
        if (-not $scheduler.WaitForExit(10000)) {
            throw 'Scheduler ancora attivo. Chiudi la sua finestra e riprova.'
        }
        Write-Host '[OK] Scheduler arrestato.'
    }
    if (Test-Path -LiteralPath $schedulerStatePath -PathType Leaf) {
        Remove-Item -LiteralPath $schedulerStatePath -Force
    }
}

function Stop-ProjectWorker {
    param([switch] $OnlyIfRunning)

    $worker = Get-ProjectProcess $workerStatePath
    if ($OnlyIfRunning -and -not $worker) {
        return
    }

    if ((Test-Path -LiteralPath '.env' -PathType Leaf) -and (Test-Path -LiteralPath 'vendor/autoload.php' -PathType Leaf)) {
        Invoke-Checked 'php.exe' @($artisanPath, 'queue:restart', '--no-interaction')
    } elseif ($worker) {
        throw 'Chiudi la finestra del worker: Laravel non e ancora disponibile per arrestarlo.'
    }

    if ($worker) {
        Write-Host '[..] Attendo che il worker termini il job in corso...'
        if (-not $worker.WaitForExit(60000)) {
            throw 'Worker ancora attivo. Attendi la fine del job e riprova.'
        }
    }
    if (Test-Path -LiteralPath $workerStatePath -PathType Leaf) {
        Remove-Item -LiteralPath $workerStatePath -Force
    }
}

function Start-ProjectProcess {
    param([string] $StatePath, [string] $Command, [string] $Label)

    $phpPath = (Get-Command php.exe -CommandType Application).Source
    $arguments = '"{0}" {1}' -f $artisanPath, $Command
    $process = Start-Process -FilePath $phpPath -WorkingDirectory $projectRoot -ArgumentList $arguments -WindowStyle Normal -PassThru
    if ($process.WaitForExit(1000)) {
        throw "$Label si e chiuso durante l avvio. Controlla storage/logs/laravel.log."
    }
    try {
        @{
            id = $process.Id
            startedAt = $process.StartTime.ToFileTimeUtc().ToString()
        } | ConvertTo-Json -Compress | Set-Content -LiteralPath $StatePath -Encoding Ascii
    } catch {
        if ($StatePath -eq $workerStatePath) {
            Invoke-Checked 'php.exe' @($artisanPath, 'queue:restart', '--no-interaction')
        } else {
            Stop-Process -InputObject $process
        }
        throw
    }
    Write-Host "[OK] $Label avviato in una finestra separata (PID $($process.Id))."
}

function Start-ProjectProcesses {
    Start-ProjectProcess $workerStatePath 'queue:work --tries=3 -v --no-interaction' 'Worker email'
    Start-ProjectProcess $schedulerStatePath 'schedule:work -v --no-interaction' 'Scheduler'
}

function Restart-ProjectProcesses {
    foreach ($file in '.env', 'artisan', 'vendor/autoload.php') {
        if (-not (Test-Path -LiteralPath $file -PathType Leaf)) {
            throw "Manca $file nella cartella del sito. Completa l installazione prima di avviare il worker."
        }
    }
    if (-not (Get-Command php.exe -CommandType Application -ErrorAction SilentlyContinue)) {
        throw 'php.exe non trovato nel PATH del server.'
    }

    Stop-ProjectScheduler
    Stop-ProjectWorker -OnlyIfRunning
    Start-ProjectProcesses
}

function Stop-LocalDevelopment {
    $nodeModules = Join-Path $projectRoot 'node_modules'
    try {
        $nodeProcesses = Get-CimInstance Win32_Process -Filter "Name = 'node.exe'"
    } catch {
        Write-Warning 'Impossibile verificare Vite e concurrently. Se sono attivi, chiudili dal terminale di sviluppo.'
        return
    }
    $devProcesses = $nodeProcesses |
        Where-Object {
            $_.CommandLine -match ([regex]::Escape($nodeModules) + '\\.*(?:vite|concurrently)\\')
        } | Sort-Object { $_.CommandLine -notmatch 'concurrently' }

    foreach ($devProcess in $devProcesses) {
        if (Get-Process -Id $devProcess.ProcessId -ErrorAction SilentlyContinue) {
            & taskkill.exe /PID $devProcess.ProcessId /T /F
            if ($LASTEXITCODE -ne 0 -and (Get-Process -Id $devProcess.ProcessId -ErrorAction SilentlyContinue)) {
                throw "Impossibile arrestare il processo di sviluppo $($devProcess.ProcessId)."
            }
        }
    }
}

function Install-Server {
    foreach ($file in '.env', 'artisan', 'composer.lock', 'package-lock.json', 'vite.config.js', 'public/web.config') {
        if (-not (Test-Path -LiteralPath $file -PathType Leaf)) {
            throw "Manca $file nella cartella del sito. Su una nuova installazione configura .env da .env.example."
        }
    }
    foreach ($command in 'php.exe', 'composer', 'node.exe', 'npm.cmd') {
        if (-not (Get-Command $command -CommandType Application -ErrorAction SilentlyContinue)) {
            throw "$command non trovato nel PATH del server."
        }
    }
    $nodeVersion = & node.exe --version
    if ($LASTEXITCODE -ne 0 -or [version]($nodeVersion -replace '^v') -lt [version]'22.12.0') {
        throw 'Installa Node.js 22.12 o superiore prima di aggiornare il sito.'
    }

    Write-Host '[1/5] Controllo piattaforma PHP e cartelle di Laravel...'
    Invoke-Checked 'composer' @('check-platform-reqs', '--lock', '--no-dev', '--no-interaction')
    $directories = @(
        'bootstrap/cache', 'storage/app/private', 'storage/app/public',
        'storage/framework/cache/data', 'storage/framework/sessions',
        'storage/framework/views', 'storage/logs'
    )
    foreach ($directory in $directories) {
        [void][System.IO.Directory]::CreateDirectory((Join-Path $projectRoot $directory))
    }

    Stop-ProjectScheduler
    if (Test-Path -LiteralPath 'vendor/autoload.php' -PathType Leaf) {
        if (-not (Test-Path -LiteralPath 'storage/framework/maintenance.php' -PathType Leaf)) {
            Invoke-Checked 'php.exe' @($artisanPath, 'down', '--render=errors::503', '--no-interaction')
        }
        Stop-ProjectWorker
    }

    Write-Host '[2/5] Installo le dipendenze PHP dal lockfile...'
    Invoke-Checked 'composer' @('install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-progress', '--no-interaction')
    if (-not (Test-Path -LiteralPath 'storage/framework/maintenance.php' -PathType Leaf)) {
        Invoke-Checked 'php.exe' @($artisanPath, 'down', '--render=errors::503', '--no-interaction')
    }
    $verifyKey = 'require ''vendor/autoload.php''; $app = require ''bootstrap/app.php''; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $app->make(''encrypter'');'
    Write-Host '[..] Verifico APP_KEY...'
    Invoke-Checked 'php.exe' @('-r', $verifyKey)
    Invoke-Checked 'php.exe' @($artisanPath, 'route:clear', '--no-interaction')

    Write-Host '[3/5] Preparo le dipendenze JavaScript e compilo gli asset...'
    $npmStamp = 'node_modules/.atrio-dependencies'
    $lockHashes = Get-FileHash -LiteralPath 'package-lock.json', 'package.json', '.npmrc'
    $dependencyVersion = ($lockHashes.Hash -join ':') + ':' + $nodeVersion
    if (-not (Test-Path -LiteralPath 'node_modules/.bin/vite.cmd' -PathType Leaf) -or
        -not (Test-Path -LiteralPath $npmStamp -PathType Leaf) -or
        (Get-Content -LiteralPath $npmStamp -Raw).Trim() -ne $dependencyVersion) {
        Invoke-Checked 'npm.cmd' @('ci', '--include=dev', '--no-audit', '--no-fund', '--prefer-offline')
        Set-Content -LiteralPath $npmStamp -Value $dependencyVersion -Encoding Ascii
    }
    Invoke-Checked 'npm.cmd' @('run', 'build')
    if (-not (Test-Path -LiteralPath 'public/build/manifest.json' -PathType Leaf)) {
        throw 'Manifest di Vite mancante dopo la build.'
    }
    if (Test-Path -LiteralPath 'public/hot' -PathType Leaf) {
        Remove-Item -LiteralPath 'public/hot' -Force
    }

    Write-Host '[4/5] Applico le migrazioni, verifico lo storage e ottimizzo Laravel...'
    Invoke-Checked 'php.exe' @($artisanPath, 'migrate', '--force', '--no-interaction')
    if (-not (Test-Path -LiteralPath 'public/storage' -PathType Container)) {
        Invoke-Checked 'php.exe' @($artisanPath, 'storage:link', '--no-interaction')
        if (-not (Test-Path -LiteralPath 'public/storage' -PathType Container)) {
            throw 'Link public/storage mancante. Verifica i permessi per creare il link e riprova.'
        }
    }
    Invoke-Checked 'php.exe' @($artisanPath, 'optimize', '--no-interaction')
    Invoke-Checked 'php.exe' @($artisanPath, 'up', '--no-interaction')

    Write-Host '[5/5] Avvio worker email e scheduler in finestre separate...'
    Start-ProjectProcesses
}

$operationLock = $null
try {
    Set-Location -LiteralPath $projectRoot
    [void][System.IO.Directory]::CreateDirectory((Join-Path $projectRoot 'storage/framework'))
    try {
        $operationLock = [System.IO.File]::Open(
            (Join-Path $projectRoot 'storage/framework/gestione-server.lock'), 'OpenOrCreate', 'Write', 'None'
        )
    } catch [System.IO.IOException] {
        throw 'Impossibile acquisire il lock. Controlla se un altro comando server e gia in esecuzione.'
    }

    if ($Action -eq 'Install') {
        Install-Server
    } elseif ($Action -eq 'RestartWorker') {
        Restart-ProjectProcesses
    } else {
        Stop-LocalDevelopment
        Stop-ProjectScheduler
        Stop-ProjectWorker
        if (Test-Path -LiteralPath 'public/hot' -PathType Leaf) {
            Remove-Item -LiteralPath 'public/hot' -Force
        }
        Write-Host '[OK] Arresto dei processi del progetto completato.'
    }
} catch {
    Write-Host "[ERRORE] $($_.Exception.Message)" -ForegroundColor Red
    if ($Action -eq 'Install' -and (Test-Path -LiteralPath 'storage/framework/maintenance.php' -PathType Leaf)) {
        Write-Host 'Il sito e in manutenzione. Correggi l errore e rilancia installa-server.bat.'
    }
    exit 1
} finally {
    if ($operationLock) {
        $operationLock.Dispose()
    }
}
