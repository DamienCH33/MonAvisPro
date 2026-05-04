<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemoController extends AbstractController
{
    #[Route('/demo', name: 'demo_login')]
    public function demoLogin(
        UserRepository $userRepository,
        Security $security
    ): Response {
        $demoUser = $userRepository->findOneBy(['email' => 'demo@monavispro.fr']);

        if (!$demoUser) {
            $this->addFlash('error', 'Compte démo non disponible.');
            return $this->redirectToRoute('app_login');
        }

        $security->login($demoUser);

        $this->addFlash('success', 'Bienvenue en mode démo ! Découvrez MonAvisPro avec un compte de test.');

        return $this->redirectToRoute('dashboard');
    }
}
