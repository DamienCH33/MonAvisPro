<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailAlreadyUsedException;
use App\Service\UserRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserRegistrationService $userRegistrationService,
    ) {}

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, Security $security): Response
    {
        if ($this->getParameter('app.demo_mode')) {
            throw $this->createAccessDeniedException('Inscription désactivée en mode démonstration.');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            try {
                $this->userRegistrationService->registerFromForm($user, $plainPassword);
            } catch (EmailAlreadyUsedException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            return $security->login($user, 'form_login', 'main');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
