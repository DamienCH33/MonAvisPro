<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * public_file_exists('images/captures/a-traiter.jpg') : affiche une section seulement
 * si ses images ont bien été générées (captures de l'accueil).
 */
final class PublicFileExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('public_file_exists', $this->exists(...))];
    }

    public function exists(string $path): bool
    {
        if (str_contains($path, '..')) {
            return false;
        }

        return is_file($this->publicDir.'/'.ltrim($path, '/'));
    }
}
