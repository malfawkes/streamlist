<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MovieRepository;
use App\Repository\GenreRepository;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(MovieRepository $movieRepository, GenreRepository $genreRepository): Response
    {
        return $this->render('home/index.html.twig', [
            // controller action arguments = AUTOWIRING: Symfony sees the type-hint,
            // builds/injects the repository. You never new anything. (Your first
            // dependency injection — the concept I flagged as "genuinely new")
            'trending'    => $movieRepository->findTrending(5),
            'hero'        => $movieRepository->findHero(),
            'movieCount'  => $movieRepository->countAll(),
            'genreCount'  => $genreRepository->countAll(),

        ]);
    }
}
