<?php

namespace App\Controller\Api;

use App\Entity\Passage;
use App\Enum\PassageClassification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/statistics')]
#[IsGranted('ROLE_RESPONSABLE')]
final class StatisticsChartController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/chart-data', name: 'api_statistics_charts', methods: ['GET'])]
    public function chartData(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Passage::class)->createQueryBuilder('p')
            ->join('p.trajet', 't')
            ->join('t.train', 'tr')
            ->addSelect('t', 'tr');

        if ($from = $request->query->get('dateFrom')) {
            try {
                $qb->andWhere('t.date >= :df')->setParameter('df', new \DateTimeImmutable($from));
            } catch (\Throwable) {
            }
        }
        if ($to = $request->query->get('dateTo')) {
            try {
                $qb->andWhere('t.date <= :dt')->setParameter('dt', new \DateTimeImmutable($to));
            } catch (\Throwable) {
            }
        }
        if ($tid = $request->query->get('trajetId')) {
            $qb->andWhere('t.id = :tid')->setParameter('tid', (int) $tid);
        }
        if ($cls = $request->query->get('classification')) {
            try {
                $enum = PassageClassification::from((string) $cls);
                $qb->andWhere('p.classification = :c')->setParameter('c', $enum);
            } catch (\ValueError) {
            }
        }

        $passages = $qb->getQuery()->getResult();

        $delaysPerTrain = [];
        $delaysOverTime = [];
        $classificationCount = [
            'ON_TIME' => 0,
            'LESS_THAN_15' => 0,
            'MORE_THAN_15' => 0,
            'CANCELLED' => 0,
        ];

        foreach ($passages as $p) {
            if (!$p instanceof Passage) {
                continue;
            }
            $train = $p->getTrajet()?->getTrain();
            $num = $train?->getNumero() ?? '?';
            $retard = $p->getRetardMinutes() ?? 0;
            if (!isset($delaysPerTrain[$num])) {
                $delaysPerTrain[$num] = 0;
            }
            if ($retard > 0) {
                $delaysPerTrain[$num] += $retard;
            }

            $d = $p->getTrajet()?->getDate();
            if ($d) {
                $key = $d->format('Y-m-d');
                if (!isset($delaysOverTime[$key])) {
                    $delaysOverTime[$key] = 0;
                }
                if ($retard > 0) {
                    $delaysOverTime[$key] += $retard;
                }
            }

            $classificationCount[$p->getClassification()->value]++;
        }

        ksort($delaysOverTime);

        return $this->json([
            'delaysPerTrain' => [
                'labels' => array_keys($delaysPerTrain),
                'data' => array_values($delaysPerTrain),
            ],
            'delaysOverTime' => [
                'labels' => array_keys($delaysOverTime),
                'data' => array_values($delaysOverTime),
            ],
            'classification' => [
                'labels' => array_keys($classificationCount),
                'data' => array_values($classificationCount),
            ],
        ]);
    }
}
