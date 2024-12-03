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
    private array $excludeCategories = [
        'Place',
        'Wikipedia definition',
    ];

    private array $knownCategoriesIncludingComma = [
        'Expressions (phrases, clauses, etc.)',
        'Noun, used as a prefix',
        'Noun, used as a suffix',
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CrawlerFactory $crawlerFactory,
    ) {}

    /**
     * @return array<LinkDto>
     * @throws UnableToFetchLinksException
     */
    public function getLinksFromPage(string $link): array
    {
        try {
            $response = $this->client->request('GET', $link);
            $crawler  = $this->crawlerFactory::fromString($response->getContent());

            return $crawler->filter('.concept_light.clearfix')->each(function (Crawler $node) {
                $word = $node->filter('span.text')->first()->text();
                $link = $node->filter('a.light-details_link')->first()->attr('href');

                return new LinkDto($link, $word);
            });
        } catch (Exception) {
            throw new UnableToFetchLinksException();
        }
    }

    /**
     * @param array<LinkDto> $links
     * @throws UnableToRetrieveWordListException
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

                                    return trim($meaningNode->text());
                                });
                $exampleSentence = $this->getExampleSentence($crawler->filter('.sentence')->getNode(0));
                $words[] = $this->makeWord(array_filter($meanings), $tags, $senses, trim($link->text), $kana, $exampleSentence);
            }

            return $words;
        } catch (Exception $exception) {
            throw new UnableToRetrieveWordListException();
        }
    }

    private function getKana(DOMNode $node): string
    {
        $furigana = $this->crawlerFactory::fromNode($node)->filter('.furigana');
        $results = $furigana
                        ->siblings()
                        ->first()
                        ->children()
                        ->filter('span')
                        ->each(fn(Crawler $crawler) => $crawler->text());

        $furigana
            ->children()
            ->filter('span')
            ->each(function (Crawler $crawler, $key) use (&$results) {
                if (trim($crawler->text()) == '') {
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

    /**
     * @param array<SenseDTO>    $senses
     * @param array<CategoryDTO> $categories
     */
    private function getDefinition(string $definition, array $senses, array $categories): DefinitionDto
    {
        return new DefinitionDto(
            trim($definition),
            $senses,
            $categories
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
            $translation = trim($crawler->children()->last()->text());
            $sentencePieces = $crawler
                                ->filter('.sentence ul li .unlinked')
                                ->each(fn(Crawler $crawler) => $crawler->text());
            $sentence = (join($sentencePieces) == '') ? '' : join($sentencePieces).'。';
        });

        return new ExampleSentenceDTO(trim($sentence), trim($translation));
    }

    /**
     * @return array<SenseDTO>
     */
    private function getSenses(?DOMNode $node): array
    {
        if (is_null($node)) {
            return [];
        }

        return $this->crawlerFactory::fromNode($node)
                    ->children()
                    ->filter('span.sense-tag')
                    ->filter('span:not(.tag-see_also)')
                    ->filter('span:not(.tag-antonym)')
                    ->each(fn(Crawler $node) => new SenseDTO(trim($node->text())));
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
     * @param array<string> $meanings
     * @param array<string> $tags
     * @param array<array<SenseDTO>> $senses
     */
    private function makeWord(
        array $meanings,
        array $tags,
        array $senses,
        string $word,
        string $kana,
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

            $definitions[] = $this->getDefinition($meaning, $senses[$i], $categories);
        }

        return new WordDto($word, $kana, $definitions, $otherForms, $exampleSentence);
    }
}
