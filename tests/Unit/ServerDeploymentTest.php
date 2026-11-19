<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('The deployment scripts use Windows batch and PowerShell.');
    }

    $this->temporaryRoot = realpath(sys_get_temp_dir());
    $this->deploymentDirectory = $this->temporaryRoot.DIRECTORY_SEPARATOR.'atrio-deploy-'.bin2hex(random_bytes(8));
    $this->files = new Filesystem;
    $this->files->makeDirectory($this->deploymentDirectory);
    $this->projectRoot = dirname(__DIR__, 2);
});

afterEach(function () {
    if (isset($this->deploymentDirectory) &&
        dirname((string) realpath($this->deploymentDirectory)) === $this->temporaryRoot) {
        $this->files->deleteDirectory($this->deploymentDirectory);
    }
});

test('the server archive contains the installable sources and excludes dependencies and site data', function () {
    $zip = new Process(
        ['cmd.exe', '/d', '/c', 'call', 'comandi server\zip-server.bat'],
        $this->projectRoot,
        ['NO_PAUSE' => '1', 'ZIP_SERVER_DEST' => $this->deploymentDirectory],
    );
    $zip->mustRun();
    $archives = glob($this->deploymentDirectory.DIRECTORY_SEPARATOR.'server-*.zip');
    expect($archives)->toHaveCount(1);

    $list = new Process(['tar.exe', '-tf', $archives[0]]);
    $list->mustRun();
    $entries = preg_split('/\R/', trim($list->getOutput()));

    expect($entries)->toContain(
        '.env.example', '.gitignore', '.npmrc', 'composer.lock', 'package-lock.json', 'vite.config.js',
        'resources/js/app.tsx', 'public/web.config', 'comandi server/installa-server.bat',
        'comandi server/ferma-progetto.bat', 'comandi server/rilancia-worker.bat', 'comandi server/gestione-server.ps1',
    );
    expect(preg_grep(
        '~^(?:vendor|node_modules|storage|bootstrap/cache|public/(?:build|storage|hot)|(?:\.env(?!\.example$)))(?:[/\.]|$)|\.sqlite~',
        $entries,
    ))->toBe([]);
});

test('the server installer requires the site environment before installing dependencies', function () {
    $scripts = $this->deploymentDirectory.DIRECTORY_SEPARATOR.'comandi server';
    $this->files->makeDirectory($scripts);
    foreach (['installa-server.bat', 'gestione-server.ps1'] as $file) {
        $this->files->copy($this->projectRoot.'/comandi server/'.$file, $scripts.'/'.$file);
    }

    $install = new Process(
        ['cmd.exe', '/d', '/c', 'call', 'comandi server\installa-server.bat'],
        $this->deploymentDirectory,
        ['NO_PAUSE' => '1'],
    );
    $install->run();

    expect($install->getExitCode())->toBe(1)
        ->and($install->getOutput())->toContain('Manca .env')
        ->and($this->files->exists($this->deploymentDirectory.'/.env'))->toBeFalse();
});

test('the stop script signals the project queue and removes the stale Vite marker', function () {
    foreach (['comandi server', 'public', 'vendor'] as $directory) {
        $this->files->makeDirectory($this->deploymentDirectory.'/'.$directory);
    }
    foreach (['ferma-progetto.bat', 'gestione-server.ps1'] as $file) {
        $this->files->copy($this->projectRoot.'/comandi server/'.$file, $this->deploymentDirectory.'/comandi server/'.$file);
    }
    $this->files->put($this->deploymentDirectory.'/.env', 'APP_ENV=testing');
    $this->files->put($this->deploymentDirectory.'/vendor/autoload.php', '<?php');
    $this->files->put($this->deploymentDirectory.'/artisan', '<?php echo implode(" ", array_slice($argv, 1)), PHP_EOL;');
    $this->files->put($this->deploymentDirectory.'/public/hot', 'http://localhost:5173');

    $stop = new Process(
        ['cmd.exe', '/d', '/c', 'call', 'comandi server\\ferma-progetto.bat'],
        $this->deploymentDirectory,
        ['NO_PAUSE' => '1'],
    );
    $stop->mustRun();

    expect($stop->getOutput())->toContain('queue:restart --no-interaction')
        ->and($this->files->exists($this->deploymentDirectory.'/public/hot'))->toBeFalse();
});

