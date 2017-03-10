<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Console\Helper;

use Hail\Console\Exception\LogicException;
use Hail\Console\Input\InputInterface;
use Hail\Console\Output\OutputInterface;
use Hail\Console\Question\ChoiceQuestion;
use Hail\Console\Question\ConfirmationQuestion;
use Hail\Console\Question\Question;
use Hail\Console\Style\SymfonyStyle;
use Hail\Console\Formatter\OutputFormatter;

/**
 * Symfony Style Guide compliant question helper.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class SymfonyQuestionHelper extends QuestionHelper
{
    /**
     * {@inheritdoc}
     */
    public function ask(InputInterface $input, OutputInterface $output, Question $question)
    {
        $validator = $question->getValidator();
        $question->setValidator(function ($value) use ($validator) {
            if (null !== $validator) {
                $value = $validator($value);
            } else {
                // make required
                if (!is_array($value) && !is_bool($value) && 0 === strlen($value)) {
                    throw new LogicException('A value is required.');
                }
            }

            return $value;
        });

        return parent::ask($input, $output, $question);
    }

    /**
     * {@inheritdoc}
     */
    protected function writePrompt(OutputInterface $output, Question $question)
    {
        $text = OutputFormatter::escapeTrailingBackslash($question->getQuestion());
        $default = $question->getDefault();

        switch (true) {
            case null === $default:
                $text = sprintf(' <info>%s</info>:', $text);

                break;

            case $question instanceof ConfirmationQuestion:
                $text = sprintf(' <info>%s (yes/no)</info> [<comment>%s</comment>]:', $text, $default ? 'yes' : 'no');

                break;

            case $question instanceof ChoiceQuestion && $question->isMultiselect():
                $choices = $question->getChoices();
                $default = explode(',', $default);

                foreach ($default as $key => $value) {
                    $default[$key] = $choices[trim($value)];
                }

                $text = sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, OutputFormatter::escape(implode(', ', $default)));

                break;

            case $question instanceof ChoiceQuestion:
                $choices = $question->getChoices();
                $text = sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, OutputFormatter::escape($choices[$default]));

                break;

            default:
                $text = sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, OutputFormatter::escape($default));
        }

        $output->writeln($text);

        if ($question instanceof ChoiceQuestion) {
            $width = max(array_map('strlen', array_keys($question->getChoices())));

            foreach ($question->getChoices() as $key => $value) {
                $output->writeln(sprintf("  [<comment>%-${width}s</comment>] %s", $key, $value));
            }
        }

        $output->write(' > ');
    }

    /**
     * {@inheritdoc}
     */
    protected function writeError(OutputInterface $output, \Exception $error)
    {
        if ($output instanceof SymfonyStyle) {
            $output->newLine();
            $output->error($error->getMessage());

            return;
        }

        parent::writeError($output, $error);
    }
}
