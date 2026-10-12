<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EstablishmentRepository;
use App\Repository\ReviewRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EstablishmentRepository $establishmentRepository,
        private ReviewRepository $reviewRepository,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('', name: 'dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $establishments = $this->establishmentRepository->findBy(
            ['owner' => $user],
            ['createdAt' => 'DESC']
        );

        // Un établissement au moins : on ouvre directement son tableau de bord.
        if ([] !== $establishments) {
            return $this->redirectToRoute('dashboard_establishment', ['id' => (string) $establishments[0]->getId()]);
        }

        // Aucun établissement : écran de démarrage.
        return $this->render('dashboard/dashboard.html.twig', [
            'establishments' => [],
            'current_establishment' => null,
            'stats' => null,
            'reviews' => [],
            'unread_count' => 0,
            'jwt_token' => $this->jwtManager->create($user),
        ]);
    }

    /** Avis affichés d'un coup dans « À traiter », puis par tranche avec « Afficher plus ». */
    private const INBOX_PAGE = 100;
    private const INBOX_STEP = 50;
    private const INBOX_MAX = 1000;

    #[Route('/a-traiter', name: 'inbox')]
    public function inbox(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $limit = max(self::INBOX_PAGE, min(self::INBOX_MAX, $request->query->getInt('afficher', self::INBOX_PAGE)));

        $establishments = $this->establishmentRepository->findBy(['owner' => $user], ['name' => 'ASC']);
        $reviews = $this->reviewRepository->findUnansweredForOwner($user, $limit);
        $counts = $this->reviewRepository->countUnansweredByBucketForOwner($user);

        return $this->render('dashboard/inbox.html.twig', [
            'establishments' => $establishments,
            'current_establishment' => null,
            'reviews' => $reviews,
            'counts' => $counts,
            'total' => $counts['all'],
            'shown' => count($reviews),
            'previous_limit' => $limit > self::INBOX_PAGE ? $limit - self::INBOX_STEP : null,
            'next_limit' => min(self::INBOX_MAX, $limit + self::INBOX_STEP),
            'step' => self::INBOX_STEP,
            'unread_count' => 0,
            'jwt_token' => $this->jwtManager->create($user),
        ]);
    }

    #[Route('/{id}', name: 'dashboard_establishment', requirements: ['id' => Requirement::UUID])]
    public function establishment(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $current = $this->establishmentRepository->find($id);

        if (!$current || $current->getOwner() !== $user) {
            throw $this->createNotFoundException();
        }

        $establishments = $this->establishmentRepository->findBy(
            ['owner' => $user],
            ['createdAt' => 'DESC']
        );

        $counts = $this->reviewRepository->getDashboardStats($current);
        $total = $counts['total'];
        $unread = $counts['unread'];
        $unanswered = $counts['unanswered'];

        $stats = [
            'average' => $total > 0 ? round($counts['sum'] / $total, 1) : null,
            'total' => $total,
            'positiveRate' => $total > 0 ? round(($counts['positive'] / $total) * 100) : 0,
            'negativeRate' => $total > 0 ? round(($counts['negative'] / $total) * 100) : 0,
            'unreadCount' => $unread,
            'unanswered' => $unanswered,
            'responseRate' => $total > 0 ? (int) round(($counts['replied'] / $total) * 100) : 0,
            'curve' => $this->reviewRepository->getAverageRatingByMonth($current),
            'repartition' => $counts['repartition'],
        ];

        $reviews = $this->reviewRepository->findBy(
            ['establishment' => $current],
            ['publishedAt' => 'DESC'],
            5
        );

        $token = $this->jwtManager->create($user);

        return $this->render('dashboard/dashboard.html.twig', [
            'establishments' => $establishments,
            'current_establishment' => $current,
            'stats' => $stats,
            'reviews' => $reviews,
            'unread_count' => $unread,
            'jwt_token' => $token,
        ]);
    }

    #[Route('/{id}/reviews', name: 'reviews_list', requirements: ['id' => Requirement::UUID])]
    public function reviews(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $current = $this->establishmentRepository->find($id);

        if (!$current || $current->getOwner() !== $user) {
            throw $this->createNotFoundException();
        }

        $establishments = $this->establishmentRepository->findBy(['owner' => $user]);
        $token = $this->jwtManager->create($user);
        $unread = $this->reviewRepository->countUnreadForEstablishment($current);

        return $this->render('dashboard/reviews.html.twig', [
            'establishments' => $establishments,
            'current_establishment' => $current,
            'unread_count' => $unread,
            'jwt_token' => $token,
        ]);
    }

    #[Route('/{id}/analysis', name: 'analysis', requirements: ['id' => Requirement::UUID])]
    public function analysis(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $current = $this->establishmentRepository->find($id);

        if (!$current || $current->getOwner() !== $user) {
            throw $this->createNotFoundException();
        }

        $establishments = $this->establishmentRepository->findBy(['owner' => $user]);
        $token = $this->jwtManager->create($user);

        return $this->render('dashboard/analysis.html.twig', [
            'establishments' => $establishments,
            'current_establishment' => $current,
            'analysis' => $current->getReviewAnalysis(),
            'unread_count' => 0,
            'jwt_token' => $token,
        ]);
    }

    #[Route('/{id}/settings', name: 'settings', requirements: ['id' => Requirement::UUID])]
    public function settings(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $current = $this->establishmentRepository->find($id);

        if (!$current || $current->getOwner() !== $user) {
            throw $this->createNotFoundException();
        }

        $establishments = $this->establishmentRepository->findBy(['owner' => $user]);
        $token = $this->jwtManager->create($user);

        return $this->render('dashboard/settings.html.twig', [
            'establishments' => $establishments,
            'current_establishment' => $current,
            'unread_count' => 0,
            'jwt_token' => $token,
        ]);
    }

    #[Route('/establishment/new', name: 'establishment_new')]
    public function newEstablishment(): Response
    {
        $token = $this->jwtManager->create($this->getUser());

        $establishments = $this->establishmentRepository->findBy(
            ['owner' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('dashboard/establishment_new.html.twig', [
            'establishments' => $establishments,
            'current_establishment' => null,
            'unread_count' => 0,
            'jwt_token' => $token,
        ]);
    }
}
