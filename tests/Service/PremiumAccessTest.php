<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\PremiumAccess;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * PremiumAccess with a mocked DBAL Connection — no database needed.
 * "SHOW TABLES returns nothing" is the schema-less worst case: every
 * schema-sniffing helper must degrade to false, never fatal.
 */
class PremiumAccessTest extends TestCase
{
    public function testPremiumTierShortCircuitsWithoutTouchingTheDb(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::never())->method('fetchAllAssociative');

        $user = new User();
        $user->setTier('premium');

        self::assertTrue((new PremiumAccess($conn))->hasPremiumAccess($user));
    }

    public function testFreeUserWithoutSubscriptionTableIsNotPremium(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([]);   // no tables at all

        $user = new User();                                     // tier 'free'

        self::assertFalse((new PremiumAccess($conn))->hasPremiumAccess($user));
    }

    public function testExpiredPremiumFallsBackToSubscriptionCheck(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([]);   // no subs table → fallback fails

        $user = new User();
        $user->setTier('premium');
        $user->setTierExpiresAt(new \DateTime('yesterday'));

        self::assertFalse((new PremiumAccess($conn))->hasPremiumAccess($user));
    }

    public function testMemoPreventsRepeatQueriesAndResetClearsIt(): void
    {
        $calls = 0;
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAllAssociative')->willReturnCallback(static function () use (&$calls): array {
            ++$calls;

            return [];
        });

        $access = new PremiumAccess($conn);
        $user   = new User();

        $access->hasPremiumAccess($user);
        $first = $calls;                       // N schema-sniff queries

        $access->hasPremiumAccess($user);      // memo hit
        self::assertSame($first, $calls, 'the memoized verdict must not re-query the DB');

        $access->reset();                      // FrankenPHP worker mode: new request
        $access->hasPremiumAccess($user);      // fresh check

        self::assertSame($first * 2, $calls, 'reset() must clear the memo');
    }
}