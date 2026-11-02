<?php

namespace App\Controller;

use App\Entity\Genre;
use App\Entity\Movie;
use App\Entity\MovieCredit;
use App\Entity\User;
use App\Entity\WatchlistItem;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityRepository;


#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'app_admin_')]
class AdminController extends AbstractController
{
    // -- plans / subscription-table candidates (duplicated from PricingController on purpose,
    //    so each controller stands alone and you never paste half a puzzle) --
    private const PLAN_TABLES   = ['plans', 'plan', 'memberships', 'tiers'];
    private const NAME_COLS     = ['name', 'plan_name', 'title', 'label'];
    private const PRICE_COLS    = ['price', 'amount', 'monthly_price', 'cost', 'fee'];
    private const SUBS_TABLES   = ['subscription_requests', 'subscriptions', 'user_subscriptions', 'plan_requests'];
    private const SUB_USER_COLS = ['user_id', 'users_id', 'userid', 'user'];
    private const SUB_PLAN_COLS = ['plan_id', 'plans_id', 'plan', 'planid', 'plan_name', 'tier'];
    private const SUB_STAT_COLS = ['status', 'state'];
    private const SUB_TS_COLS   = ['created_at', 'createdAt', 'created', 'date_created', 'request_date', 'submitted_at'];

    // ================================================================ dashboard

