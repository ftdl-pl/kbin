<?php declare(strict_types=1);

namespace App\Markdown\CommonMark;

use League\CommonMark\Inline\Element\Link;
use League\CommonMark\Inline\Parser\InlineParserInterface;
use League\CommonMark\InlineParserContext;

/**
 * Parses links like /u/foo, w/bar, etc.
 */
abstract class AbstractLocalLinkParser implements InlineParserInterface
{
    final public function getCharacters(): array
    {
        return ['/', $this->getPrefix()];
    }

    /**
     * Return a single-character prefix.
     */
    abstract public function getPrefix(): string;

    final public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        $previousChar = $cursor->peek(-1);

        if ($previousChar !== null && !preg_match('!^\s+$!', $previousChar)) {
            return false;
        }

        $previousState = $cursor->saveState();

        if ($this->getPrefix() === null) {
            return false;
        }

        if ($this->getApRegex() && $match = $cursor->match($this->getApRegex())) {
            $name = $match;
        } else {
            $name = $cursor->match($this->getRegex());
        }

        if (null === $name) {
            $cursor->restoreState($previousState);

            return false;
        }

        $link = new Link(
            $this->getUrl($name), $this->getHandle($name), $this->getName($name)
        );

        $inlineContext->getContainer()->appendChild($link);

        return true;
    }

    private function getHandle(string $suffix): string
    {
        if (substr_count($suffix, '@') == 2) {
            return '@'.explode('@', $suffix)[1];
        }

        return $suffix;
    }

    protected function getName(string $suffix): string
    {
        return $suffix;
    }

    abstract public function getRegex(): string;

    abstract public function getApRegex(): ?string;

    /**
     * Generates a URL based on the extracted suffix.
     */
    abstract public function getUrl(string $suffix): string;
}
