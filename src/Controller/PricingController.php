<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PricingController extends AbstractController
{
    private const PLAN_TABLES  = ['plans', 'plan', 'memberships', 'tiers'];
    private const NAME_COLS    = ['name', 'plan_name', 'title', 'label'];
    private const PRICE_COLS   = ['price', 'amount', 'monthly_price', 'cost', 'fee'];
    private const DESC_COLS    = ['description', 'details', 'info', 'about', 'summary'];
    private const DUR_COLS     = ['duration', 'period', 'billing_period', 'interval'];

    private const SUBS_TABLES   = ['subscription_requests', 'subscriptions', 'user_subscriptions', 'plan_requests'];
    private const SUB_USER_COLS = ['user_id', 'users_id', 'userid', 'user'];
    private const SUB_PLAN_COLS = ['plan_id', 'plans_id', 'plan', 'planid', 'plan_name', 'tier'];
    private const SUB_STAT_COLS = ['status', 'state'];
    private const SUB_TS_COLS   = ['created_at', 'createdAt', 'created', 'date_created', 'request_date', 'submitted_at'];

    #[Route('/pricing', name: 'app_pricing', methods: ['GET'])]
    public function index(Connection $conn): Response
    {
        $pending = false;
        $activePlanId = null;

        $user = $this->getUser();
        if ($user instanceof User) {
            [$pending, $activePlanId] = $this->subscriptionState($conn, (int) $user->getId(), $user->getTier());
        }

        return $this->render('pricing/index.html.twig', [
            'plans'          => $this->allPlans($conn),
            'pending'        => $pending,
            'active_plan_id' => $activePlanId,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/upgrade/{id}', name: 'app_pricing_upgrade', methods: ['GET'])]
    public function upgrade(int $id, Connection $conn): Response
    {
        $plan = $this->planById($conn, $id);
        if (!$plan) {
            throw $this->createNotFoundException('Plan not found.');
        }

        return $this->render('pricing/payment.html.twig', [
            'plan' => $plan, 'errors' => [], 'old' => [],
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/upgrade/{id}', name: 'app_pricing_process', methods: ['POST'])]
    public function process(int $id, Request $request, Connection $conn): Response
    {
        $plan = $this->planById($conn, $id);
        if (!$plan) {
            throw $this->createNotFoundException('Plan not found.');
        }

        $old = [
            'card_name'   => trim((string) $request->request->get('card_name')),
            'card_number' => trim((string) $request->request->get('card_number')),
            'card_expiry' => trim((string) $request->request->get('card_expiry')),
            'card_cvc'    => trim((string) $request->request->get('card_cvc')),
        ];

        $back = fn (array $errors) => $this->render('pricing/payment.html.twig', [
            'plan' => $plan, 'errors' => $errors, 'old' => $old,
        ]);

        if (!$this->isCsrfTokenValid('upgrade_' . $id, (string) $request->request->get('_token'))) {
            return $back(['Security token mismatch — please try again.']);
        }

        // ---- validate the fake payment details (we never store them) ----
        $errors = [];

        if ($old['card_name'] === '') {
            $errors[] = 'Cardholder name is required.';
        }

        $digits = preg_replace('/[\s\-]/', '', $old['card_number']);
        if (!preg_match('/^\d{12,19}$/', (string) $digits)) {
            $errors[] = 'Card number must be 12–19 digits (try 4242 4242 4242 4242).';
        }

        if (!preg_match('#^(\d{2})\s*/\s*(\d{2}|\d{4})$#', $old['card_expiry'], $m)) {
            $errors[] = 'Expiry must look like MM/YY.';
        } else {
            $month = (int) $m[1];
            $year  = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];
            if ($month < 1 || $month > 12) {
                $errors[] = 'Expiry month must be 01–12.';
            } elseif ($year < (int) date('Y') || ($year === (int) date('Y') && $month < (int) date('n'))) {
                $errors[] = 'That card looks expired.';
            }
        }

        if (!preg_match('/^\d{3,4}$/', $old['card_cvc'])) {
            $errors[] = 'CVC must be 3 or 4 digits.';
        }

        if ($errors) {
            return $back($errors);
        }

        // ---- write the pending request ----
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        [$subs, $uc, $pc, $sc, $tc] = $this->subsColumns($conn);
        if (!$subs || !$uc || !$pc || !$sc) {
            $this->addFlash('error', 'Subscription table is missing key columns — nothing was saved.');
            return $this->redirectToRoute('app_pricing');
        }

        [$pendingWord] = $this->statusWords($conn);

        $alreadyPending = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM `$subs` WHERE `$uc` = ? AND `$sc` = ?",
            [$user->getId(), $pendingWord]
        );
        if ($alreadyPending > 0) {
            $this->addFlash('info', 'You already have a request waiting for approval.');
            return $this->redirectToRoute('app_pricing');
        }

        $row = [$uc => $user->getId(), $pc => $plan['id'], $sc => $pendingWord];
        if ($tc) {
            $row[$tc] = date('Y-m-d H:i:s');
        }
        $conn->insert($subs, $row);

        $this->addFlash('success', sprintf('Payment received — your %s request is now waiting for admin approval.', $plan['name']));

        return $this->redirectToRoute('app_pricing');
    }

    // ================================================================= runtime schema helpers

    private function tableExists(Connection $conn, string $table): bool
    {
        foreach ($conn->fetchAllAssociative('SHOW TABLES') as $row) {
            if (in_array($table, array_values($row), true)) {
                return true;
            }
        }
        return false;
    }

    private function tableColumns(Connection $conn, string $table): array
    {
        return array_column($conn->fetchAllAssociative('SHOW COLUMNS FROM `' . $table . '`'), 'Field');
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

    private function plansTable(Connection $conn): ?string
    {
        foreach (self::PLAN_TABLES as $t) {
            if ($this->tableExists($conn, $t)) {
                return $t;
            }
        }
        return null;
    }

    private function allPlans(Connection $conn): array
    {
        $table = $this->plansTable($conn);
        if (!$table) {
            return [];
        }

        $cols  = $this->tableColumns($conn, $table);
        $nameC = $this->pick($cols, self::NAME_COLS);
        if (!$nameC) {
            return [];
        }
        $priceC = $this->pick($cols, self::PRICE_COLS);
        $descC  = $this->pick($cols, self::DESC_COLS);
        $durC   = $this->pick($cols, self::DUR_COLS);

        $selects = ['`id`', "`$nameC` AS name"];
        if ($priceC) { $selects[] = "`$priceC` AS price"; }
        if ($descC)  { $selects[] = "`$descC` AS description"; }
        if ($durC)   { $selects[] = "`$durC` AS duration"; }

        $plans = [];
        foreach ($conn->fetchAllAssociative('SELECT ' . implode(', ', $selects) . " FROM `$table` ORDER BY `id`") as $row) {
            $plans[] = [
                'id'          => (int) $row['id'],
                'name'        => (string) $row['name'],
                'price'       => isset($row['price']) && $row['price'] !== null ? number_format((float) $row['price'], 2) : null,
                'description' => $row['description'] ?? null,
                'duration'    => $row['duration'] ?? null,
            ];
        }
        return $plans;
    }

    private function planById(Connection $conn, int $id): ?array
    {
        foreach ($this->allPlans($conn) as $plan) {
            if ($plan['id'] === $id) {
                return $plan;
            }
        }
        return null;
    }

    /** @return array{0:?string,1:?string,2:?string,3:?string,4:?string} */
    private function subsColumns(Connection $conn): array
    {
        $table = null;
        foreach (self::SUBS_TABLES as $t) {
            if ($this->tableExists($conn, $t)) { $table = $t; break; }
        }
        if (!$table) {
            return [null, null, null, null, null];
        }

        $cols = $this->tableColumns($conn, $table);
        return [
            $table,
            $this->pick($cols, self::SUB_USER_COLS),
            $this->pick($cols, self::SUB_PLAN_COLS),
            $this->pick($cols, self::SUB_STAT_COLS),
            $this->pick($cols, self::SUB_TS_COLS),
        ];
    }

    /**
     * Reads the status column's ENUM dictionary at runtime and picks the
     * words that actually exist. Same doctrine as the column candidates,
     * one level deeper: never guess a vocabulary you can read.
     *
     * @return array{0:string,1:?string,2:?string} [pending, approved, rejected]
     */
    private function statusWords(Connection $conn): array
    {
        [$subs, , , $sc] = $this->subsColumns($conn);
        if (!$subs || !$sc) {
            return ['pending', 'active', 'rejected'];
        }

        $rows = $conn->fetchAllAssociative(
            'SHOW COLUMNS FROM `' . $subs . '` LIKE ' . $conn->quote($sc)
        );
        $type = (string) ($rows[0]['Type'] ?? '');

        if (preg_match('/^enum\((.*)\)$/i', $type, $m)) {
            $values = array_map(
                static fn (string $v): string => trim($v, " '\t"),
                explode(',', $m[1])
            );
            $values = array_values(array_filter($values, static fn (string $v): bool => $v !== ''));

            $pending = in_array('pending', $values, true) ? 'pending' : ($values[0] ?? 'pending');

            $approved = null;
            foreach (['active', 'approved', 'accepted', 'granted', 'paid'] as $cand) {
                if (in_array($cand, $values, true)) { $approved = $cand; break; }
            }
            if ($approved === null) {
                foreach ($values as $v) {
                    if ($v !== $pending) { $approved = $v; break; }
                }
            }

            $rejected = null;
            foreach (['rejected', 'denied', 'declined', 'cancelled', 'canceled', 'refused'] as $cand) {
                if (in_array($cand, $values, true)) { $rejected = $cand; break; }
            }

            return [$pending, $approved, $rejected];
        }

        return ['pending', 'active', 'rejected'];
    }

    /** @return array{0:bool,1:?int} [hasPending?, activePlanId] */
    private function subscriptionState(Connection $conn, int $userId, ?string $tier): array
    {
        [$subs, $uc, $pc, $sc] = $this->subsColumns($conn);
        [$pendingWord, $approvedWord] = $this->statusWords($conn);

        $pending = false;
        $activePlanId = null;

        if ($subs && $uc && $pc && $sc) {
            $pending = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM `$subs` WHERE `$uc` = ? AND `$sc` = ?",
                [$userId, $pendingWord]
            ) > 0;

            if ($approvedWord !== null) {
                $active = $conn->fetchOne(
                    "SELECT `$pc` FROM `$subs` WHERE `$uc` = ? AND `$sc` = ? LIMIT 1",
                    [$userId, $approvedWord]
                );
                if ($active !== null && $active !== false && (string) $active !== '') {
                    $activePlanId = (int) $active;
                }
            }
        }

        // legacy fallback: a tier set directly (or by the old app) with no row at all
        if ($activePlanId === null && $tier !== null && strtolower($tier) !== 'free') {
            foreach ($this->allPlans($conn) as $plan) {
                if (strtolower($plan['name']) === strtolower($tier)) {
                    $activePlanId = $plan['id'];
                    break;
                }
            }
        }

        return [$pending, $activePlanId];
    }
}