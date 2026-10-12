<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\EstablishmentRepository;
use App\Repository\ReviewRepository;
use App\Security\DemoAccountGuard;
use App\Service\AccountDeletionService;
use App\Service\AccountExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Réglages › Mon compte : identifiants, export des données, suppression du compte.
 */
#[Route('/dashboard/compte')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly EstablishmentRepository $establishmentRepository,
        private readonly ReviewRepository $reviewRepository,
        private readonly DemoAccountGuard $demoGuard,
    ) {
    }

    #[Route('', name: 'account', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->currentUser();
        $establishments = $this->establishmentRepository->findBy(['owner' => $user], ['name' => 'ASC']);

        $reviewCount = [] === $establishments ? 0 : $this->reviewRepository->count(['establishment' => $establishments]);

        return $this->render('dashboard/account.html.twig', [
            'establishments' => $establishments,
            'current_establishment' => $establishments[0] ?? null,
            'review_count' => $reviewCount,
            'is_demo' => $this->demoGuard->isDemo($user),
            'unread_count' => 0,
        ]);
    }

    #[Route('/export', name: 'account_export', methods: ['GET'])]
    public function export(AccountExportService $exporter): JsonResponse
    {
        $response = new JsonResponse(
            $exporter->export($this->currentUser()),
            Response::HTTP_OK,
            [],
            false,
        );
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'monavispro-mes-donnees-'.date('Y-m-d').'.json',
        ));

        return $response;
    }

    #[Route('/supprimer', name: 'account_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        UserPasswordHasherInterface $hasher,
        AccountDeletionService $deletion,
        Security $security,
        RateLimiterFactoryInterface $apiLoginLimiter,
    ): Response {
        $user = $this->currentUser();

        if (!$this->isCsrfTokenValid('delete-account', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Formulaire expiré, recommencez.');

            return $this->redirectToRoute('account');
        }

        if ($this->demoGuard->isDemo($user)) {
            $this->addFlash('error', 'Le compte démo ne peut pas être supprimé.');

            return $this->redirectToRoute('account');
        }

        // Même limite que la connexion : on ne devine pas un mot de passe par ce formulaire.
        $limiter = $apiLoginLimiter->create($request->getClientIp().'|'.$user->getUserIdentifier());
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop d\'essais. Réessayez dans quelques minutes.');

            return $this->redirectToRoute('account');
        }

        if (!$hasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $this->addFlash('error', 'Mot de passe incorrect : votre compte n\'a pas été supprimé.');

            return $this->redirectToRoute('account');
        }

        $deletion->delete($user);
        $security->logout(false);
        $this->addFlash('success', 'Votre compte et toutes vos données ont été supprimés. Merci d\'avoir utilisé MonAvisPro.');

        return $this->redirectToRoute('app_home');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
