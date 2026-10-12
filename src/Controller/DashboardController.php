<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EstablishmentRepository;
use App\Repository\ReviewRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/a-traiter', name: 'inbox')]
    public function inbox(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $establishments = $this->establishmentRepository->findBy(['owner' => $user], ['name' => 'ASC']);
        $reviews = $this->reviewRepository->findUnansweredForOwner($user, 100);

        $counts = ['all' => 0, 'neg' => 0, 'mid' => 0, 'pos' => 0];
        foreach ($reviews as $review) {
            $rating = (int) $review->getRating();
            ++$counts['all'];
            ++$counts[$rating <= 2 ? 'neg' : (3 === $rating ? 'mid' : 'pos')];
        }

        return $this->render('dashboard/inbox.html.twig', [
            'establishments' => $establishments,
            'current_establishment' => null,
            'reviews' => $reviews,
            'counts' => $counts,
            'total' => $this->reviewRepository->countUnansweredForOwner($user),
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
            'responseRate' => $total > 0 ? (int) round((($total - $unanswered) / $total) * 100) : 0,
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
