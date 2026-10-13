<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\EstablishmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression définitive d'un compte : accès Google retiré, puis tout effacé
 * en base (établissements, avis, analyses, demandes de mot de passe : ON DELETE CASCADE).
 */
class AccountDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EstablishmentRepository $establishmentRepository,
        private readonly GoogleAccessRevoker $revoker,
    ) {
    }

    public function delete(User $user): void
    {
        // Une révocation par compte Google suffit (elle couvre toutes ses fiches).
        $revokedAccounts = [];
        foreach ($this->establishmentRepository->findBy(['owner' => $user]) as $establishment) {
            $account = $establishment->getGoogleAccountId() ?? (string) $establishment->getId();
            if (isset($revokedAccounts[$account])) {
                continue;
            }
            if ($this->revoker->revokeFor($establishment, wholeAccount: true)) {
                $revokedAccounts[$account] = true;
            }
        }

        $this->em->getConnection()->executeStatement(
            'DELETE FROM "user" WHERE id = :id',
            ['id' => (string) $user->getId()],
        );
        $this->em->clear();
    }
}
