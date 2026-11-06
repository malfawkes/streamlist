<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MovieControllerTest extends WebTestCase
{
    public function testShowPageListsTopBilledCast(): void
    {
        $client  = static::createClient();
        $doctrine = $client->getContainer()->get('doctrine');

        // Pick the top-billed cast row of whichever movie has one — the
        // same row findTopCast() should render first, so the whole path
        // is exercised: entity mapping → repository → controller → Twig.
        $row = $doctrine->getConnection()->fetchAssociative(
            'SELECT movie_id, name FROM movie_credits
             WHERE job = :job
             ORDER BY movie_id, credit_order
             LIMIT 1',
            ['job' => 'Cast']
        );

        self::assertNotNull($row, 'movie_credits has no Cast rows to test against');

        // /movie/{id} is members-only on this app: an anonymous client is
        // bounced 302 → /login (exactly what the failing run saw). Log an
        // EXISTING account in. loginUser() installs the session token
        // directly — no form submit, no password check — but the firewall
        // re-loads the user from the database on the next request, so the
        // row must be real. Prefer an admin row if one exists (survives a
        // ROLE_ADMIN guard too), else any member. Read-only on purpose:
        // this suite runs against the dev database (see
        // config/packages/test/doctrine.yaml), so nothing is inserted.
        $userId = $doctrine->getConnection()->fetchOne(
            'SELECT id FROM users ORDER BY is_admin DESC, id ASC LIMIT 1'
        );

        self::assertNotFalse($userId, 'users table is empty — register any account in the app first');

        $client->loginUser($doctrine->getManager()->find(User::class, (int) $userId));

        $client->request('GET', '/movie/'.$row['movie_id']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.md-cast');
        self::assertSelectorTextContains('.md-cast__name', $row['name']);
    }
}