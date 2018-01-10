<?php

namespace Hail\Console\Logger;

class LogAction
{
    public $title;

    public $desc;

    public $status;

    protected $logger;

    protected $actionColumnWidth = 38;

    public function __construct($logger, $title, $desc, $status = 'waiting')
    {
        $this->logger = $logger;
        $this->title = $title;
        $this->desc = $desc;
        $this->status = $status;

        fwrite($this->logger->fd, "\e[?25l"); //hide
    }

    public function setStatus($status, $style = 'green')
    {
        $this->status = $status;
        $this->update($style);
    }

    public function setActionColumnWidth($width)
    {
        $this->actionColumnWidth = $width;
    }

    protected function update($style = 'green')
    {
        $padding = max($this->actionColumnWidth - strlen($this->title), 1);
        $buf = sprintf('  %s % -20s',
            $this->logger->formatter->format(sprintf('%s', $this->title), $style).str_repeat(' ', $padding),
            $this->status
        );
        fwrite($this->logger->fd, $buf."\r");
        fflush($this->logger->fd);
    }

    public function finalize()
    {
        fwrite($this->logger->fd, "\n");
        fflush($this->logger->fd);
        fwrite($this->logger->fd, "\e[?25h"); // show
    }

    public function done()
    {
        $this->setStatus('done');
        $this->finalize();
    }
}