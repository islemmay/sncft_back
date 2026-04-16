<?php

namespace App\Command;

use App\Entity\Gare;
use App\Entity\Train;
use App\Entity\Trajet;
use App\Entity\Utilisateur;
use App\Service\RoleAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-demo', description: 'Crée un jeu de données de démonstration (utilisateurs + trains + trajets)')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RoleAssignmentService $roleAssignment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->ensureUser('Admin SNCFT', 'admin@sncft.local', 'admin123', 'AD', 'AD001');
        $this->ensureUser('Agent Rail', 'agent@sncft.local', 'agent123', 'AG', 'AG001');
        $this->ensureUser('Responsable Ops', 'resp@sncft.local', 'resp123', 'RS', 'RS001');
        $this->ensureUser('Voyageur', 'user@sncft.local', 'user123', 'XX', 'US001');

        $train = $this->ensureTrain('TGV-101', 'TGV', 400);
        $this->ensureTrain('TER-55', 'TER', 200);

        $this->ensureGare('Tunis Central', 36.8065, 10.1815, 'Tunis');
        $this->ensureGare('Sousse Bab Jdid', 35.8256, 10.6411, 'Sousse');
        $this->ensureGare('Sfax Ville', 34.7406, 10.7603, 'Sfax');

        $trajet = new Trajet();
        $trajet->setDate(new \DateTimeImmutable('today'));
        $trajet->setVilleDepart('Tunis');
        $trajet->setVilleArrivee('Sousse');
        $trajet->setTrain($train);
        $this->em->persist($trajet);
        $this->em->flush();

        $io->success('Démo prête. Comptes: admin@sncft.local / agent@sncft.local / resp@sncft.local / user@sncft.local — mot de passe: voir commande (admin123, etc.)');

        return Command::SUCCESS;
    }

    private function ensureUser(string $nom, string $email, string $plain, string $service, string $matricule): void
    {
        $repo = $this->em->getRepository(Utilisateur::class);
        if ($repo->findOneBy(['email' => $email])) {
            return;
        }
        $u = new Utilisateur();
        $u->setNom($nom);
        $u->setEmail($email);
        $u->setService($service);
        $u->setMatricule($matricule);
        $u->setRoles($this->roleAssignment->rolesForService($service));
        $u->setPassword($this->passwordHasher->hashPassword($u, $plain));
        $this->em->persist($u);
        $this->em->flush();
    }

    private function ensureTrain(string $numero, string $type, int $cap): Train
    {
        $repo = $this->em->getRepository(Train::class);
        $t = $repo->findOneBy(['numero' => $numero]);
        if ($t) {
            return $t;
        }
        $t = new Train();
        $t->setNumero($numero);
        $t->setType($type);
        $t->setCapacite($cap);
        $this->em->persist($t);
        $this->em->flush();

        return $t;
    }

    private function ensureGare(string $nom, float $lat, float $lon, string $ville): Gare
    {
        $repo = $this->em->getRepository(Gare::class);
        $g = $repo->findOneBy(['nom' => $nom]);
        if ($g) {
            return $g;
        }
        $g = new Gare();
        $g->setNom($nom);
        $g->setLatitude($lat);
        $g->setLongitude($lon);
        $g->setVille($ville);
        $this->em->persist($g);
        $this->em->flush();

        return $g;
    }
}
