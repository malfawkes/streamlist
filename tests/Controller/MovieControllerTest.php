<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MovieControllerTest extends WebTestCase
{
    public function testShowPageListsTopBilledCast(): void
    {
        $client = static::createClient();

        // Pick the top-billed cast row of whichever movie has one — the
        // same row findTopCast() should render first, so the whole path
        // is exercised: entity mapping → repository → controller → Twig.
        $row = $client->getContainer()
            ->get('doctrine')
            ->getConnection()
            ->fetchAssociative(
                'SELECT movie_id, name FROM movie_credits
                 WHERE job = :job
                 ORDER BY movie_id, credit_order
                 LIMIT 1',
                ['job' => 'Cast']
            );

        self::assertNotNull($row, 'movie_credits has no Cast rows to test against');

        $client->request('GET', '/movie/'.$row['movie_id']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.md-cast');
        self::assertSelectorTextContains('.md-cast__name', $row['name']);
    }
}
