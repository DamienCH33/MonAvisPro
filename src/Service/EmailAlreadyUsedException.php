<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Levée lorsqu'on tente de créer un utilisateur avec un email déjà utilisé.
 */
class EmailAlreadyUsedException extends \DomainException {}
