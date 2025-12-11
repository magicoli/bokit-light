#!/usr/bin/env php
<?php
/**
 * Queue Worker Helper for Bokit
 * 
 * This script starts a queue worker that processes jobs in the background.
 * The worker is needed for auto-sync to work properly.
 * 
 * Usage:
 *   php worker.php start   # Start the worker
 *   php worker.php stop    # Stop the worker
 *   php worker.php restart # Restart the worker
 *   php worker.php status  # Check if worker is running
 * 
 * For production, you should use supervisor or systemd to manage this worker.
 * For development, you can run this script manually.
 */

$command = $argv[1] ?? 'start';
$pidFile = __DIR__ . '/storage/worker.pid';

function isWorkerRunning($pidFile) {
    if (!file_exists($pidFile)) {
        return false;
    }
    
    $pid = (int) file_get_contents($pidFile);
    
    // Check if process is running
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $output = [];
        exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
        return count($output) > 2; // Header + process line
    } else {
        // Unix/Linux/Mac
        return posix_kill($pid, 0);
    }
}

function stopWorker($pidFile) {
    if (!file_exists($pidFile)) {
        echo "❌ No worker PID file found\n";
        return false;
    }
    
    $pid = (int) file_get_contents($pidFile);
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("taskkill /PID $pid /F 2>NUL");
    } else {
        posix_kill($pid, SIGTERM);
    }
    
    unlink($pidFile);
    echo "✅ Worker stopped (PID: $pid)\n";
    return true;
}

function startWorker($pidFile) {
    if (isWorkerRunning($pidFile)) {
        $pid = file_get_contents($pidFile);
        echo "⚠️  Worker already running (PID: $pid)\n";
        return false;
    }
    
    $artisan = __DIR__ . '/artisan';
    $logFile = __DIR__ . '/storage/logs/worker.log';
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')) {
        // Windows
        $cmd = "start /B php \"$artisan\" queue:work --daemon > \"$logFile\" 2>&1";
        pclose(popen($cmd, 'r'));
        // On Windows, we can't easily get the PID, so we'll just mark it as running
        file_put_contents($pidFile, getmypid());
    } else {
        // Unix/Linux/Mac
        $cmd = "nohup php \"$artisan\" queue:work --daemon > \"$logFile\" 2>&1 & echo $!";
        $pid = trim(shell_exec($cmd));
        file_put_contents($pidFile, $pid);
    }
    
    sleep(1); // Give it a moment to start
    
    if (isWorkerRunning($pidFile)) {
        $pid = file_get_contents($pidFile);
        echo "✅ Worker started (PID: $pid)\n";
        echo "📋 Logs: $logFile\n";
        return true;
    } else {
        echo "❌ Failed to start worker\n";
        return false;
    }
}

switch ($command) {
    case 'start':
        startWorker($pidFile);
        break;
        
    case 'stop':
        stopWorker($pidFile);
        break;
        
    case 'restart':
        echo "🔄 Restarting worker...\n";
        stopWorker($pidFile);
        sleep(1);
        startWorker($pidFile);
        break;
        
    case 'status':
        if (isWorkerRunning($pidFile)) {
            $pid = file_get_contents($pidFile);
            echo "✅ Worker is running (PID: $pid)\n";
        } else {
            echo "❌ Worker is not running\n";
            if (file_exists($pidFile)) {
                echo "   (Stale PID file found, removing...)\n";
                unlink($pidFile);
            }
        }
        break;
        
    default:
        echo "Usage: php worker.php {start|stop|restart|status}\n";
        exit(1);
}
