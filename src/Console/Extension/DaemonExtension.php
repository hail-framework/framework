<?php

namespace Hail\Console\Extension;

use Hail\Console\Exception\ExtensionException;

class DaemonExtension extends AbstractExtension
{
    /**
     * @var boolean Detach from shell.
     */
    protected $detach = false;

    protected $chdir = false;

    protected static $supported;

    public static function isSupported()
    {
        if (self::$supported === null) {
            self::$supported = \function_exists('\pcntl_fork');
        }

        return self::$supported;
    }

    public function finish()
    {
        $pidFile = $this->getPidFilePath();
        if (\file_exists($pidFile)) {
            @unlink($pidFile);
        }
    }

    public function execute()
    {
        if (!$this->isAvailable()) {
            throw new ExtensionException('pcntl_fork() is not supported.');
        }

        $this->prepareLogger();
        $this->daemonize();
    }

    /**
     * Call this method if you don't want to close STDIN, STDOUT and STDERR on making a daemon process.
     */
    public function detach()
    {
        $this->detach = true;
    }

    /**
     * Call this method if you want to change the current directory on making a daemon process.
     */
    protected function changeDirectory()
    {
        $this->chdir = true;
    }

    public function getPidFilePath()
    {
        if ($pidFile = $this->getCommandOption('pid-file')) {
            return $pidFile;
        }

        $pid = \getmypid();
        $command = $this->getCommand();
        $pidFile = $command ? $command->name() : $pid;

        return $this->config['pid_dir'] . "/$pidFile.pid";
    }

    public function init()
    {
        $this->addOption('pid-file?', '(daemon) Path of pid file.');
        $this->addOption('log-path?', '(daemon) Path of log file when running with daemon extension.');
        $this->addOption('detach', '(daemon) Detach from the shell.');
    }

    protected function prepareLogger()
    {
        $logPath = $this->getLogPath();
        $logger = $this->getOutput();

        if (!$logPath || !$logger) {
            return;
        }

        $resource = \fopen($logPath, 'a+b');
        if ($resource === false) {
            throw new ExtensionException("Can't open file: $logPath", $this);
        }

        $logger->setStream($resource);
    }

    protected function daemonize()
    {
        if ($this->detach || $this->getCommandOption('detach')) {
            $this->getOutput()->debug('forking process to background..');
            // The return value of pcntl_fork:
            //
            // On success, the PID of the child process is returned in the parent's
            // thread of execution, and a 0 is returned in the child's thread of
            // execution. On failure, a -1 will be returned in the parent's
            // context, no child process will be created, and a PHP error is
            // raised.
            switch (\pcntl_fork()) {
                case -1:
                    throw new ExtensionException('pcntl_fork() failed');

                // child process
                case 0:
                    break;

                // exit parent process
                default:
                    if (!\fclose(STDIN)) {
                        throw new ExtensionException('fclose(STDIN) failed');
                    }

                    if (!\fclose(STDOUT)) {
                        throw new ExtensionException('fclose(STDOUT) failed');
                    }

                    if (!\fclose(STDERR)) {
                        throw new ExtensionException('fclose(STDERR) failed');
                    }
                    exit(0);
            }
        }

        // The execution here runs in child process
        if ($this->savePid() === false) {
            throw new ExtensionException('pid file create failed');
        }

        if ($this->chdir) {
            $this->chdir();
        }
    }

    private function chdir()
    {
        if ($this->chdir && !\chdir('/')) {
            throw new ExtensionException('chdir failed');
        }
    }

    protected function savePid()
    {
        $pidFile = $this->getPidFilePath();
        $pid = \getmypid();
        $this->getOutput()->debug("pid {$pid} saved in $pidFile");

        return \file_put_contents($pidFile, $pid);
    }

    protected function getLogPath()
    {
        if ($logPath = $this->getCommandOption('log-path')) {
            return $logPath;
        }

        return $this->getApplicationOption('log-path') ?: null;
    }

    protected function getApplicationOption($key)
    {
        if (!$this->hasApplication()) {
            return null;
        }

        return $this->getCommand()->getApplication()->getOption($key);
    }

    private function hasApplication()
    {
        return $this->getCommand() && $this->getCommand()->hasApplication();
    }
}
