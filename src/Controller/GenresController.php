<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GenresController extends AbstractController
{
    #[Route('/genres', name: 'app_genres', methods: ['GET'])]
    public function index(Connection $connection): Response
    {
        $genres = $connection->fetchAllAssociative(
            'SELECT g.id, g.name, COUNT(mg.movie_id) AS movie_count
             FROM genres g
             LEFT JOIN movie_genres mg ON mg.genre_id = g.id
             GROUP BY g.id, g.name
             ORDER BY g.name ASC'
        );

        return $this->render('genres/index.html.twig', [
            'genres' => $genres,
        ]);
    }
}