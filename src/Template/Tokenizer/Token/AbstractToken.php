<?php

namespace Hail\Template\Tokenizer\Token;

use Hail\Template\Tokenizer\Tokenizer;

abstract class AbstractToken implements TokenInterface
{
    /** @var int */
    private $depth;

    /** @var int */
    protected $line;

    /** @var null|TokenInterface */
    protected $parent;

    /** @var int */
    protected $position;

    /** @var string */
    protected $type;

    /** @var string */
    protected $value;

    /** @var TokenInterface[] */
    protected $children = [];

    /**
     * Constructor
     *
     * @param string              $type
     * @param TokenInterface|null $parent
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $type, TokenInterface $parent = null)
    {
        if (!$this->isValidType($type)) {
            throw new \InvalidArgumentException('Invalid type: ' . $type);
        }

        $this->depth = 0;
        if ($parent instanceof TokenInterface) {
            $this->depth = $parent->getDepth() + 1;
        }

        $this->line = 0;
        $this->position = 0;
        $this->parent = $parent;
        $this->type = $type;
        $this->value = '';
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Getter for 'line'.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    public function isClosingElementImplied(string $html): bool
    {
        return false;
    }

    /**
     * @return TokenInterface|null
     */
    public function getParent(): ?TokenInterface
    {
        return $this->parent;
    }

    /**
     * @param $parent
     *
     * @return mixed|void
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    /**
     * Getter for 'position'.
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Getter for 'throwOnError'.
     *
     * @return boolean
     */
    protected function getThrowOnError(): bool
    {
        return Tokenizer::getThrowOnError();
    }

    /**
     * Getter for 'type'.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Getter for "value"
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    protected function isValidType(string $type): bool
    {
        return \in_array($type, self::ALL_TYPE, true);
    }

    protected function setTokenPosition(string $html)
    {
        $positionArray = Tokenizer::getPosition($html);
        $this->line = $positionArray['line'];
        $this->position = $positionArray['position'];
    }

    protected function parseHtml(string $html, int $startPos, string $endTag): ?string
    {
        $html = \ltrim($html);
        $this->setTokenPosition($html);

        // Parse token.
        $posOfEndOfCData = \mb_strpos($html, $endTag);
        if ($posOfEndOfCData === false) {
            return null;
        }

        $this->value = \trim(\mb_substr($html, $startPos, $posOfEndOfCData - $startPos));

        return \mb_substr($html, $posOfEndOfCData + \mb_strlen($endTag));
    }

    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'value' => $this->value,
            'line' => $this->getLine(),
            'position' => $this->getPosition(),
        ];

        if ($this->children !== []) {
            $result['children'] = [];
            foreach ($this->children as $child) {
                $result['children'][] = $child->toArray();
            }
        }

        return $result;
    }

    /**
     * @return Element|null
     */
    public function getPreviousSibling(): ?TokenInterface
    {
        $parent = $this->getParent();
        if ($parent === null || !($parent instanceof Element)) {
            return null;
        }

        return $parent->findPrevious($this);
    }

    /**
     * @return Element|null
     */
    public function getNextSibling(): ?TokenInterface
    {
        $parent = $this->getParent();
        if ($parent === null || !($parent instanceof Element)) {
            return null;
        }

        return $parent->findNext($this);
    }

    /**
     * @param TokenInterface $new
     */
    public function insertAfterSelf(TokenInterface $new): void
    {
        $parent = $this->getParent();
        if ($parent === null || !($parent instanceof Element)) {
            throw new \RuntimeException('Current token is top node or parent node is not ' . __NAMESPACE__ . '\\Element.');
        }

        $parent->insertAfter($new, $this);
    }

    /**
     * @param TokenInterface $new
     */
    public function insertBeforeSelf(TokenInterface $new): void
    {
        $parent = $this->getParent();
        if ($parent === null || !($parent instanceof Element)) {
            throw new \RuntimeException('Current token is top node or parent node is not ' . __NAMESPACE__ . '\\Element.');
        }

        $parent->insertBefore($new, $this);
    }

    public function remove(): void
    {
        $parent = $this->getParent();
        if ($parent === null || !($parent instanceof Element)) {
            throw new \RuntimeException('Current token is top node or parent node is not ' . __NAMESPACE__ . '\\Element.');
        }

        $parent->removeChild($this);
    }

    /**
     * Getter for 'children'.
     *
     * @return array
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @return boolean
     */
    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * @param TokenInterface $element
     *
     * @return null|int
     */
    public function findChild(TokenInterface $element): ?int
    {
        $index = \array_search($element, $this->children, true);

        return $index === false ? null : $index;
    }

    public function findPrevious(TokenInterface $element): ?TokenInterface
    {
        $index = $this->findChild($element);

        if ($index !== null) {
            return $this->children[$index - 1] ?? null;
        }

        return null;
    }

    /**
     * @param TokenInterface $element
     *
     * @return Element|null
     */
    public function findNext(TokenInterface $element): ?TokenInterface
    {
        $index = $this->findChild($element);

        if ($index !== null) {
            return $this->children[$index + 1] ?? null;
        }

        return null;
    }

    /**
     * @param TokenInterface $new
     * @param TokenInterface $ref
     */
    public function insertAfter(TokenInterface $new, TokenInterface $ref): void
    {
        $index = $this->findChild($ref) ?? 0;
        ++$index;

        $new->setParent($this);

        if (!isset($this->children[$index])) {
            $this->children[] = $new;
        } else {
            \array_splice($this->children, $index, 0, $new);
        }
    }

    /**
     * @param TokenInterface $new
     * @param TokenInterface $ref
     */
    public function insertBefore(TokenInterface $new, TokenInterface $ref): void
    {
        $index = $this->findChild($ref) ?? 0;

        $new->setParent($this);

        \array_splice($this->children, $index, 0, $new);
    }

    public function appendChild(TokenInterface $new): void
    {
        $new->setParent($this);

        $this->children[] = $new;
    }

    public function removeChildren(): void
    {
        $this->children = [];
    }

    /**
     * @param TokenInterface $element
     */
    public function removeChild(TokenInterface $element): void
    {
        $index = $this->findChild($element);

        if ($index !== null) {
            \array_splice($this->children, $index, 1);
        }
    }

    public function __clone()
    {
        if ($this->children !== []) {
            $children = [];
            foreach ($this->children as $child) {
                $children[] = clone $child;
            }

            $this->children = $children;
        }

        $this->parent = null;
        $this->line = null;
        $this->position = null;
    }
}