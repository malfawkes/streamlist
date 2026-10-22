<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createFormBuilder($user)
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank(message: 'Name cannot be blank.')],
            ])
            ->add('avatar', FileType::class, [
                'mapped'      => false,
                'required'    => false,
                'constraints' => [new Image(maxSize: '2m')],
            ])
            ->add('save', SubmitType::class, ['label' => 'Save changes'])
            ->getForm();

        // The form's processor skeleton: read the request, validate, then act.
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 'name' needed zero lines from us — already on $user.

            $file = $form->get('avatar')->getData();
            if ($file !== null) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';

                // Clean up the previous file (guard: first upload has none)
                $oldPath = $user->getAvatarPath();
                if ($oldPath !== null) {
                    $oldFile = $uploadsDir . '/' . basename($oldPath);
                    if (is_file($oldFile)) {
                        unlink($oldFile);
                    }
                }

                // Never trust the user's filename — ours is collision-proof.
                $filename = uniqid('avatar_', true) . '.' . $file->guessExtension();
                $file->move($uploadsDir, $filename);

                $user->setAvatarPath('uploads/avatars/' . $filename);
            }

            // $user is a managed entity — flush is enough, no persist.
            $entityManager->flush();

            $this->addFlash('success', 'Profile updated.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'profileForm' => $form->createView(),
        ]);
    }
}