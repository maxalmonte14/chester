<?php

declare(strict_types=1);

namespace Chester\Services;

use Chester\DTO\CategoryDTO;
use Chester\DTO\DefinitionDto;
use Chester\DTO\ExampleSentenceDTO;
use Chester\DTO\LinkDto;
use Chester\DTO\OtherFormDto;
use Chester\DTO\SenseDTO;
use Chester\DTO\WordDto;
use Chester\Exceptions\UnableToFetchLinksException;
use Chester\Exceptions\UnableToRetrieveWordListException;
use DOMNode;
use Exception;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JishoCrawlerService
{
    /**
     * @var array<string>
     */
    private array $excludeCategories = [
        'Place',
        'Wikipedia definition',
    ];

    /**
     * @var array<string>
     */
    private array $knownCategoriesIncludingComma = [
        'Expressions (phrases, clauses, etc.)',
        'Noun, used as a prefix',
        'Noun, used as a suffix',
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CrawlerFactory      $crawlerFactory,
    ) {}

    /**
     * @return array<LinkDto>
     * @throws UnableToFetchLinksException
     */
    public function getLinksFromPage(string $link): array
    {
        try {
            $response = $this->client->request('GET', $link);
            /** @var array<LinkDto> */
            $links = $this->crawlerFactory::fromString($response->getContent())
                        ->filter('.concept_light.clearfix')
                        ->each(function (Crawler $node) {
                            $word = $node->filter('span.text')->first()->text();
                            $link = $node->filter('a.light-details_link')->first()->attr('href');

                            if (!is_null($link)) {
                                return new LinkDto($link, $word);
                            }
                        });

            return $links;
        } catch (Exception) {
            throw new UnableToFetchLinksException();
        }
    }

    /**
     * @param  array<LinkDto> $links
     * @throws UnableToRetrieveWordListException
     * 
     * @return array<WordDto>
     */
    public function getWords(array $links): array
    {
        try {
            $words  = [];

            foreach ($links as $link) {
                $senses   = [];
                $tags     = [];
                $response = $this->client->request('GET', sprintf('https:%s', $link->url));
                $crawler  = $this->crawlerFactory::fromString($response->getContent());
                $kana     = $this->getKana($crawler->getNode(0));
                /** @var array<string> */
                $meanings = $crawler
                                ->filter('.meanings-wrapper div.meaning-wrapper')
                                ->each(function (Crawler $crawler) use (&$senses, &$tags) {
                                    $previousSibling = $crawler->previousAll();

                                    if ($previousSibling->text() == 'Notes') {
                                        return;
                                    }

                                    $meaningNode = $crawler->filter('span.meaning-meaning')->first();
                                    $sensesNode  = $meaningNode->siblings()->last()->getNode(0);
                                    $senses[]    = $this->getSenses($sensesNode);
                                    $tags[]      = $previousSibling->attr('class') == 'meaning-tags'
                                                    ? $previousSibling->text()
                                                    : '';

                                    return $meaningNode->text();
                                });
                $exampleSentence = $this->getExampleSentence($crawler->filter('.sentence')->getNode(0));
                $words[] = $this->makeWord(array_filter($meanings), $tags, $senses, trim($link->text), $kana, $exampleSentence);
            }

            return $words;
        } catch (Exception) {
            throw new UnableToRetrieveWordListException();
        }
    }

    private function getKana(?DOMNode $node): ?string
    {
        if (is_null($node)) {
            return null;
        }

        $furigana = $this->crawlerFactory::fromNode($node)->filter('.furigana');
        $rubyAnnotation = $furigana->filter('ruby rt');
        
        if ($rubyAnnotation->count() > 0) {
            return $rubyAnnotation->text();
        }

        $results  = $furigana
                        ->siblings()
                        ->first()
                        ->children()
                        ->filter('span')
                        ->each(fn(Crawler $crawler) => $crawler->text());

        $furigana
            ->children()
            ->filter('span')
            ->each(function (Crawler $crawler, int $key) use (&$results) {
                if ($crawler->text() == '') {
                    return;
                }

                array_splice($results, $key, 0, $crawler->text());
            });

        return trim(join($results));
    }

    /**
     * @return array<CategoryDTO>
     */
    private function getCategories(string $categories): array
    {
        $extraCategories = [];

        foreach ($this->knownCategoriesIncludingComma as $knownCategory) {
            if (str_contains($categories, $knownCategory)) {
                $categories = str_replace($knownCategory, '', $categories);
                $extraCategories[] = trim(str_replace(',', '', $knownCategory));
            }
        }

        $filteredArray = array_filter(explode(',', $categories), fn($c) => trim($c) != '');

        return array_map(
            fn ($category) => new CategoryDTO(ucfirst(trim($category))),
            array_merge(array_unique($filteredArray), $extraCategories)
        );
    }

    private function getExampleSentence(?DOMNode $node): ?ExampleSentenceDTO
    {
        if (is_null($node)) {
            return null;
        }

        $translation = '';
        $sentence = '';

        $this->crawlerFactory::fromNode($node)
            ->each(function (Crawler $crawler) use (&$translation, &$sentence) {
                $translation = $crawler->children()->last()->text();
                $sentencePieces = $crawler
                                    ->filter('.sentence ul li .unlinked')
                                    ->each(fn(Crawler $crawler) => $crawler->text());
                $sentence = (join($sentencePieces) == '') ? '' : join($sentencePieces).'。';
        });

        return new ExampleSentenceDTO(trim($sentence), $translation);
    }

    /**
     * @return array<SenseDTO>
     */
    private function getSenses(?DOMNode $node): array
    {
        if (is_null($node)) {
            return [];
        }

        /** @var array<SenseDTO> */
        $results = $this->crawlerFactory::fromNode($node)
                    ->children()
                    ->filter('span.sense-tag')
                    ->filter('span:not(.tag-see_also)')
                    ->filter('span:not(.tag-antonym)')
                    ->each(fn(Crawler $node) => new SenseDTO($node->text()));

        return $results;
    }

    /**
     * @return array<OtherFormDto>
     */
    private function getOtherForms(string $otherForms): array
    {
        return array_map(
            fn($form) => new OtherFormDto(trim($form)),
            explode('、', $otherForms)
        );
    }

    /**
     * @param array<string>          $meanings
     * @param array<string>          $tags
     * @param array<array<SenseDTO>> $senses
     */
    private function makeWord(
        array $meanings,
        array $tags,
        array $senses,
        string $word,
        ?string $kana,
        ?ExampleSentenceDTO $exampleSentence
    ): WordDto
    {
        $definitions = [];
        $otherForms = [];

        foreach ($meanings as $i => $meaning) {
            if ($tags[$i] == 'Other forms') {
                $otherForms = $this->getOtherForms($meaning);

                continue;
            }

            $categories = $this->getCategories($tags[$i]);

            if (
                isset($categories[0]) &&
                in_array($categories[0]->name, $this->excludeCategories)
            ) {
                continue;
            }

            $definitions[] = new DefinitionDto($meaning, $senses[$i], $categories);
        }

        return new WordDto($word, $kana, $definitions, $otherForms, $exampleSentence);
    }
}
