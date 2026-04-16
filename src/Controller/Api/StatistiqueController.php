<?php

namespace App\Controller\Api;

use App\Entity\Passage;
use App\Entity\Statistique;
use App\Enum\PassageClassification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/statistiques')]
#[IsGranted('ROLE_RESPONSABLE')]
final class StatistiqueController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_stats_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $stats = $this->em->getRepository(Statistique::class)->findBy([], ['periodeDebut' => 'DESC']);
        $json = $this->serializer->serialize($stats, 'json', [AbstractNormalizer::GROUPS => ['stat:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('', name: 'api_stats_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $stat = $this->deserialize($request->getContent());
        if ($stat instanceof JsonResponse) {
            return $stat;
        }
        $errors = $this->validator->validate($stat);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }
        $this->em->persist($stat);
        $this->em->flush();
        $json = $this->serializer->serialize($stat, 'json', [AbstractNormalizer::GROUPS => ['stat:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), Response::HTTP_CREATED);
    }

    #[Route('/rapport', name: 'api_stats_report', methods: ['POST'])]
    public function generateReport(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            $debut = new \DateTimeImmutable((string) ($data['periodeDebut'] ?? 'today'));
            $fin = new \DateTimeImmutable((string) ($data['periodeFin'] ?? 'today'));
        } catch (\Throwable) {
            return $this->json(['error' => 'Dates invalides'], 422);
        }

        $qb = $this->em->getRepository(Passage::class)->createQueryBuilder('p')
            ->join('p.trajet', 't')
            ->select('COUNT(p.id)')
            ->where('t.date BETWEEN :d1 AND :d2')
            ->andWhere('p.retardMinutes IS NOT NULL')
            ->andWhere('p.retardMinutes > 0')
            ->setParameter('d1', $debut)
            ->setParameter('d2', $fin);

        if (!empty($data['trajetId'])) {
            $qb->andWhere('t.id = :tid')->setParameter('tid', (int) $data['trajetId']);
        }
        if (!empty($data['classification'])) {
            try {
                $c = PassageClassification::from((string) $data['classification']);
                $qb->andWhere('p.classification = :cl')->setParameter('cl', $c);
            } catch (\ValueError) {
            }
        }

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        $stat = new Statistique();
        $stat->setPeriodeDebut($debut);
        $stat->setPeriodeFin($fin);
        $stat->setNbRetards($count);
        $this->em->persist($stat);
        $this->em->flush();

        $json = $this->serializer->serialize($stat, 'json', [AbstractNormalizer::GROUPS => ['stat:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), Response::HTTP_CREATED);
    }

    private function deserialize(string $content): Statistique|JsonResponse
    {
        try {
            /** @var Statistique $stat */
            $stat = $this->serializer->deserialize($content, Statistique::class, 'json', [
                AbstractNormalizer::GROUPS => ['stat:write'],
            ]);
        } catch (\Throwable) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        return $stat;
    }

    private function validationError(\Symfony\Component\Validator\ConstraintViolationListInterface $errors): JsonResponse
    {
        $list = [];
        foreach ($errors as $e) {
            $list[] = ['path' => $e->getPropertyPath(), 'message' => $e->getMessage()];
        }

        return $this->json(['error' => 'Validation', 'violations' => $list], 422);
    }
}
