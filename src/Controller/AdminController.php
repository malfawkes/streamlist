<?php

namespace App\Controller;

use App\Repository\GenreRepository;
use App\Repository\MovieRepository;
use App\Repository\UserRepository;
use App\Repository\WatchlistItemRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route; // ← see note below
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'app_admin_')]
class AdminController extends AbstractController
{
    // Runtime column detection — same expedite trick as PricingController.
    private const SR_USER_CANDIDATES    = ['user_id', 'users_id', 'user'];
    private const SR_PLAN_CANDIDATES    = ['plan_id', 'plans_id', 'plan'];
    private const SR_STATUS_CANDIDATES  = ['status', 'state', 'request_status'];
    private const SR_TS_CANDIDATES      = ['created_at', 'requested_at', 'date_created', 'created', 'submitted_at', 'request_date'];
    private const PLAN_NAME_CANDIDATES  = ['name', 'plan_name', 'title', 'label'];
    private const PLAN_DUR_CANDIDATES   = ['duration_days', 'duration', 'days', 'validity_days', 'duration_months'];

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $userRepo,
        MovieRepository $movieRepo,
        GenreRepository $genreRepo,
        WatchlistItemRepository $watchlistRepo,
        Connection $connection
    ): Response {
        $srCols      = $this->tableColumns($connection, 'subscription_requests');
        $userCol     = $this->pick($srCols, self::SR_USER_CANDIDATES) ?? 'user_id';
        $planCol     = $this->pick($srCols, self::SR_PLAN_CANDIDATES) ?? 'plan_id';
        $statusCol   = $this->pick($srCols, self::SR_STATUS_CANDIDATES) ?? 'status';
        $tsCol       = $this->pick($srCols, self::SR_TS_CANDIDATES);
        $planCols    = $this->tableColumns($connection, 'plans');
        $planNameCol = $this->pick($planCols, self::PLAN_NAME_CANDIDATES);

        // ---- Tier breakdown for the Users card ----
        $tierBits = [];
        foreach ($connection->fetchAllAssociative('SELECT tier, COUNT(*) AS n FROM users GROUP BY tier') as $row) {
            $tierBits[] = ((int) $row['n']) . ' ' . $row['tier'];
        }

        $pendingCount = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM subscription_requests WHERE `{$statusCol}` = :status",
            ['status' => 'pending']
        );

        // Contact messages only if the table exists (it should — original schema).
        $contactCount = 0;
        if (in_array('contact_messages', $connection->fetchFirstColumn('SHOW TABLES'), true)) {
            $contactCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM contact_messages');
        }

        // ---- Pending requests with user + plan names ----
        // NULL AS requested_at keeps the key present when no timestamp column exists.
        $tsSelect     = $tsCol ? ", sr.`{$tsCol}` AS requested_at" : ', NULL AS requested_at';
        $planNameExpr = $planNameCol ? "p.`{$planNameCol}`" : "sr.`{$planCol}`";

        $pendingRequests = $connection->fetchAllAssociative(
            "SELECT sr.id AS id,
                    u.name AS user_name,
                    u.email AS user_email,
                    {$planNameExpr} AS plan_name
                    {$tsSelect}
             FROM subscription_requests sr
             INNER JOIN users u ON u.id = sr.`{$userCol}`
             LEFT JOIN plans p ON p.id = sr.`{$planCol}`
             WHERE sr.`{$statusCol}` = :status
             ORDER BY sr.id DESC
             LIMIT 20",
            ['status' => 'pending']
        );

        $recentUsers = $connection->fetchAllAssociative(
            'SELECT id, name, email, tier FROM users ORDER BY id DESC LIMIT 5'
        );

        $topMovies = $connection->fetchAllAssociative(
            'SELECT m.id, m.title, COUNT(*) AS adds
             FROM watch_list w
             INNER JOIN movies m ON m.id = w.movie_id
             GROUP BY m.id, m.title
             ORDER BY adds DESC
             LIMIT 5'
        );

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users'                 => $userRepo->count([]),
                'tier_summary'          => $tierBits ? implode(' · ', $tierBits) : '—',
                'movies'                => $movieRepo->countAll(),
                'genres'                => $genreRepo->countAll(),
                'watchlist_adds'        => $watchlistRepo->count([]),
                'pending_subscriptions' => $pendingCount,
                'contact_messages'      => $contactCount,
            ],
            'pending_requests' => $pendingRequests,
            'recent_users'     => $recentUsers,
            'top_movies'       => $topMovies,
        ]);
    }

    #[Route('/subscription/{id}/approve', name: 'subscription_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approveSubscription(int $id, Request $request, Connection $connection, CsrfTokenManagerInterface $csrf): Response
    {
        return $this->resolveSubscription($id, 'approved', $request, $connection, $csrf);
    }

    #[Route('/subscription/{id}/reject', name: 'subscription_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejectSubscription(int $id, Request $request, Connection $connection, CsrfTokenManagerInterface $csrf): Response
    {
        return $this->resolveSubscription($id, 'rejected', $request, $connection, $csrf);
    }

    /**
     * One processor, two doors — approve and reject share the whole skeleton:
     * CSRF → load → still-pending guard → decide → write → flash → redirect.
     */
    private function resolveSubscription(int $id, string $decision, Request $request, Connection $connection, CsrfTokenManagerInterface $csrf): Response
    {
        $action = $decision === 'approved' ? 'approve' : 'reject';

        $submitted = (string) $request->request->get('_token');
        if (!$csrf->isTokenValid(new CsrfToken('admin_sub_' . $action . '_' . $id, $submitted))) {
            $this->addFlash('error', 'Invalid or expired token. Please try again.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $srCols    = $this->tableColumns($connection, 'subscription_requests');
        $userCol   = $this->pick($srCols, self::SR_USER_CANDIDATES) ?? 'user_id';
        $planCol   = $this->pick($srCols, self::SR_PLAN_CANDIDATES) ?? 'plan_id';
        $statusCol = $this->pick($srCols, self::SR_STATUS_CANDIDATES) ?? 'status';

        $req = $connection->fetchAssociative(
            "SELECT id, `{$userCol}` AS user_id, `{$planCol}` AS plan_id, `{$statusCol}` AS status
             FROM subscription_requests WHERE id = :id",
            ['id' => $id]
        );
        if ($req === false) {
            throw $this->createNotFoundException('Request not found.');
        }
        if ($req['status'] !== 'pending') {
            $this->addFlash('warning', 'That request was already handled.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($decision === 'approved') {
            // Grant: premium for the plan's duration, default 30 days.
            $days = 30;
            $planDurCol = $this->pick($this->tableColumns($connection, 'plans'), self::PLAN_DUR_CANDIDATES);
            if ($planDurCol !== null) {
                $plan = $connection->fetchAssociative('SELECT * FROM plans WHERE id = :id', ['id' => $req['plan_id']]);
                if ($plan && is_numeric($plan[$planDurCol] ?? null) && (int) $plan[$planDurCol] > 0) {
                    $days = (int) $plan[$planDurCol];
                    if (str_contains($planDurCol, 'month')) {
                        $days *= 30;
                    }
                }
            }

            $fields = ['tier' => 'premium'];
            if ($this->pick($this->tableColumns($connection, 'users'), ['tier_expires_at']) !== null) {
                $fields['tier_expires_at'] = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            }
            $connection->update('users', $fields, ['id' => $req['user_id']]);

            $user = $connection->fetchAssociative('SELECT email FROM users WHERE id = :id', ['id' => $req['user_id']]);
            $this->addFlash('success', sprintf(
                'Approved — %s is premium for %d days.',
                $user['email'] ?? ('user #' . $req['user_id']),
                $days
            ));
        } else {
            $this->addFlash('success', 'Request rejected.');
        }

        $connection->update('subscription_requests', [$statusCol => $decision], ['id' => $id]);

        return $this->redirectToRoute('app_admin_dashboard');
    }

    /** @return string[] real column names of a table */
    private function tableColumns(Connection $connection, string $table): array
    {
        return array_column(
            $connection->fetchAllAssociative("SHOW COLUMNS FROM {$table}"),
            'Field'
        );
    }

    /** First candidate that exists in the table, in the table's own casing */
    private function pick(array $columns, array $candidates): ?string
    {
        $lowered = array_map('strtolower', $columns);
        foreach ($candidates as $candidate) {
            $idx = array_search(strtolower($candidate), $lowered, true);
            if ($idx !== false) {
                return $columns[$idx];
            }
        }

        return null;
    }
}