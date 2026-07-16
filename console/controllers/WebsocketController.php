<?php

namespace console\controllers;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use Yii;

/**
 * WebSocket server management commands
 */
class WebsocketController extends Controller
{
    /**
     * @var bool Run server as daemon
     */
    public $daemon = false;

    /**
     * @var string PID file path
     */
    private $pidFile;

    /**
     * @var string Log file path
     */
    private $logFile;

    public function init()
    {
        parent::init();
        $this->pidFile = Yii::getAlias('@common/runtime/websocket.pid');
        $this->logFile = Yii::getAlias('@common/runtime/logs/websocket.log');

        // Create runtime directory if not exists
        $runtimeDir = dirname($this->pidFile);
        if (!is_dir($runtimeDir)) {
            mkdir($runtimeDir, 0755, true);
        }

        // Create logs directory if not exists
        $logsDir = dirname($this->logFile);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['daemon']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'd' => 'daemon',
        ]);
    }

    /**
     * Start WebSocket server
     */
    public function actionStart()
    {
        if ($this->isRunning()) {
            $this->stdout("WebSocket server is already running (PID: " . $this->getPid() . ")\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Starting WebSocket server...\n", Console::FG_GREEN);

        if ($this->daemon) {
            return $this->startDaemon();
        } else {
            return $this->startForeground();
        }
    }

    /**
     * Stop WebSocket server
     */
    public function actionStop()
    {
        if (!$this->isRunning()) {
            $this->stdout("WebSocket server is not running\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $pid = $this->getPid();
        $this->stdout("Stopping WebSocket server (PID: $pid)...\n", Console::FG_GREEN);

        // Send SIGTERM
        if (posix_kill($pid, SIGTERM)) {
            // Wait for graceful shutdown
            $timeout = 10;
            while ($timeout > 0 && $this->isRunning()) {
                sleep(1);
                $timeout--;
            }

            // Force kill if still running
            if ($this->isRunning()) {
                $this->stdout("Force stopping server...\n", Console::FG_YELLOW);
                posix_kill($pid, SIGKILL);
                sleep(1);
            }

            if (!$this->isRunning()) {
                $this->removePidFile();
                $this->stdout("WebSocket server stopped\n", Console::FG_GREEN);
                return ExitCode::OK;
            }
        }

        $this->stdout("Failed to stop WebSocket server\n", Console::FG_RED);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Restart WebSocket server
     */
    public function actionRestart()
    {
        $this->actionStop();
        sleep(2);
        return $this->actionStart();
    }

    /**
     * Show WebSocket server status
     */
    public function actionStatus()
    {
        if ($this->isRunning()) {
            $pid = $this->getPid();
            $this->stdout("WebSocket server is running (PID: $pid)\n", Console::FG_GREEN);
            $this->stdout("WebSocket port: 8900\n");
            $this->stdout("HTTP notifications port: 8901\n");
            $this->stdout("PID file: " . $this->pidFile . "\n");
            $this->stdout("Log file: " . $this->logFile . "\n");
        } else {
            $this->stdout("WebSocket server is not running\n", Console::FG_RED);
        }

        return ExitCode::OK;
    }

    /**
     * Show WebSocket server logs
     */
    public function actionLogs($lines = 50)
    {
        if (!file_exists($this->logFile)) {
            $this->stdout("Log file not found: " . $this->logFile . "\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Last $lines lines from WebSocket log:\n", Console::FG_CYAN);
        $this->stdout(str_repeat("-", 60) . "\n");

        $command = "tail -n $lines " . escapeshellarg($this->logFile);
        system($command);

        return ExitCode::OK;
    }

    /**
     * Start server in foreground mode
     */
    private function startForeground()
    {
        $serverFile = Yii::getAlias('@common/websocket/server.php');

        if (!file_exists($serverFile)) {
            $this->stdout("Server file not found: $serverFile\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Check if ReactPHP is installed
        if (!class_exists('React\EventLoop\Loop')) {
            $this->stdout("ReactPHP not found. Please run: composer require react/socket react/http\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("WebSocket server starting in foreground mode...\n");
        $this->stdout("Press Ctrl+C to stop\n");

        // Save PID
        $this->savePid(getmypid());

        // Include and run server
        include $serverFile;

        return ExitCode::OK;
    }

    /**
     * Start server as daemon
     */
    private function startDaemon()
    {
        $serverFile = Yii::getAlias('@common/websocket/server.php');

        if (!file_exists($serverFile)) {
            $this->stdout("Server file not found: $serverFile\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Check if ReactPHP is installed
        if (!class_exists('React\EventLoop\Loop')) {
            $this->stdout("ReactPHP not found. Please run: composer require react/socket react/http\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Fork process
        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->stdout("Failed to fork process\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        } elseif ($pid) {
            // Parent process
            $this->savePid($pid);
            $this->stdout("WebSocket server started as daemon (PID: $pid)\n", Console::FG_GREEN);
            $this->stdout("WebSocket: ws://localhost:8900\n");
            $this->stdout("HTTP notifications: http://localhost:8901\n");
            $this->stdout("Use 'php yii websocket/status' to check server status\n");
            $this->stdout("Use 'php yii websocket/logs' to view logs\n");
            return ExitCode::OK;
        } else {
            // Child process - become daemon
            posix_setsid();

            // Redirect output to log file
            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);

            $stdin = fopen('/dev/null', 'r');
            $stdout = fopen($this->logFile, 'a');
            $stderr = fopen($this->logFile, 'a');

            // Include and run server
            include $serverFile;

            exit(0);
        }
    }

    /**
     * Check if server is running
     */
    private function isRunning()
    {
        $pid = $this->getPid();
        if (!$pid) {
            return false;
        }

        return posix_kill($pid, 0);
    }

    /**
     * Get PID from file
     */
    private function getPid()
    {
        if (!file_exists($this->pidFile)) {
            return false;
        }

        $pid = (int)file_get_contents($this->pidFile);
        return $pid > 0 ? $pid : false;
    }

    /**
     * Save PID to file
     */
    private function savePid($pid)
    {
        file_put_contents($this->pidFile, $pid);
    }

    /**
     * Remove PID file
     */
    private function removePidFile()
    {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }
}