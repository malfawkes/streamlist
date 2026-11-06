<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;

/**
 * One question, one answer: "does this user get PREMIUM access right now?"
 *
 * Two sources of truth are honored — UNION, not intersection:
 *
 *   1. users.tier = 'premium' (and not expired) — the legacy path:
 *      tier set directly, or by an admin whose plan name DID match the
 *      tier column's enum dictionary.
 *
 *   2. an APPROVED subscription row for this user — the very row the
 *      admin approval flow writes, and the one the pricing page shows
 *      as "your plan". The tier dual-write only happens when the plan
 *      name maps onto an enum word (plan "Basic" + enum('free','premium')
 *      leaves tier at 'free'). Tier alone would lock out paying members,
 *      so the subscription table gets a vote too.
 *
 * Same runtime schema-sniffing doctrine as PricingController: never guess
 * a table or column name you can read.
 *
 * The subscription-table verdict is memoized per user object, so the nav
 * badge, every movie card and the controllers share ONE round of
 * schema-sniffing per request. ResetInterface clears it between requests
 * (FrankenPHP worker mode keeps services alive — stale verdicts must
 * never leak into the next request).
 */
final class PremiumAccess implements ResetInterface
{
    private const SUBS_TABLES   = ['subscription_requests', 'subscriptions', 'user_subscriptions', 'plan_requests'];
    private const SUB_USER_COLS = ['user_id', 'users_id', 'userid', 'user'];
    private const SUB_STAT_COLS = ['status', 'state'];

    /**
     * Verdicts keyed by object identity (spl_object_id).
     *
     * @var array<int,bool>
     */
    private array $verdicts = [];

    public function __construct(private readonly Connection $conn)
    {
    }

    public function hasPremiumAccess(User $user): bool
    {
        // 1) tier path — in-memory and free, so it is re-checked on every
        //    call: an approval that lands mid-request (same object, tier
        //    now written) takes effect immediately
        if ($user->hasActivePremium()) {
            return true;
        }

        // 2) subscription-table path — memoized: one DB round-trip per
        //    user object per request, shared by every caller
        $key = spl_object_id($user);
        if (isset($this->verdicts[$key])) {
            return $this->verdicts[$key];
        }

        return $this->verdicts[$key] = $this->hasApprovedSubscription($user);
    }

    /**
     * Called automatically between requests under FrankenPHP worker mode
     * (services implementing ResetInterface get the kernel.reset tag).
     */
    public function reset(): void
    {
        $this->verdicts = [];
    }

    private function hasApprovedSubscription(User $user): bool
    {
        $userId = $user->getId();
        if ($userId === null) {
            return false;
        }

        [$subs, $uc, $sc] = $this->subsColumns();
        if (!$subs || !$uc || !$sc) {
            return false;
        }

        $approvedWord = $this->approvedStatusWord($subs, $sc);
        if ($approvedWord === null) {
            return false;
        }

        $hit = $this->conn->fetchOne(
            "SELECT `$uc` FROM `$subs` WHERE `$uc` = ? AND `$sc` = ? LIMIT 1",
            [$userId, $approvedWord]
        );

        return $hit !== null && $hit !== false;
    }

    // ================================================================ runtime schema helpers

    /** @return array{0:?string,1:?string,2:?string} [table, user_col, status_col] */
    private function subsColumns(): array
    {
        $table = null;
        foreach (self::SUBS_TABLES as $t) {
            if ($this->tableExists($t)) {
                $table = $t;
                break;
            }
        }
        if (!$table) {
            return [null, null, null];
        }

        $cols = array_column(
            $this->conn->fetchAllAssociative('SHOW COLUMNS FROM `' . $table . '`'),
            'Field'
        );

        return [
            $table,
            $this->pick($cols, self::SUB_USER_COLS),
            $this->pick($cols, self::SUB_STAT_COLS),
        ];
    }

    /**
     * Reads the status column's ENUM dictionary and picks the word that
     * means "approved". Mirrors PricingController::statusWords().
     */
    private function approvedStatusWord(string $table, string $statusCol): ?string
    {
        $rows = $this->conn->fetchAllAssociative(
            'SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->conn->quote($statusCol)
        );
        $type = (string) ($rows[0]['Type'] ?? '');

        if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) {
            return 'active';   // plain varchar — the convention this app writes
        }

        $values = array_map(
            static fn (string $v): string => trim($v, " '\t"),
            explode(',', $m[1])
        );
        $values = array_values(array_filter($values, static fn (string $v): bool => $v !== ''));

        $pending = in_array('pending', $values, true) ? 'pending' : ($values[0] ?? 'pending');

        foreach (['active', 'approved', 'accepted', 'granted', 'paid'] as $cand) {
            if (in_array($cand, $values, true)) {
                return $cand;
            }
        }
        foreach ($values as $v) {
            if ($v !== $pending) {
                return $v;    // a two-word enum: the other word is the approved one
            }
        }

        return null;
    }

    private function tableExists(string $table): bool
    {
        foreach ($this->conn->fetchAllAssociative('SHOW TABLES') as $row) {
            if (in_array($table, array_values($row), true)) {
                return true;
            }
        }

        return false;
    }

    private function pick(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (in_array($c, $columns, true)) {
                return $c;
            }
        }

        return null;
    }
}