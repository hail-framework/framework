<?php
namespace Hail\Console\Component\Progress;

use Hail\Console\Formatter;
use Hail\Console\ConsoleInfo\ConsoleInfoFactory;
use Hail\Console\Ansi\Colors;

class ProgressBar implements ProgressReporter
{
    protected $terminalWidth = 78;

    protected $formatter;

    protected $stream;

    protected $console;

    protected $leftDecorator = '[';

    protected $rightDecorator = ']';

    protected $columnDecorator = ' | ';

    protected $barCharacter = '#';

    protected $descFormat = '%finished%/%total% %unit% | %percentage% | %eta_period%';

    protected $unit;

    protected $title;

    protected $start;

    protected $etaTime = '--:--';

    protected $etaPeriod = '--';

    public function __construct($stream)
    {
        $this->stream = $stream;
        $this->formatter = Formatter::getInstance();

        $this->console = ConsoleInfoFactory::create();
        $this->updateLayout();
    }

    public function updateLayout()
    {
        if ($this->console) {
            $this->terminalWidth = $this->console->getColumns();
        }
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setUnit($unit)
    {
        $this->unit = $unit;
    }

    public function start($title = null)
    {
        if ($title) {
            $this->setTitle($title);
        }
        $this->start = microtime(true);
    }

    public function update($finished, $total)
    {
        $percentage = $total > 0 ? round($finished / $total, 2) : 0.0;
        $trigger = $finished % 3;

        if ($trigger) {
            $this->etaTime = date('H:i', ETACalculator::calculateEstimatedTime($finished, $total, $this->start, microtime(true)));
            $this->etaPeriod = ETACalculator::calculateEstimatedPeriod($finished, $total, $this->start, microtime(true));
        }
        $desc = str_replace([
            '%finished%', '%total%', '%unit%', '%percentage%', '%eta_time%', '%eta_period%',
        ], [
            $finished,
            $total,
            $this->unit,
            ($percentage * 100) . '%',
            'ETA: ' . $this->etaTime,
            'ETA: ' . $this->etaPeriod,
        ], $this->descFormat);

        $barSize = $this->terminalWidth
            - mb_strlen($desc)
            - mb_strlen($this->leftDecorator)
            - mb_strlen($this->rightDecorator)
            - mb_strlen($this->columnDecorator)
            ;

        if ($this->title) {
            $barSize -= (mb_strlen($this->title) + mb_strlen($this->columnDecorator));
        }

        $sharps = ceil($barSize * $percentage);

        fwrite($this->stream, "\r"
            . ($this->title ? $this->title . $this->columnDecorator : '')
            . $this->formatter->decorate($this->leftDecorator, ['fg' => $trigger ? 'purple' : 'light_purple'])
            . $this->formatter->decorate(str_repeat($this->barCharacter, $sharps), ['fg' => $trigger ? 'purple' : 'light_purple'])
            . str_repeat(' ', max($barSize - $sharps, 0))
            . $this->formatter->decorate($this->rightDecorator, ['fg' => $trigger ? 'purple' : 'light_purple'])
            . $this->columnDecorator
            . $this->formatter->decorate($desc, ['fg' => $trigger ? 'light_gray' : 'white'])
            );

        // hide cursor
        // fputs($this->stream, "\033[?25l");

        // show cursor
        // fputs($this->stream, "\033[?25h");
    }

    public function finish($title = null)
    {
        if ($title) {
            $this->setTitle($title);
        }
        fwrite($this->stream, PHP_EOL);
    }
}
