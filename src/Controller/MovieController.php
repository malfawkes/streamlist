<?php

namespace App\Controller;

use App\Entity\Movie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MovieController extends AbstractController
{
    #[Route('/movie/{id}', name: 'app_movie', requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]      // your old requireLogin()
    public function show(Movie $movie): Response
    {
        return $this->render('movie/index.html.twig', [
            'movie' => $movie,
        ]);
    }
}