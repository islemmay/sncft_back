<?php

namespace App\Controller\Api;

use App\Entity\LogEntry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/logs')]
#[IsGranted('ROLE_ADMIN')]
final class LogController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('', name: 'api_logs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(LogEntry::class)->createQueryBuilder('l')
            ->orderBy('l.dateHeure', 'DESC')
            ->setMaxResults(500);

        if ($request->query->get('action')) {
            $qb->andWhere('l.action = :a')->setParameter('a', $request->query->get('action'));
        }

        $logs = $qb->getQuery()->getResult();
        $json = $this->serializer->serialize($logs, 'json', [AbstractNormalizer::GROUPS => ['log:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }
}
