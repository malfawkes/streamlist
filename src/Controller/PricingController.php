<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PricingController extends AbstractController
{
    // Candidate names per field — first one that exists in YOUR table wins.
    private const NAME_CANDIDATES     = ['name', 'plan_name', 'title', 'label'];
    private const PRICE_CANDIDATES    = ['price', 'amount', 'cost', 'monthly_price', 'plan_price'];
    private const DURATION_CANDIDATES = ['duration_days', 'duration', 'days', 'duration_months', 'validity_days'];
    private const DESC_CANDIDATES     = ['description', 'features', 'details', 'plan_description'];
    private const TS_CANDIDATES       = ['created_at', 'requested_at', 'date_created', 'created', 'submitted_at', 'request_date'];
    private const SR_USER_CANDIDATES  = ['user_id', 'users_id', 'user'];
    private const SR_PLAN_CANDIDATES  = ['plan_id', 'plans_id', 'plan'];
    private const SR_STATUS_CANDIDATES = ['status', 'state', 'request_status'];

    #[Route('/pricing', name: 'app_pricing', methods: ['GET'])]
    public function index(Connection $connection): Response
    {
        // Real column names, straight from MySQL — no guessing.
        $cols       = $this->tableColumns($connection, 'plans');
        $nameCol    = $this->pick($cols, self::NAME_CANDIDATES);
        $priceCol   = $this->pick($cols, self::PRICE_CANDIDATES);
        $durationCol = $this->pick($cols, self::DURATION_CANDIDATES);
        $descCol    = $this->pick($cols, self::DESC_CANDIDATES);

        $rows = $connection->fetchAllAssociative(
            $priceCol
                ? "SELECT * FROM plans ORDER BY `{$priceCol}` ASC"
                : 'SELECT * FROM plans ORDER BY id ASC'
        );

        // Normalize to fixed keys so the template never changes.
        $plans = [];
        foreach ($rows as $row) {
            $plans[] = [
                'id'           => $row['id'] ?? null,
                'name'         => $nameCol    ? ($row[$nameCol]    ?? null) : null,
                'price'        => $priceCol   ? ($row[$priceCol]   ?? null) : null,
                'durationDays' => $durationCol ? ($row[$durationCol] ?? null) : null,
                'description'  => $descCol    ? ($row[$descCol]    ?? null) : null,
            ];
        }

        $pendingPlanId = null;
        $user = $this->getUser();

        if ($user instanceof User) {
            $srCols    = $this->tableColumns($connection, 'subscription_requests');
            $userCol   = $this->pick($srCols, self::SR_USER_CANDIDATES) ?? 'user_id';
            $planCol   = $this->pick($srCols, self::SR_PLAN_CANDIDATES) ?? 'plan_id';
            $statusCol = $this->pick($srCols, self::SR_STATUS_CANDIDATES) ?? 'status';

            $pending = $connection->fetchAssociative(
                "SELECT `{$planCol}` AS plan_id FROM subscription_requests
                 WHERE `{$userCol}` = :uid AND `{$statusCol}` = :status
                 LIMIT 1",
                ['uid' => $user->getId(), 'status' => 'pending']
            );
            $pendingPlanId = $pending ? ($pending['plan_id'] ?? null) : null;
        }

        return $this->render('pricing/index.html.twig', [
            'plans'           => $plans,
            'pending_plan_id' => $pendingPlanId,
        ]);
    }

    #[Route('/upgrade/{id}', name: 'app_upgrade_request', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function requestUpgrade(
        int $id,
        Request $request,
        Connection $connection,
        CsrfTokenManagerInterface $csrf
    ): Response {
        // CSRF guard
        $submitted = (string) $request->request->get('_token');
        if (!$csrf->isTokenValid(new CsrfToken('upgrade_' . $id, $submitted))) {
            $this->addFlash('error', 'Invalid or expired form token. Please try again.');
            return $this->redirectToRoute('app_pricing');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $srCols    = $this->tableColumns($connection, 'subscription_requests');
        $userCol   = $this->pick($srCols, self::SR_USER_CANDIDATES) ?? 'user_id';
        $planCol   = $this->pick($srCols, self::SR_PLAN_CANDIDATES) ?? 'plan_id';
        $statusCol = $this->pick($srCols, self::SR_STATUS_CANDIDATES) ?? 'status';

        // One open request per user
        $existing = $connection->fetchAssociative(
            "SELECT id FROM subscription_requests WHERE `{$userCol}` = :uid AND `{$statusCol}` = :status LIMIT 1",
            ['uid' => $user->getId(), 'status' => 'pending']
        );
        if ($existing !== false) {
            $this->addFlash('warning', 'You already have a pending upgrade request under review.');
            return $this->redirectToRoute('app_pricing');
        }

        // Plan must exist
        $plan = $connection->fetchAssociative('SELECT id FROM plans WHERE id = :id', ['id' => $id]);
        if ($plan === false) {
            throw $this->createNotFoundException('Plan not found.');
        }

        // INSERT built from the columns your table actually has
        $data = [
            $userCol   => $user->getId(),
            $planCol   => $id,
            $statusCol => 'pending',
        ];

        // Any timestamp column gets a value (prevents the 1364 no-default error)
        $tsCol = $this->pick($srCols, self::TS_CANDIDATES);
        if ($tsCol !== null) {
            $data[$tsCol] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $connection->insert('subscription_requests', $data);

        $this->addFlash('success', 'Request submitted — an admin will review it shortly.');
        return $this->redirectToRoute('app_pricing');
    }

    /** @return string[] the real column names of a table */
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