        #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(ManagerRegistry $doctrine, Connection $conn): Response
    {
        $recentAdds = $this->repo($doctrine, WatchlistItem::class)->createQueryBuilder('w')
            ->select('w.id AS wid, u.id AS uid, u.name AS uname, m.id AS mid, m.title AS mtitle')
            ->join('w.user', 'u')
            ->join('w.movie', 'm')
            ->orderBy('w.id', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getArrayResult();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users'     => $this->repo($doctrine, User::class)->count([]),
                'movies'    => $this->repo($doctrine, Movie::class)->count([]),
                'genres'    => $this->repo($doctrine, Genre::class)->count([]),
                'watchlist' => $this->repo($doctrine, WatchlistItem::class)->count([]),
                'pending'   => $this->pendingCount($conn),
            ],
            'pending_rows' => $this->pendingRows($conn, $doctrine->getManager()),
            'recent_users' => $this->repo($doctrine, User::class)->findBy([], ['id' => 'DESC'], 5),
            'recent_adds'  => $recentAdds,
        ]);
    }

    // ================================================================ users

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(ManagerRegistry $doctrine, Connection $conn): Response
    {
        [$activeMap, $pendingIds] = $this->userSubscriptionMaps($conn);

        $plans = [];
        foreach ($this->allPlans($conn) as $p) {
            $plans[$p['id']] = $p; // keyed by id so the template can look plans up directly
        }

        return $this->render('admin/users.html.twig', [
            'users'       => $doctrine->getManager()->getRepository(User::class)->findBy([], ['id' => 'DESC']),
            'plans'       => $plans,
            'active_map'  => $activeMap,
            'pending_ids' => $pendingIds,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function userDelete(User $user, Request $request, ManagerRegistry $doctrine, Connection $conn): Response
    {
        if (!$this->isCsrfTokenValid('del_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Security token mismatch — nothing was deleted.');
            return $this->redirectToRoute('app_admin_users');
        }

        $me = $this->getUser();
        if ($me instanceof User && $me->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot delete the account you are logged in with.');
            return $this->redirectToRoute('app_admin_users');
        }

        $em = $doctrine->getManager();
        $name = $user->getName() ?? $user->getEmail();

        // children first, parent second — same rule as every FK in your life
        foreach ($em->getRepository(WatchlistItem::class)->findBy(['user' => $user]) as $item) {
            $em->remove($item);
        }
        [$subs, $uc] = $this->subsColumns($conn);
        if ($subs && $uc) {
            $conn->delete($subs, [$uc => $user->getId()]);
        }
        if ($user->getAvatarPath()) {
            $file = rtrim($this->getParameter('kernel.project_dir'), '/') . '/public/' . ltrim((string) $user->getAvatarPath(), '/');
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', sprintf('Deleted %s — watchlist, requests and avatar included.', $name));
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/plan', name: 'user_setplan', methods: ['POST'])]
    public function userSetPlan(User $user, Request $request, Connection $conn): Response
    {
        if (!$this->isCsrfTokenValid('plan_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Security token mismatch.');
            return $this->redirectToRoute('app_admin_users');
        }

        [$subs, $uc, $pc, $sc, $tc] = $this->subsColumns($conn);
        if (!$subs || !$uc) {
            $this->addFlash('error', 'No subscription table found — cannot set plans.');
            return $this->redirectToRoute('app_admin_users');
        }

        // wipe whatever the user had — a direct set resolves pending requests too
        $conn->delete($subs, [$uc => $user->getId()]);

        $planId = (string) $request->request->get('plan_id', '');
        if ($planId !== '') {
            $plan = $this->planById($conn, (int) $planId);
            if (!$plan || !$pc || !$sc) {
                $this->addFlash('error', 'That plan does not exist.');
                return $this->redirectToRoute('app_admin_users');
            }
            $row = [$uc => $user->getId(), $pc => (int) $planId, $sc => 'active'];
            if ($tc) {
                $row[$tc] = date('Y-m-d H:i:s');
            }
            $conn->insert($subs, $row);
            $this->addFlash('success', sprintf('%s is now on %s.', $user->getName() ?? $user->getEmail(), $plan['name']));
        } else {
            $this->addFlash('success', sprintf('Removed the plan for %s.', $user->getName() ?? $user->getEmail()));
        }

        return $this->redirectToRoute('app_admin_users');
    }

    // ================================================================ pending requests

    #[Route('/pending/{id}/approve', name: 'pending_approve', methods: ['POST'])]
    public function pendingApprove(int $id, Request $request, Connection $conn): Response
    {
        return $this->resolvePending($id, $request, $conn, 'approve', 'active', 'Approved — that plan is now live.');
    }

    #[Route('/pending/{id}/reject', name: 'pending_reject', methods: ['POST'])]
    public function pendingReject(int $id, Request $request, Connection $conn): Response
    {
        return $this->resolvePending($id, $request, $conn, 'reject', 'rejected', 'Request rejected — the user can try again.');
    }

    private function resolvePending(int $id, Request $request, Connection $conn, string $csrfAction, string $status, string $message): Response
    {
        if (!$this->isCsrfTokenValid('sub_' . $csrfAction . '_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Security token mismatch.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        [$subs, , , $sc] = $this->subsColumns($conn);
        if (!$subs || !$sc) {
            $this->addFlash('error', 'No subscription table found.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $affected = $conn->update($subs, [$sc => $status], [$this->idColumn($conn, $subs) ?? 'id' => $id]);
        $this->addFlash($affected > 0 ? 'success' : 'error', $affected > 0 ? $message : 'No request found with that id.');

        return $this->redirectToRoute('app_admin_dashboard');
    }

    // ================================================================ movies

    #[Route('/movies', name: 'movies', methods: ['GET'])]
    public function movies(ManagerRegistry $doctrine): Response
    {
        return $this->render('admin/movies.html.twig', [
            'movies' => $doctrine->getManager()->getRepository(Movie::class)->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/movies/new', name: 'movie_new', methods: ['GET', 'POST'])]
    public function movieNew(Request $request, ManagerRegistry $doctrine): Response
    {
        $em = $doctrine->getManager();
        $errors = [];

        if ($request->isMethod('POST')) {
            [$movie, $errors] = $this->movieFromRequest($request, $em, null);
            if (!$errors) {
                $em->persist($movie);
                $em->flush();
                $this->addFlash('success', sprintf('"%s" is live on the movie wall.', $movie->getTitle()));
                return $this->redirectToRoute('app_movie_show', ['id' => $movie->getId()]);
            }
        }

        return $this->render('admin/movie_form.html.twig', $this->formView(null, $request, $em, $errors));
    }

    #[Route('/movies/{id}/edit', name: 'movie_edit', methods: ['GET', 'POST'])]
    public function movieEdit(Movie $movie, Request $request, ManagerRegistry $doctrine): Response
    {
        $em = $doctrine->getManager();
        $errors = [];

        if ($request->isMethod('POST')) {
            [, $errors] = $this->movieFromRequest($request, $em, $movie);
            if (!$errors) {
                $em->flush();
                $this->addFlash('success', 'Saved.');
                return $this->redirectToRoute('app_movie_show', ['id' => $movie->getId()]);
            }
        }

        return $this->render('admin/movie_form.html.twig', $this->formView($movie, $request, $em, $errors));
    }

    #[Route('/movies/{id}/delete', name: 'movie_delete', methods: ['POST'])]
    public function movieDelete(Movie $movie, Request $request, ManagerRegistry $doctrine): Response
    {
        if (!$this->isCsrfTokenValid('del_movie_' . $movie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Security token mismatch — nothing was deleted.');
            return $this->redirectToRoute('app_admin_movies');
        }

        $em = $doctrine->getManager();

        // children first: credits, watchlist entries, genre links
        foreach ($em->getRepository(MovieCredit::class)->findBy(['movie' => $movie]) as $credit) {
            $em->remove($credit);
        }
        foreach ($em->getRepository(WatchlistItem::class)->findBy(['movie' => $movie]) as $item) {
            $em->remove($item);
        }
        $movie->getGenres()->clear();

        $title = $movie->getTitle();
        $em->remove($movie);
        $em->flush();

        $this->addFlash('success', sprintf('"%s" and its credits, genres and watchlist entries are gone.', $title ?? 'Movie'));
        return $this->redirectToRoute('app_admin_movies');
    }

    // ================================================================ movie form plumbing

        /** @return array{0:?Movie, 1:string[]} */
    private function movieFromRequest(Request $request, EntityManagerInterface $em, ?Movie $movie): array
    {
        $errors = [];
        $data = [
            'title'         => trim((string) $request->request->get('title')),
            'tmdb_id'       => trim((string) $request->request->get('tmdb_id')),
            'release_date'  => trim((string) $request->request->get('release_date')),
            'rating'        => trim((string) $request->request->get('rating')),
            'overview'      => trim((string) $request->request->get('overview')),
            'poster_path'   => trim((string) $request->request->get('poster_path')),
            'backdrop_path' => trim((string) $request->request->get('backdrop_path')),
            'trailer_key'   => trim((string) $request->request->get('trailer_key')),
            'is_premium'    => $request->request->get('is_premium') === '1',
        ];

        if ($data['title'] === '') {
            $errors[] = 'Title is required.';
        }

        $dateObj = null;
        if ($data['release_date'] !== '') {
            $dateObj = \DateTime::createFromFormat('Y-m-d', $data['release_date']) ?: null;
            if (!$dateObj) {
                $errors[] = 'Release date must be a valid YYYY-MM-DD date.';
            }
        }

        if ($data['rating'] !== '' && (!preg_match('/^\d{1,2}(\.\d)?$/', $data['rating']) || (float) $data['rating'] > 10)) {
            $errors[] = 'Rating must be at most 10 with one decimal place (e.g. 7.5).';
        }

        if ($errors) {
            return [null, $errors];
        }

        $movie = $movie ?? new Movie();

        $movie->setTitle($data['title']);
        $movie->setOverview($data['overview'] !== '' ? $data['overview'] : null);
        $movie->setPosterPath($data['poster_path'] !== '' ? $data['poster_path'] : null);
        $movie->setBackdropPath($data['backdrop_path'] !== '' ? $data['backdrop_path'] : null);
        $movie->setTrailerKey($data['trailer_key'] !== '' ? $data['trailer_key'] : null);
        $movie->setReleaseDate($dateObj);
        $movie->setRating($data['rating'] !== '' ? $data['rating'] : null);
        $movie->setIsPremium($data['is_premium']);

        // tmdb_id is NOT NULL in the DB — auto-fill when the admin leaves it blank
        if ($data['tmdb_id'] !== '') {
            $movie->setTmdbId((int) $data['tmdb_id']);
        } elseif ($movie->getTmdbId() === null) {
            $movie->setTmdbId(time()); // unique-ish placeholder that fits an INT column
        }

        $movie->getGenres()->clear();
        foreach ((array) $request->request->all('genres') as $gid) {
            $genre = $em->getRepository(Genre::class)->find((int) $gid);
            if ($genre) {
                $movie->addGenre($genre);
            }
        }

        return [$movie, []];
    }

    private function formView(?Movie $movie, Request $request, EntityManagerInterface $em, array $errors): array
    {
        $selected = $movie
            ? array_map(fn (Genre $g) => $g->getId(), $movie->getGenres()->toArray())
            : [];
        if ($request->isMethod('POST')) {
            $selected = array_map('intval', (array) $request->request->all('genres'));
        }

        return [
            'movie'    => $movie,
            'genres'   => $em->getRepository(Genre::class)->findBy([], ['name' => 'ASC']),
            'selected' => $selected,
            'errors'   => $errors,
            'old'      => $request->isMethod('POST') ? $request->request->all() : [],
        ];
    }

    private function trySet(object $entity, string $method, array $candidates): void
    {
        foreach ($candidates as $value) {
            try {
                $entity->{$method}($value);
                return;
            } catch (\Throwable) {
                // wrong type for that setter — try the next shape
            }
        }
    }

    // ================================================================ runtime schema helpers

    /**
     * ManagerRegistry speaks the generic "persistence" dialect: its
     * getManager() is typed as ObjectManager, whose getRepository()
     * only promises ObjectRepository. The real object is always
     * Doctrine's EntityRepository — the assert makes the editor
     * check the box instead of trusting the label.
     */
    private function repo(ManagerRegistry $doctrine, string $class): EntityRepository
    {
        $repository = $doctrine->getManager()->getRepository($class);

        \assert($repository instanceof EntityRepository);

        return $repository;
    }

    private function em(ManagerRegistry $doctrine): EntityManagerInterface
    {
        $em = $doctrine->getManager();

        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

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

    private function idColumn(Connection $conn, string $table): ?string
    {
        $cols = $this->tableColumns($conn, $table);
        return $this->pick($cols, ['id']) ?? ($cols[0] ?? null);
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

        $selects = ['`id`', "`$nameC` AS name"];
        if ($priceC) { $selects[] = "`$priceC` AS price"; }

        $plans = [];
        foreach ($conn->fetchAllAssociative('SELECT ' . implode(', ', $selects) . " FROM `$table` ORDER BY `id`") as $row) {
            $plans[] = [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'price' => isset($row['price']) && $row['price'] !== null ? number_format((float) $row['price'], 2) : null,
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

    private function pendingCount(Connection $conn): int
    {
        [$subs, , , $sc] = $this->subsColumns($conn);
        if (!$subs || !$sc) {
            return 0;
        }
        return (int) $conn->fetchOne("SELECT COUNT(*) FROM `$subs` WHERE `$sc` = 'pending'");
    }

    private function pendingRows(Connection $conn, EntityManagerInterface $em): array
    {
        [$subs, $uc, $pc, $sc] = $this->subsColumns($conn);
        if (!$subs || !$uc || !$pc || !$sc) {
            return [];
        }
        $subsIdC = $this->idColumn($conn, $subs);
        if (!$subsIdC) {
            return [];
        }

        // ask Doctrine for the REAL users table/column names — never guess the schema
        $userMeta     = $em->getClassMetadata(User::class);
        $usersTable   = $userMeta->getTableName();
        $usersIdCol   = $userMeta->getColumnName('id');
        $usersNameCol = $userMeta->getColumnName('name');

        $plansTable = $this->plansTable($conn);
        $planNameC  = null;
        $planIdC    = null;
        if ($plansTable) {
            $planCols = $this->tableColumns($conn, $plansTable);
            $planNameC = $this->pick($planCols, self::NAME_COLS);
            $planIdC   = $this->pick($planCols, ['id']) ?? ($planCols[0] ?? null);
        }

        $sql = "SELECT s.`$subsIdC` AS sid, s.`$pc` AS plan_id, u.`$usersIdCol` AS uid, u.`$usersNameCol` AS user_name"
            . ($plansTable && $planNameC && $planIdC ? ", p.`$planNameC` AS plan_name" : '')
            . " FROM `$subs` s JOIN `$usersTable` u ON u.`$usersIdCol` = s.`$uc`"
            . ($plansTable && $planNameC && $planIdC ? " LEFT JOIN `$plansTable` p ON p.`$planIdC` = s.`$pc`" : '')
            . " WHERE s.`$sc` = 'pending' ORDER BY s.`$subsIdC` DESC LIMIT 10";

        $rows = [];
        foreach ($conn->fetchAllAssociative($sql) as $r) {
            $rows[] = [
                'id'        => (int) $r['sid'],
                'user_id'   => (int) $r['uid'],
                'user_name' => (string) $r['user_name'],
                'plan_id'   => (int) $r['plan_id'],
                'plan_name' => $r['plan_name'] ?? ('Plan #' . $r['plan_id']),
            ];
        }
        return $rows;
    }

    /** @return array{0: array<int,int>, 1: array<int,bool>} */
    private function userSubscriptionMaps(Connection $conn): array
    {
        $activeMap = [];
        $pendingIds = [];

        [$subs, $uc, $pc, $sc] = $this->subsColumns($conn);
        if ($subs && $uc && $pc && $sc) {
            foreach ($conn->fetchAllAssociative("SELECT `$uc` AS uid, `$pc` AS pid, `$sc` AS st FROM `$subs`") as $row) {
                if (($row['st'] ?? null) === 'active') {
                    $activeMap[(int) $row['uid']] = (int) $row['pid'];
                } elseif (($row['st'] ?? null) === 'pending') {
                    $pendingIds[(int) $row['uid']] = true;
                }
            }
        }

        return [$activeMap, $pendingIds];
    }
}