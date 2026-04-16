<?php

namespace App\Service;

use App\Entity\LogEntry;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

final class AppLogService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function log(string $action, ?string $details = null, ?Utilisateur $user = null): void
    {
        $entry = new LogEntry();
        $entry->setAction($action);
        $entry->setDateHeure(new \DateTimeImmutable());
        $entry->setDetails($details);
        $entry->setUtilisateur($user);
        $this->em->persist($entry);
        $this->em->flush();
    }
}
