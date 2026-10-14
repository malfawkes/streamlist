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

final class WatchlistController extends AbstractController
{
    // ── THE PAGE: my watchlist ──────────────────────────────────
    #[Route('/watchlist', name: 'app_watchlist')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(WatchlistItemRepository $repo): Response
    {
        $items = $repo->findForUser($this->getUser());

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
    ): Response {
        // CSRF — your token, framework-run
        if (!$this->isCsrfTokenValid('watchlist_add_' . $movie->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request.');
            return $this->redirectToRoute('app_movies');
        }

        // duplicate check — your "already in watchlist" (the UNIQUE
        // constraint still backs this up at the DB level)
        if ($repo->findOneForUser($this->getUser(), $movie->getId())) {
            $this->addFlash('error', 'Already in your watchlist.');
            return $this->redirectToRoute('app_movies');
        }

        // THE WRITE PATTERN: create → relate → persist → flush
        $item = new WatchlistItem();          // createdAt set in constructor ✓
        $item->setUser($this->getUser());     // identity from the SESSION —
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
        $item = $repo->findOneForUser($this->getUser(), $movie->getId());
        if (!$item) {
            $this->addFlash('error', 'Not in your watchlist.');
            return $this->redirectToRoute('app_watchlist');
        }

        $em->remove($item);
        $em->flush();

        $this->addFlash('success', 'Removed from your watchlist.');
        return $this->redirectToRoute('app_watchlist');
    }
}