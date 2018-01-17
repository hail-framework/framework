<?php

namespace Hail\Template\Tokenizer\Token;

interface TokenInterface
{
    public const ROOT = 'root';
    public const CDATA = 'cdata';
    public const COMMENT = 'comment';
    public const DOCTYPE = 'doctype';
    public const ELEMENT = 'element';
    public const PHP = 'php';
    public const TEXT = 'text';

    public const ALL_TYPE = [
        self::ROOT,
        self::CDATA,
        self::COMMENT,
        self::DOCTYPE,
        self::ELEMENT,
        self::PHP,
        self::TEXT,
    ];

    /**
     * Will return the nesting depth of the token.
     *
     * @return int
     */
    public function getDepth(): int;

    /**
     * Will return the token line number.
     *
     * @return int
     */
    public function getLine(): int;

    /**
     * Will return the token line position.
     *
     * @return int
     */
    public function getPosition(): int;

    /**
     * Will return true of the parent should be closed automatically.
     *
     * @param string $html
     *
     * @return boolean
     */
    public function isClosingElementImplied(string $html): bool;

    /**
     * Will parse this token.
     *
     * @param string $html
     *
     * @return string Remaining HTML.
     */
    public function parse(string $html): string;

    /**
     * Will return the parent token or null if none.
     *
     * @return TokenInterface|null
     */
    public function getParent();

    /**
     * @param $parent
     *
     * @return mixed
     */
    public function setParent($parent);

    /**
     * Will return the type of token.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Will return the contents of the token.
     *
     * @return string
     */
    public function getValue(): string;

    public function setValue(string $value): void;

    /**
     * Will convert this token to an array structure.
     *
     * @return array
     */
    public function toArray(): array;

    public function __toString(): string;

    public function getPreviousSibling(): ?TokenInterface;

    public function getNextSibling(): ?TokenInterface;

    public function insertAfterSelf(TokenInterface $new): void;

    public function insertBeforeSelf(TokenInterface $new): void;

    public function remove(): void;

    public function hasChildren(): bool;

    public function getChildren(): array;

    public function findChild(TokenInterface $element): ?int;

    public function findPrevious(TokenInterface $element): ?TokenInterface;

    public function findNext(TokenInterface $element): ?TokenInterface;

    public function insertAfter(TokenInterface $new, TokenInterface $ref): void;

    public function insertBefore(TokenInterface $new, TokenInterface $ref): void;

    public function appendChild(TokenInterface $new): void;

    public function removeChildren(): void;

    public function removeChild(TokenInterface $element): void;
}
