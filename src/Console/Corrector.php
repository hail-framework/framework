<?php

namespace Hail\Console;

/**
 * Try to correct/suggest user's input
 */
class Corrector
{
    /**
     * Given user's input, ask user to correct it.
     *
     * @param string   $input          user's input
     * @param string[] $possibleTokens candidates of the suggestion
     *
     * @return string corrected input
     */
    public static function correct(string $input, array $possibleTokens = []): string
    {
        $guess = static::match($input, $possibleTokens);
        if ($guess === $input) {
            return $guess;
        }

        return static::askForGuess($guess) ? $guess : $input;
    }

    /**
     * Given user's input, return the best match among candidates.
     *
     * @param string   $input          @see self::correct()
     * @param string[] $possibleTokens @see self::correct()
     *
     * @return string best matched string or raw input if no candidates provided
     */
    public static function match(string $input, array $possibleTokens = []): string
    {
        if (empty($possibleTokens)) {
            return $input;
        }

        $bestSimilarity = -1;
        $bestGuess = $input;
        foreach ($possibleTokens as $possibleToken) {
            similar_text($input, $possibleToken, $similarity);
            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestGuess = $possibleToken;
            }
        }

        return $bestGuess;
    }

    private static function askForGuess(string $guess): bool
    {
        $prompter = new Component\Prompter;

        return $prompter->confirm("Did you mean '$guess'?", true);
    }
}
