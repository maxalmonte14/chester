<?php

namespace App\Services;

use App\DTO\CategoryDTO;
use App\DTO\DefinitionDto;
use App\DTO\LinkDto;
use App\DTO\OtherFormDto;
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
        return array_map(function ($category) {
            return new CategoryDTO(ucfirst(trim($category)));
        }, array_unique(explode(',', $categories)));
    }

    /**
     * @return array<DefinitionDto>
     */
    private function getDefinition(\DOMNodeList $nodeList, array $categories): array
    {
        $definitions = [];

        foreach ($nodeList as $meaningDefinitionContent) {
            if ($meaningDefinitionContent->getAttribute('class') == 'meaning-meaning') {
                $definitions[] = new DefinitionDto(
                    trim($meaningDefinitionContent->textContent),
                    $categories
                );
            }
        }

        return $definitions;
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
