<?php

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
];

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev') !== 'prod') {
    $bundles[Symfony\Bundle\MakerBundle\MakerBundle::class] = ['dev' => true];
    $bundles[Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class] = ['dev' => true, 'test' => true];
    $bundles[Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class] = ['dev' => true, 'test' => true];
    $bundles[Zenstruck\Foundry\ZenstruckFoundryBundle::class] = ['dev' => true, 'test' => true];
}

return $bundles;
