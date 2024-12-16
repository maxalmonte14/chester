<?php

namespace Chester\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExtractControllerTest extends WebTestCase
{
    public function testItCanExtractWordListFromValidLink(): void
    {
        $client = static::createClient();
        $crawler = $client->request(
            'POST',
            '/extract',
            ['pageLink' => 'https://jisho.org/search/%20%23words%20%23n5?page=1']
        );

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'The following links were fetched');
        $this->assertCount(20, $crawler->filter('li'));
    }

    public function testItCanNotExtractWordListFromInvalidLink(): void
    {
        $client = static::createClient();
        
        $client->request(
            'POST',
            '/extract',
            ['pageLink' => 'invalid']
        );
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('p', 'Unable to retrieve links');
    }
}
