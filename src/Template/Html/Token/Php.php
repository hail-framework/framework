<?php

namespace Hail\Template\Html\Token;

class Php extends AbstractToken
{
    public function __construct(TokenInterface $parent = null)
    {
        parent::__construct(TokenInterface::PHP, $parent);
    }

    public function parse(string $html): string
    {
        $return = $this->parseHtml($html, 6, '?>');

        return $return ?? '';
    }

    public function __toString(): string
    {
        if (\strpos($this->value, "\n") !== false) {
            return "<?php\n{$this->value}\n?>";
        }

        return "<?php {$this->value} ?>";
    }
}
