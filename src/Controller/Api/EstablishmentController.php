<?php

namespace App\Controller\Api;

use App\Entity\Establishment;
use App\Repository\EstablishmentRepository;
use App\Security\DemoAccountGuard;
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
        private DemoAccountGuard $demoGuard,
    ) {
    }

    /** Identifiant Google Maps (ChIJ…) : lettres, chiffres, « _ » et « - » uniquement. */
    private const PLACE_ID_PATTERN = '/^[A-Za-z0-9_-]{10,255}$/';

    #[Route('', name: 'api_establishments_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $establishments = $this->establishmentRepository->findBy(
            ['owner' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->json(
            array_map(fn (Establishment $e) => $this->serialize($e), $establishments)
        );
    }

    #[Route('', name: 'api_establishments_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if ($this->demoGuard->isDemo($this->getUser())) {
            return $this->json(['error' => 'Action désactivée sur le compte démo.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Requête invalide.'], 400);
        }

        $data['name'] = $this->cleanText($data['name'] ?? null);
        $data['address'] = $this->cleanText($data['address'] ?? null);

        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom est requis.'], 422);
        }

        if (empty($data['placeId'])) {
            return $this->json(['error' => 'Le Google Place ID est requis.'], 422);
        }

        if (!is_string($data['placeId']) || !preg_match(self::PLACE_ID_PATTERN, $data['placeId'])) {
            return $this->json(['error' => 'Google Place ID invalide.'], 422);
        }

        if (mb_strlen((string) $data['name']) > 255 || mb_strlen((string) $data['address']) > 500) {
            return $this->json(['error' => 'Nom ou adresse trop long.'], 422);
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
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        return $this->json($this->serialize($establishment));
    }

    #[Route('/{id}', name: 'api_establishments_update', methods: ['PATCH'])]
    public function update(Establishment $establishment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Requête invalide.'], 400);
        }

        if (isset($data['name'])) {
            $name = $this->cleanText($data['name']);
            if (null === $name || mb_strlen($name) > 255) {
                return $this->json(['error' => 'Nom invalide.'], 422);
            }
            $establishment->setName($name);
        }

        if (isset($data['address'])) {
            $address = $this->cleanText($data['address']);
            if (null === $address || mb_strlen($address) > 500) {
                return $this->json(['error' => 'Adresse invalide.'], 422);
            }
            $establishment->setAddress($address);
        }

        if (isset($data['alertsEnabled'])) {
            $establishment->setAlertsEnabled((bool) $data['alertsEnabled']);
        }

        $error = $this->applyReplySettings($establishment, $data);
        if (null !== $error) {
            return $this->json(['error' => $error], 422);
        }

        $this->em->flush();

        return $this->json($this->serialize($establishment));
    }

    #[Route('/{id}', name: 'api_establishments_delete', methods: ['DELETE'])]
    public function delete(Establishment $establishment): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        if ($this->demoGuard->isDemo($this->getUser())) {
            return $this->json(['error' => 'Action désactivée sur le compte démo.'], 403);
        }

        $this->em->remove($establishment);
        $this->em->flush();

        return $this->json(['message' => 'Établissement supprimé.']);
    }

    #[Route('/{id}/sync', name: 'api_establishments_sync', methods: ['POST'])]
    public function sync(Establishment $establishment, ReviewSyncService $reviewSyncService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        $count = $reviewSyncService->sync($establishment);

        return $this->json([
            'message' => 'Sync terminée',
            'newReviews' => $count,
            'lastSyncAt' => $establishment->getLastSyncAt()?->format('c'),
        ]);
    }

    /**
     * Applique les réglages de réponse envoyés par l'écran Paramètres.
     *
     * @param array<string, mixed> $data
     *
     * @return string|null message d'erreur, ou null si tout est valide
     */
    private function applyReplySettings(Establishment $establishment, array $data): ?string
    {
        if (array_key_exists('replyFormality', $data)) {
            if (!in_array($data['replyFormality'], Establishment::FORMALITIES, true)) {
                return 'Choisissez « vous » ou « tu ».';
            }
            $establishment->setReplyFormality($data['replyFormality']);
        }

        if (array_key_exists('replyTone', $data)) {
            if (!in_array($data['replyTone'], Establishment::TONES, true)) {
                return 'Ton invalide.';
            }
            $establishment->setReplyTone($data['replyTone']);
        }

        if (array_key_exists('replySignature', $data)) {
            $signature = $this->cleanText($data['replySignature']);
            if (null !== $signature && mb_strlen($signature) > 120) {
                return 'La signature ne doit pas dépasser 120 caractères.';
            }
            $establishment->setReplySignature($signature);
        }

        if (array_key_exists('clientEmail', $data)) {
            $email = $this->cleanText($data['clientEmail']);
            if (null !== $email && (mb_strlen($email) > 180 || false === filter_var($email, FILTER_VALIDATE_EMAIL))) {
                return 'Adresse e-mail du commerçant invalide.';
            }
            $establishment->setClientEmail($email);
        }

        if (array_key_exists('replyInstructions', $data)) {
            $instructions = $this->cleanText($data['replyInstructions']);
            if (null !== $instructions && mb_strlen($instructions) > 1000) {
                return 'Les consignes ne doivent pas dépasser 1 000 caractères.';
            }
            $establishment->setReplyInstructions($instructions);
        }

        return null;
    }

    private function cleanText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return '' === $value ? null : $value;
    }

    /**
     * @return array{
     *     id: string|null,
     *     name: string,
     *     placeId: string,
     *     address: string,
     *     alertsEnabled: bool,
     *     lastSyncAt: string|null,
     *     createdAt: string,
     *     reviewsCount: int,
     *     googleConnected: bool,
     *     replyFormality: string,
     *     replyTone: string,
     *     replySignature: string|null,
     *     replyInstructions: string|null,
     *     clientEmail: string|null
     * }
     */
    private function serialize(Establishment $e): array
    {
        return [
            'id' => $e->getId()?->toRfc4122(),
            'name' => $e->getName() ?? '',
            'placeId' => $e->getPlaceId() ?? '',
            'address' => $e->getAddress() ?? '',
            'alertsEnabled' => $e->isAlertsEnabled(),
            'lastSyncAt' => $e->getLastSyncAt()?->format('c'),
            'createdAt' => $e->getCreatedAt()?->format('c') ?? '',
            'reviewsCount' => $e->getReviews()->count(),
            'googleConnected' => $e->isConnectedToGoogleBusiness(),
            'replyFormality' => $e->getReplyFormality(),
            'replyTone' => $e->getReplyTone(),
            'replySignature' => $e->getReplySignature(),
            'replyInstructions' => $e->getReplyInstructions(),
            'clientEmail' => $e->getClientEmail(),
        ];
    }
}
