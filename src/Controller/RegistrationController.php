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
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserRegistrationService $userRegistrationService,
    ) {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, Security $security, RateLimiterFactoryInterface $registrationLimiter): Response
    {
        if ($this->getParameter('app.demo_mode') || !$this->getParameter('app.registration_enabled')) {
            throw $this->createNotFoundException('Les inscriptions sont fermées.');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$registrationLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de créations de compte depuis votre connexion. Réessayez dans une heure.');

            return $this->render('security/register.html.twig', [
                'registrationForm' => $form,
            ], new Response(null, Response::HTTP_TOO_MANY_REQUESTS));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            try {
                $this->userRegistrationService->registerFromForm($user, $plainPassword);
            } catch (EmailAlreadyUsedException) {
                // Message neutre : ne pas confirmer qu'une adresse a déjà un compte.
                $this->addFlash('error', 'Inscription impossible avec ces informations. Si vous avez déjà un compte, connectez-vous ou utilisez « Mot de passe oublié ».');

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
