<?php

declare(strict_types=1);

namespace Chester\Services;

use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

final class CrawlerFactory
{
    public static function fromString(string $content): Crawler
    {
        return new Crawler($content);
    }

    public static function fromNode(DOMNode $node): Crawler
    {
        return new Crawler($node);
    }
}