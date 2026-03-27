<?php

namespace App\Controller\Api;

use App\Entity\Establishment;
use App\Repository\EstablishmentRepository;
use App\Service\ReviewSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/establishments')]
class EstablishmentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EstablishmentRepository $establishmentRepository,
    ) {}

    #[Route('', name: 'api_establishments_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $establishments = $this->establishmentRepository->findBy(
            ['owner' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->json(
            array_map(fn(Establishment $e) => $this->serialize($e), $establishments)
        );
    }

    #[Route('', name: 'api_establishments_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom est requis.'], 422);
        }

        if (empty($data['placeId'])) {
            return $this->json(['error' => 'Le Google Place ID est requis.'], 422);
        }

        if (empty($data['address'])) {
            return $this->json(['error' => 'L\'adresse est requise.'], 422);
        }

        $existing = $this->establishmentRepository->findOneBy(['placeId' => $data['placeId']]);
        if ($existing) {
            return $this->json(['error' => 'Cet établissement existe déjà.'], 422);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $establishment = new Establishment();
        $establishment->setOwner($user);
        $establishment->setName($data['name']);
        $establishment->setPlaceId($data['placeId']);
        $establishment->setAddress($data['address']);
        $establishment->setAlertsEnabled($data['alertsEnabled'] ?? true);

        $this->em->persist($establishment);
        $this->em->flush();

        return $this->json($this->serialize($establishment), 201);
    }

    #[Route('/{id}', name: 'api_establishments_show', methods: ['GET'])]
    public function show(Establishment $establishment): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessOwner($establishment);

        return $this->json($this->serialize($establishment));
    }

    #[Route('/{id}', name: 'api_establishments_update', methods: ['PATCH'])]
    public function update(Establishment $establishment, Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessOwner($establishment);

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $establishment->setName($data['name']);
        }

        if (isset($data['address'])) {
            $establishment->setAddress($data['address']);
        }

        if (isset($data['alertsEnabled'])) {
            $establishment->setAlertsEnabled((bool) $data['alertsEnabled']);
        }

        $this->em->flush();

        return $this->json($this->serialize($establishment));
    }

    #[Route('/{id}', name: 'api_establishments_delete', methods: ['DELETE'])]
    public function delete(Establishment $establishment): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessOwner($establishment);

        $this->em->remove($establishment);
        $this->em->flush();

        return $this->json(['message' => 'Établissement supprimé.']);
    }

    #[Route('/{id}/sync', name: 'api_establishments_sync', methods: ['POST'])]
    public function sync(
        Establishment $establishment,
        ReviewSyncService $reviewSyncService
    ): JsonResponse {

        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessOwner($establishment);

        $count = $reviewSyncService->sync($establishment);

        return $this->json([
            'message' => 'Sync terminée',
            'newReviews' => $count,
            'lastSyncAt' => $establishment->getLastSyncAt()?->format('c')
        ]);
    }

    private function serialize(Establishment $e): array
    {
        return [
            'id'            => $e->getId(),
            'name'          => $e->getName(),
            'placeId'       => $e->getPlaceId(),
            'address'       => $e->getAddress(),
            'alertsEnabled' => $e->isAlertsEnabled(),
            'lastSyncAt'    => $e->getLastSyncAt()?->format('c'),
            'createdAt'     => $e->getCreatedAt()->format('c'),
            'reviewsCount'  => $e->getReviews()->count(),
        ];
    }

    private function denyAccessUnlessOwner(Establishment $establishment): void
    {
        if ($establishment->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}
