<?php

namespace App\Controller\Api;

use App\Entity\Establishment;
use App\Entity\User;
use App\Service\GoogleBusinessProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/google')]
class GoogleBusinessController extends AbstractController
{
    public function __construct(
        private readonly GoogleBusinessProfileService $googleService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/connect', name: 'google_business_connect')]
    public function connect(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('google_oauth_state', $state);

        $authUrl = $this->googleService->getAuthorizationUrl($state);

        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'google_business_callback')]
    #[Route('/callback/', name: 'google_business_callback_slash')]
    public function callback(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $state = $request->query->get('state');
        $sessionState = $request->getSession()->get('google_oauth_state');

        if ($state !== $sessionState) {
            $this->addFlash('error', 'Erreur de sécurité : état invalide');

            return $this->redirectToRoute('dashboard');
        }

        if ($request->query->has('error')) {
            $this->addFlash('error', 'Connexion Google annulée');

            return $this->redirectToRoute('dashboard');
        }

        try {
            $code = $request->query->get('code');
            $tokenData = $this->googleService->exchangeCodeForToken($code);

            $accessToken = $tokenData['access_token'];
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? 3600;

            $accounts = $this->googleService->getAccounts($accessToken);

            if (empty($accounts)) {
                $this->addFlash('warning', 'Aucun compte Google Business trouvé pour ce compte Google');

                return $this->redirectToRoute('dashboard');
            }

            $request->getSession()->set('google_accounts', $accounts);
            $request->getSession()->set('google_access_token', $accessToken);
            $request->getSession()->set('google_refresh_token', $refreshToken);
            $request->getSession()->set('google_token_expires_at', time() + $expiresIn);

            return $this->redirectToRoute('google_business_select_location');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion : '.$e->getMessage());

            return $this->redirectToRoute('dashboard');
        }
    }

    #[Route('/select-location', name: 'google_business_select_location')]
    public function selectLocation(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $accounts = $request->getSession()->get('google_accounts', []);
        $accessToken = $request->getSession()->get('google_access_token');

        if (empty($accounts)) {
            $this->addFlash('warning', 'Session expirée, veuillez vous reconnecter');

            return $this->redirectToRoute('google_business_connect');
        }

        try {
            $allLocations = [];
            foreach ($accounts as $account) {
                $locations = $this->googleService->getLocations($account['name'], $accessToken);
                foreach ($locations as $location) {
                    $location['accountId'] = $account['name'];
                    $allLocations[] = $location;
                }
            }

            if (empty($allLocations)) {
                $this->addFlash('warning', 'Aucun établissement trouvé sur vos comptes Google Business');

                return $this->redirectToRoute('dashboard');
            }

            return $this->render('google_business/select_location.html.twig', [
                'locations' => $allLocations,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la récupération des établissements : '.$e->getMessage());

            return $this->redirectToRoute('dashboard');
        }
    }

    #[Route('/save-location', name: 'google_business_save_location', methods: ['POST'])]
    public function saveLocation(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $locationDataRaw = $request->request->get('location_data');

        if (!is_string($locationDataRaw)) {
            throw new \InvalidArgumentException('location_data invalide');
        }

        $locationData = json_decode($locationDataRaw, true);

        if (!is_array($locationData)) {
            throw new \InvalidArgumentException('JSON invalide');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User invalide');
        }

        $establishment = new Establishment();
        $establishment->setOwner($user);

        $establishment->setName($locationData['title'] ?? 'Sans nom');

        preg_match('/locations\/([^\/]+)$/', $locationData['name'], $matches);
        $placeId = $matches[1] ?? basename($locationData['name']);

        $establishment->setPlaceId($placeId);
        $establishment->setGooglePlaceId($placeId);

        $address = $this->formatAddress($locationData['storefrontAddress'] ?? []);
        $establishment->setAddress($address ?: 'Adresse non renseignée');

        $this->em->persist($establishment);
        $this->em->flush();

        return $this->redirectToRoute('dashboard');
    }

    /**
     * @param array{
     *     addressLines?: string[],
     *     postalCode?: string,
     *     locality?: string
     * } $address
     */
    private function formatAddress(array $address): string
    {
        $parts = [];

        if (isset($address['addressLines'])) {
            $parts[] = implode(', ', $address['addressLines']);
        }

        if (isset($address['postalCode'], $address['locality'])) {
            $parts[] = $address['postalCode'].' '.$address['locality'];
        } elseif (isset($address['locality'])) {
            $parts[] = $address['locality'];
        }

        return implode(', ', $parts);
    }
}