test('the worker batch points to the restart action and requires an installed site', function () {
    $scripts = $this->deploymentDirectory.'/comandi server';
    $this->files->makeDirectory($scripts);
    foreach (['rilancia-worker.bat', 'gestione-server.ps1'] as $file) {
        $this->files->copy($this->projectRoot.'/comandi server/'.$file, $scripts.'/'.$file);
    }

    $restart = new Process(
        ['cmd.exe', '/d', '/c', 'call', 'comandi server\\rilancia-worker.bat'],
        $this->deploymentDirectory,
        ['NO_PAUSE' => '1'],
    );
    $restart->run();

    expect($restart->getExitCode())->toBe(1);
    expect($restart->getOutput())->toContain('Completa l installazione prima di avviare il worker');
});

test('the server scripts start, restart and stop the queue worker and scheduler', function () {
    foreach (['comandi server', 'vendor'] as $directory) {
        $this->files->makeDirectory($this->deploymentDirectory.'/'.$directory);
    }
    $this->files->copy(
        $this->projectRoot.'/comandi server/gestione-server.ps1',
        $this->deploymentDirectory.'/comandi server/gestione-server.ps1',
    );
    $this->files->put($this->deploymentDirectory.'/.env', 'APP_ENV=testing');
    $this->files->put($this->deploymentDirectory.'/vendor/autoload.php', '<?php');
    $this->files->put($this->deploymentDirectory.'/artisan', <<<'PHP'
        <?php
        $signal = __DIR__.'/restart-signal';
        if ($argv[1] === 'queue:restart') {
            exit(file_put_contents($signal, '.', FILE_APPEND) === false ? 1 : 0);
        }
        if ($argv[1] === 'schedule:work') {
            sleep(25);
            exit;
        }
        if ($argv[1] !== 'queue:work') {
            exit(1);
        }
        $generation = @file_get_contents($signal);
        $deadline = microtime(true) + 25;
        while (@file_get_contents($signal) === $generation && microtime(true) < $deadline) {
            usleep(50_000);
        }
        PHP);
    $this->files->put($this->deploymentDirectory.'/worker-test.ps1', <<<'POWERSHELL'
        param([string] $Action)

        function Start-Process {
            param($FilePath, $WorkingDirectory, $ArgumentList, $WindowStyle, [switch] $PassThru)
            $PSBoundParameters['WindowStyle'] = 'Hidden'
            Microsoft.PowerShell.Management\Start-Process @PSBoundParameters
        }
        & "$PSScriptRoot\comandi server\gestione-server.ps1" -Action $Action
        POWERSHELL);
    $workerStatePath = $this->deploymentDirectory.'/storage/framework/atrio-worker.json';
    $schedulerStatePath = $this->deploymentDirectory.'/storage/framework/atrio-scheduler.json';
    $signalPath = $this->deploymentDirectory.'/restart-signal';
    $command = ['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'worker-test.ps1'];
    $assertStopped = function (int $workerId, int $schedulerId): void {
        $check = sprintf('if (Get-Process -Id %d,%d -ErrorAction SilentlyContinue) { exit 1 }', $workerId, $schedulerId);
        (new Process(['powershell.exe', '-NoProfile', '-Command', $check]))->mustRun();
    };

    try {
        $startWorker = new Process([...$command, 'RestartWorker'], $this->deploymentDirectory);
        $startWorker->mustRun();
        $firstWorker = json_decode($this->files->get($workerStatePath), true, flags: JSON_THROW_ON_ERROR);
        $firstScheduler = json_decode($this->files->get($schedulerStatePath), true, flags: JSON_THROW_ON_ERROR);
        expect($this->files->exists($signalPath))->toBeFalse();
        expect($firstScheduler['id'])->not->toBe($firstWorker['id']);

        $restartWorker = new Process([...$command, 'RestartWorker'], $this->deploymentDirectory);
        $restartWorker->mustRun();
        $replacement = json_decode($this->files->get($workerStatePath), true, flags: JSON_THROW_ON_ERROR);
        $replacementScheduler = json_decode($this->files->get($schedulerStatePath), true, flags: JSON_THROW_ON_ERROR);

        expect($this->files->get($signalPath))->toBe('.');
        expect($replacement['id'])->not->toBe($firstWorker['id']);
        expect($replacementScheduler['id'])->not->toBe($firstScheduler['id']);
        expect($restartWorker->getOutput())->toContain('Attendo che il worker termini il job in corso');
        $assertStopped($firstWorker['id'], $firstScheduler['id']);

        (new Process([...$command, 'Stop'], $this->deploymentDirectory))->mustRun();

        expect($this->files->exists($workerStatePath))->toBeFalse();
        expect($this->files->exists($schedulerStatePath))->toBeFalse();
        $assertStopped($replacement['id'], $replacementScheduler['id']);
    } finally {
        if ($this->files->exists($workerStatePath) || $this->files->exists($schedulerStatePath)) {
            (new Process([...$command, 'Stop'], $this->deploymentDirectory))->run();
        }
    }
});
