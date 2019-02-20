<?php

namespace Hail\Console\Component;

use Hail\Console\Formatter;
use Hail\Console\IO\Console;
use Hail\Console\IO\Factory;
use Hail\Console\IO\ReadlineConsole;

/**
 * Prompter class
 */
class Prompter
{
    private $style = 'question';

    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @var Console
     */
    private $console;

    public function __construct()
    {
        $this->formatter = Formatter::getInstance();
        $this->console = Factory::console();
    }

    /**
     * set prompt style
     */
    public function setStyle($style)
    {
        $this->style = $style;

        return $this;
    }

    /**
     * @param string    $prompt
     * @param bool|null $yes
     *
     * @return bool
     */
    public function confirm(string $prompt, bool $yes = null): bool
    {
        $default = null;
        if ($yes === true) {
            $default = 'Y';
        } elseif ($yes === false) {
            $default = 'n';
        }

        return 'Y' === $this->ask($prompt, ['Y', 'n'], $default);
    }

    /**
     * Show prompt with message, you can provide valid options
     * for the simple validation.
     *
     * @param string      $prompt
     * @param array       $validAnswers an array of valid values (optional)
     * @param string|null $default
     *
     * @return null|string
     */
    public function ask(string $prompt, array $validAnswers = [], string $default = null)
    {
        if ($validAnswers) {
            $answers = [];
            foreach ($validAnswers as $v) {
                if ($default && $default === $v) {
                    $answers[] = '[' . $v . ']';
                } else {
                    $answers[] = $v;
                }
            }
            $prompt .= ' (' . implode('/', $answers) . ')';
        }

        $prompt .= ' ';

        if ($this->style) {
            echo $this->formatter->getStartMark($this->style);
        }

        $answer = null;
        while (true) {
            $answer = trim($this->console->readLine($prompt));
            if ($validAnswers) {
                if (in_array($answer, $validAnswers, true)) {
                    break;
                }

                if (trim($answer) === '' && $default) {
                    $answer = $default;
                    break;
                }
                continue;
            }
            break;
        }

        if ($this->style) {
            echo $this->formatter->getClearMark();
        }

        return $answer;
    }

    /**
     * Show password prompt with a message.
     *
     * @param string $prompt
     *
     * @return mixed|string
     */
    public function password(string $prompt)
    {
        if ($this->style) {
            echo $this->formatter->getStartMark($this->style);
        }

        $result = $this->console->readPassword($prompt);

        if ($this->style) {
            echo $this->formatter->getClearMark();
        }

        return $result;
    }

    /**
     * Provide a simple console menu for choices,
     * which gives values an index number for user to choose items.
     *
     * @code
     *
     *      $val = $app->choose('Your versions' , array(
     *          'php-5.4.0' => '5.4.0',
     *          'php-5.4.1' => '5.4.1',
     *          'system' => '5.3.0',
     *      ));
     *      var_dump($val);
     *
     * @code
     *
     * @param  string $prompt Prompt message
     * @param  array  $choices
     *
     * @return mixed  value
     */
    public function choose(string $prompt, array $choices)
    {
        echo "$prompt: \n";

        $choicesMap = [];

        $i = 0;
        if (\Arrays::isAssoc($choices)) {
            foreach ($choices as $choice => $value) {
                $choicesMap[++$i] = $value;
                echo "\t$i: " . $choice . ' => ' . $value . "\n";
            }
        } else {
            //is sequential
            foreach ($choices as $choice) {
                $choicesMap[++$i] = $choice;
                echo "\t$i: $choice\n";
            }
        }

        if ($this->style) {
            echo $this->formatter->getStartMark($this->style);
        }

        $completionItems = array_keys($choicesMap);
        $choosePrompt = "Please Choose 1-$i > ";

        if (ReadlineConsole::isAvailable()) {
            readline_completion_function(function () use ($completionItems) {
                return $completionItems;
            });
        }

        while (true) {
            $answer = (int) trim($this->console->readLine($choosePrompt));

            if (isset($choicesMap[$answer])) {
                if ($this->style) {
                    echo $this->formatter->getClearMark();
                }

                return $choicesMap[$answer];
            }
        }
    }
}
