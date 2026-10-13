<?php

namespace App\Controller;

use App\Repository\GenreRepository;
use App\Repository\MovieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MoviesController extends AbstractController
{
    #[Route('/movies', name: 'app_movies')]
    public function index(
        Request $request,
        MovieRepository $movieRepository,
        GenreRepository $genreRepository,
    ): Response {
        $page = max(1, (int) $request->query->get('page', 1));

        $genre = null;
        $rawGenre = $request->query->get('genre');
        if ($rawGenre !== null && $rawGenre !== '') {
            $genre = $genreRepository->find((int) $rawGenre);
            if (!$genre) {
                return $this->redirectToRoute('app_movies');
            }
        }

        $sort = $request->query->get('sort', 'date');
        if (!in_array($sort, ['date', 'rating', 'title'], true)) {
            $sort = 'date';
        }

        $perPage = 24;
        $total = $movieRepository->countFiltered($genre?->getId());
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        return $this->render('movies/index.html.twig', [
            'movies'       => $movieRepository->findPaginated($page, $perPage, $genre?->getId(), $sort),
            'genres'       => $genreRepository->findAllWithCounts(),
            'currentGenre' => $genre,
            'sort'         => $sort,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'total'        => $total,
        ]);
    }
}