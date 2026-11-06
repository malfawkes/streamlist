<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests — no database, no kernel. They pin down the tier
 * half of PremiumAccess (the "one true check" every gate reuses).
 */
class UserTierTest extends TestCase
{
    public function testFreeTierIsNotPremium(): void
    {
        $user = new User();          // constructor default: tier 'free'
        $user->setTier('free');

        self::assertFalse($user->hasActivePremium());
    }

    public function testPremiumWithoutExpiryIsActive(): void
    {
        $user = new User();
        $user->setTier('premium');   // admin approval flow: no expiry on file

        self::assertTrue($user->hasActivePremium());
    }

    public function testPremiumExpiringTodayIsActive(): void
    {
        $user = new User();
        $user->setTier('premium');
        $user->setTierExpiresAt(new \DateTime('today'));

        self::assertTrue($user->hasActivePremium());
    }

    public function testExpiredPremiumIsInactive(): void
    {
        $user = new User();
        $user->setTier('premium');
        $user->setTierExpiresAt(new \DateTime('yesterday'));

        self::assertFalse($user->hasActivePremium());
    }

    public function testTierWordIsCaseInsensitive(): void
    {
        $user = new User();
        $user->setTier('Premium');   // an enum dictionary could carry any case

        self::assertTrue($user->hasActivePremium());
    }
}