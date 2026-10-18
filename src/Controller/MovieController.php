<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Entity\User;
use App\Repository\MovieCreditRepository;
use App\Repository\WatchlistItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class MovieController extends AbstractController
{
    #[Route(
        '/movie/{id}',
        name: 'app_movie_show',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function show(
        Movie $movie,
        WatchlistItemRepository $watchlistRepo,
        MovieCreditRepository $creditRepo,
        CsrfTokenManagerInterface $csrf
    ): Response {
        $inWatchlist = false;
        $user = $this->getUser();

        if ($user instanceof User) {
            $existing = $watchlistRepo->findOneBy([
                'user' => $user,
                'movie' => $movie,
            ]);
            $inWatchlist = $existing !== null;
        }

        $action  = $inWatchlist ? 'app_watchlist_remove' : 'app_watchlist_add';
        $tokenId = ($inWatchlist ? 'watchlist_remove_' : 'watchlist_add_') . $movie->getId();

        return $this->render('movie/show.html.twig', [
            'movie'            => $movie,
            'cast'             => $creditRepo->findTopCast($movie),
            'in_watchlist'     => $inWatchlist,
            'watchlist_action' => $action,
            'watchlist_token'  => $csrf->getToken($tokenId)->getValue(),
        ]);
    }
}