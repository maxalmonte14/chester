<?php

namespace Chester\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WordListControllerTest extends WebTestCase
{
    public function testItCanShowWordListFromValidLinkCollection(): void
    {
        $client = static::createClient();
        
        $crawler = $client->request('POST', '/word-list', ['data' => json_encode([
            [
                'text' => '学校',
                'url'  => '//jisho.org/word/%E5%AD%A6%E6%A0%A1',
            ],
            [
                'text' => '川',
                'url'  => '//jisho.org/word/%E5%B7%9D',
            ]
        ])]);
        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('ul.word-list > li'));
    }

    public function testItShowsErrorFromInvalidLinkCollection(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/word-list', ['data' => json_encode([
            [
                'text' => 'x',
                'url'  => 'invalid',
            ],
            [
                'text' => 'y',
                'url'  => 'invalid',
            ]
        ])]);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('p', 'Unable to retrieve word list');
    }
}