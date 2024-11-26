<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;

final class CrawlerFactory
{
    public static function fromString(string $content): Crawler
    {
        return new Crawler($content);
    }

    public static function fromNode(\DOMNode $node): Crawler
    {
        return new Crawler($node);
    }

    public static function fromNodeList(\DOMNodeList $nodeList): Crawler
    {
        return new Crawler($nodeList);
    }
}