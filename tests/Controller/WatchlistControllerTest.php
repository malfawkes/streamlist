<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The perimeter rule: /watchlist is members-only. Anonymous visitors
 * are bounced to the login page, not shown the list.
 */
class WatchlistControllerTest extends WebTestCase
{
    public function testWatchlistPageRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/watchlist');

        self::assertResponseRedirects('/login');
    }
}