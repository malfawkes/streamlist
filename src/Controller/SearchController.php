<?php

namespace App\Controller;

use App\Repository\MovieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    // GET on purpose: search is a read. Bookmarkable URLs, no CSRF.
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function index(Request $request, MovieRepository $movieRepo): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        // Guard 1: no term at all → back to browsing.
        if ($q === '') {
            return $this->redirectToRoute('app_movies');
        }

        // Guard 2: one character would match half the catalog.
        if (mb_strlen($q) < 2) {
            $this->addFlash('warning', 'Type at least 2 characters to search.');
            return $this->redirectToRoute('app_movies');
        }

        return $this->render('search/index.html.twig', [
            'q'       => $q,
            'results' => $movieRepo->searchByTitle($q),
        ]);
    }
}