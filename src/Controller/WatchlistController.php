<?php

namespace App\Controller;

use App\Entity\WatchlistItem;
use App\Repository\WatchlistItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Movie;
use App\Entity\User;
use App\Service\PremiumAccess;

final class WatchlistController extends AbstractController
{
    // ── THE PAGE: my watchlist ──────────────────────────────────
    #[Route('/watchlist', name: 'app_watchlist')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(WatchlistItemRepository $repo): Response
    {
        $items = $repo->findForUser($this->currentUser());

        return $this->render('watchlist/index.html.twig', [
            'items' => $items,
            'count' => count($items),
        ]);
    }

    // ── ADD ──────────────────────────────────────────────────────
    // methods: POST-only = your guard, enforced by routing.
    // Movie $movie = ParamConverter: {id} → fetch → 404 if missing.
    //   Your filter_var + not-found guard, type-hinted.
    #[Route('/watchlist/add/{id}', name: 'app_watchlist_add', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function add(
        Movie $movie,
        Request $request,
        EntityManagerInterface $em,
        WatchlistItemRepository $repo,
        PremiumAccess $premium,
    ): Response {
        // CSRF — your token, framework-run
        if (!$this->isCsrfTokenValid('watchlist_add_' . $movie->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request.');
            return $this->redirectToRoute('app_movies');
        }

        // ── TYPE NARROWING ──
        // getUser() only promises ?UserInterface — an interface that knows
        // nothing about hasActivePremium(). currentUser() returns OUR
        // App\Entity\User, so PHP and the editor both can see the method.
        $user = $this->currentUser();

        // ONE ask, both sources of truth (users.tier AND the approved
        // subscription row — see PremiumAccess). A subscribed member whose
        // tier never got dual-written still gets in.
        $hasPremium = $premium->hasPremiumAccess($user);

        // ── PREMIUM GATE — the real bouncer lives here, not in the template ──
        if ($movie->isPremium() && !$hasPremium) {
            $this->addFlash('error', sprintf(
                '"%s" is a ★ PREMIUM title — upgrade your plan to add it to your watchlist.',
                $movie->getTitle()
            ));
            return $this->redirectToRoute('app_pricing');
        }

        // ── FREE-TIER CAP — the 20-title promise on the About page ──
        // (was FREE_TIER_WATCHLIST_LIMIT in the old app; premium lifts the cap)
        if (!$hasPremium && $repo->countForUser($user) >= 20) {
            $this->addFlash('error', 'Free watchlist is full (20 titles). Upgrade for unlimited.');
            return $this->redirectToRoute('app_pricing');
        }

        // duplicate check — your "already in watchlist" (the UNIQUE
        // constraint still backs this up at the DB level)
        if ($repo->findOneForUser($user, $movie->getId())) {
            $this->addFlash('error', 'Already in your watchlist.');
            return $this->redirectToRoute('app_movies');
        }

        // THE WRITE PATTERN: create → relate → persist → flush
        $item = new WatchlistItem();          // createdAt set in constructor ✓
        $item->setUser($user);                // identity from the SESSION —
        $item->setMovie($movie);              // never from the form. Your law.

        $em->persist($item);
        $em->flush();

        $this->addFlash('success', 'Added to your watchlist.');
        return $this->redirectToRoute('app_watchlist');
    }

    // ── REMOVE ───────────────────────────────────────────────────
    #[Route('/watchlist/remove/{id}', name: 'app_watchlist_remove', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function remove(
        Movie $movie,
        Request $request,
        EntityManagerInterface $em,
        WatchlistItemRepository $repo,
    ): Response {
        if (!$this->isCsrfTokenValid('watchlist_remove_' . $movie->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request.');
            return $this->redirectToRoute('app_watchlist');
        }

        // scoped delete: THIS user's row for THIS movie — never just movie_id
        $item = $repo->findOneForUser($this->currentUser(), $movie->getId());
        if (!$item) {
            $this->addFlash('error', 'Not in your watchlist.');
            return $this->redirectToRoute('app_watchlist');
        }

        $em->remove($item);
        $em->flush();

        $this->addFlash('success', 'Removed from your watchlist.');
        return $this->redirectToRoute('app_watchlist');
    }

    // ── TYPE NARROWING HELPER ───────────────────────────────────
    /**
     * getUser() promises only ?UserInterface; the entity provider behind
     * the firewall always yields App\Entity\User. This narrows the type so
     * the editor (and PHP) can see hasActivePremium() etc. — and acts as a
     * belt-and-braces guard if a route ever loses its IsGranted attribute.
     */
    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Not logged in.');
        }

        return $user;
    }
}