<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'hero' => [
                'title' => 'Demon Slayer: Kimetsu no Yaiba Infinity Castle',
                'overview' => 'The Demon Slayer Corps are drawn into the Infinity Castle, where Tanjiro, Nezuko, and the Hashira face terrifying Upper Rank demons in a desperate fight as the final battle against Muzan Kibutsuji begins.',
                'rating' => 8.8,
                'backdrop_path' => '/1RgPyOhN4DRs225BGTlHJqCudII.jpg',
                'poster_path' => '/fWVSwgjpT2D78VUh6X8UBd2rorW.jpg',
                'release_date' => '2025-07-18',
                'is_premium' => false
            ],
            'movieCount' => 1000,
            'genreCount' => 50,
            'trending' => [
                [
                    'title' => 'Spider-Man: No Way Home',
                    'overview' => 'With Spider-Man\'s identity now revealed, Peter asks Doctor Strange for help. When a spell goes wrong, dangerous foes from other worlds start to appear, forcing Peter to discover what it truly means to be Spider-Man.',
                    'rating' => 10.0,
                    'backdrop_path' => '/qeQJx07rK2xm8SD2sJxFKhE7gs0.jpg',
                    'poster_path' => '/bjiS5ipwxb9JFy3XRRN4OAilSeX.jpg',
                    'release_date' => '2021-12-15',
                    'is_premium' => true
                ],
                [
                    'title' => 'Doctor Strange in the Multiverse of Madness',
                    'overview' => 'Dr. Stephen Strange casts a forbidden spell that opens the doorway to the multiverse, including alternate versions of himself, whose threat to humanity is too great for the combined forces of Strange, Wong, and Wanda Maximoff.',
                    'rating' => 7.5,
                    'backdrop_path' => '/9Gtg2DzBhmYamXBS1hKAhiwbBKS.jpg',
                    'poster_path' => '/9Gtg2DzBhmYamXBS1hKAhiwbBKS.jpg',
                    'release_date' => '2022-05-04',
                    'is_premium' => false
                ],
                [
                    'title' => 'Jurassic World Dominion',
                    'overview' => 'Four years after Isla Nublar was destroyed, dinosaurs now live—and hunt—alongside humans all over the world. This fragile balance will reshape the future and determine, once and for all, whether human beings are to remain the apex predators on a planet they now share with history’s most fearsome creatures.',
                    'rating' => 6.5,
                    'backdrop_path' => '/kAVRgw7GgK1CfYEJq8ME6EvRIgU.jpg',
                    'poster_path' => '/kAVRgw7GgK1CfYEJq8ME6EvRIgU.jpg',
                    'release_date' => '2022-06-10',
                    'is_premium' => false
                ],
                [
                    'title' => 'The Flash',
                    'overview' => 'Barry Allen uses his super speed to change the past, but his attempt to save his family creates a world without superheroes, forcing him to race for his life to save the future.',
                    'rating' => 7.0,
                    'backdrop_path' => '/fki3kBlwJzFp8QohL43g9ReV455.jpg',
                    'poster_path' => '/fki3kBlwJzFp8QohL43g9ReV455.jpg',
                    'release_date' => '2023-06-16',
                    'is_premium' => true
                ],
                [
                    'title' => 'Avatar: The Way of Water',
                    'overview' => 'Set more than a decade after the events of the first film, learn the story of the Sully family (Jake, Neytiri, and their kids), the trouble that follows them, the lengths they go to keep each other safe, the battles they fight to stay alive, and the tragedies they endure.',
                    'rating' => 8.0,
                    'backdrop_path' => '/t6HIqrRAclMCA60NsSmeqe9RmNV.jpg',
                    'poster_path' => '/t6HIqrRAclMCA60NsSmeqe9RmNV.jpg',
                    'release_date' => '2022-12-16',
                    'is_premium' => false
                ]
            ]
        ]);
    }
}
