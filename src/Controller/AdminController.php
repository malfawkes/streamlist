<?php

namespace App\Controller;

use App\Repository\GenreRepository;
use App\Repository\MovieRepository;
use App\Repository\UserRepository;
use App\Repository\WatchlistItemRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'app_admin_')]
class AdminController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $userRepo,
        MovieRepository $movieRepo,
        GenreRepository $genreRepo,
        WatchlistItemRepository $watchlistRepo,
        Connection $connection
    ): Response {
        // No entity for subscription_requests (yet) — DBAL counts it raw.
        // Same lane as GenreRepository::findAllWithCounts.
        $pendingSubscriptions = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM subscription_requests WHERE status = :status',
            ['status' => 'pending']
        );

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users'                => $userRepo->count([]),
                'movies'               => $movieRepo->countAll(),
                'genres'               => $genreRepo->countAll(),
                'watchlist_adds'       => $watchlistRepo->count([]),
                'pending_subscriptions'=> $pendingSubscriptions,
            ],
        ]);
    }
}