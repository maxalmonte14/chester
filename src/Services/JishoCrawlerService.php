<?php

namespace App\Services;

use App\DTO\CategoryDTO;
use App\DTO\DefinitionDto;
use App\DTO\LinkDto;
use App\DTO\OtherFormDto;
use App\DTO\SenseDTO;
use App\DTO\WordDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JishoCrawlerService
{
    private Crawler $crawler;

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
        private array  $definitions = [],
        private string $exampleSentence = '',
        private string $exampleSentenceTranslation = '',
        private string $kana = '',
        private array  $otherForms = [],
        private array  $words = [],
    ) {
        $this->crawler = new Crawler();
    }

    /**
     * @return array<LinkDto>
     */
    public function getLinksFromPage(string $link): array
    {
        try {
            $response = $this->client->request('GET', $link);
        } catch (\Exception $exception) {
            return [];
        }

        $this->crawler->addHtmlContent($response->getContent());
        $links = [];

        foreach ($this->crawler->filter('.concept_light.clearfix') as $domElement) {
            $word = $domElement
                ->childNodes
                ->item(1)
                ->firstElementChild
                ->firstElementChild
                ->lastElementChild
                ->textContent;
            $link = $domElement
                ->childNodes
                ->item(5)
                ->getAttribute('href');
            $links[] = new LinkDto($link, $word);
        }

        return $links;
    }

    /**
     * @param array<LinkDto> $links
     */
    public function getWords(array $links): array
    {
        foreach ($links as $link) {
            $response = $this->client->request('GET', sprintf('https:%s', $link->url));

            $this->crawler->addHtmlContent($response->getContent());

            $this->kana = $this->getKana();

            $this->crawler->filter('.meanings-wrapper div')->each(function (Crawler $crawler) {
                if (
                    $crawler->attr('class') == 'meaning-tags' && $crawler->text() != 'Other forms'
                ) {
                    $meaningDefinition = $crawler->getNode(0)->nextElementSibling->firstChild;
                    $formattedCategories = $this->getCategories(trim($crawler->text()));

                    if (in_array($formattedCategories[0]->name, $this->excludeCategories)) {
                        return;
                    }

                    $this->definitions = array_merge(
                        $this->definitions,
                        $this->getDefinition($meaningDefinition->childNodes, $formattedCategories)
                    );
                } else if (
                    $crawler->attr('class') == 'meaning-tags' && $crawler->text() == 'Other forms'
                ) {
                    {
                        $meaningDefinition = $crawler->getNode(0)->nextElementSibling->firstChild;
                        $this->otherForms = $this->getOtherForms($meaningDefinition->childNodes);
                    }
                } else if ($crawler->attr('class') == 'meaning-wrapper' && $this->exampleSentence == '') {
                    $crawler->filter('.sentence')->each(function (Crawler $crawler) {
                        $this->exampleSentenceTranslation = $crawler->children()->last()->text();

                        $crawler
                            ->filter('.sentence ul li .unlinked')
                            ->each(fn (Crawler $crawler) => $this->exampleSentence .= $crawler->text());

                        $this->exampleSentence .= ($this->exampleSentence == '') ? '' : '。';
                    });
                }
            });

            $this->words[] = new WordDto(
                trim($link->text),
                trim($this->kana),
                $this->definitions,
                $this->otherForms,
                trim($this->exampleSentence),
                trim($this->exampleSentenceTranslation),
            );

            $this->resetProperties();
        }

        return $this->words;
    }

    private function resetProperties(): void
    {
        $this->crawler->clear();

        $this->kana = '';
        $this->definitions = [];
        $this->exampleSentence = '';
        $this->exampleSentenceTranslation = '';
        $this->otherForms = [];
    }

    private function getKana(): string
    {
        $furigana = $this->crawler->filter('.furigana');
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
                if (trim($crawler->text()) != '') {
                    array_splice($results, $key, 0, $crawler->text());
                }
            });

        return join($results);
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
     * @return array<DefinitionDto>
     */
    private function getDefinition(\DOMNodeList $nodeList, array $categories): array
    {
        $definitions = [];
        $definitionElement = (new Crawler($nodeList))->filter('.meaning-meaning')->first();

        if ($definitionElement->count() > 0) {
            $definitions[] = new DefinitionDto(
                trim($definitionElement->text()),
                $this->getSenses($definitionElement->siblings()->last()),
                $categories
            );
        }

        return $definitions;
    }

    /**
     * @return array<SenseDTO>
     */
    private function getSenses(Crawler $element): array
    {
        $children = $element->children();
        $senseTags = $children?->filter('.sense-tag');

        if (
            $children->count() > 0 &&
            $senseTags->count() > 0 &&
            !in_array($senseTags->first()->attr('class'), ['sense-tag tag-antonym', 'sense-tag tag-see_also'])
        ) {
            $senses = $senseTags
                ->filter('span:not(.tag-see_also)')
                ->filter('span:not(.tag-antonym)')
                ->each(
                    fn(Crawler $crawler) => new SenseDTO(trim($crawler->text()))
                );
        }

        return $senses ?? [];
    }

    /**
     * @return array<OtherFormDto>
     */
    private function getOtherForms(\DOMNodeList $nodeList): array
    {
        $otherForms = [];

        foreach ($nodeList as $meaningDefinitionContent) {
            if ($meaningDefinitionContent->getAttribute('class') == 'meaning-meaning') {
                $otherForms[] = new OtherFormDto(
                    trim($meaningDefinitionContent->textContent),
                );
            }
        }

        return $otherForms;
    }
}
