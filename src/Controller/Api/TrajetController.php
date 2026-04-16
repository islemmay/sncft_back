<?php

namespace App\Controller\Api;

use App\Entity\Train;
use App\Entity\Trajet;
use App\Service\AppLogService;
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

#[Route('/api/trajets')]
final class TrajetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly AppLogService $appLog,
    ) {
    }

    #[Route('', name: 'api_trajets_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Trajet::class)->createQueryBuilder('t')
            ->leftJoin('t.train', 'tr')->addSelect('tr')
            ->orderBy('t.date', 'DESC')->addOrderBy('t.id', 'DESC');

        if ($d = $request->query->get('villeDepart')) {
            $qb->andWhere('t.villeDepart LIKE :vd')->setParameter('vd', '%'.$d.'%');
        }
        if ($a = $request->query->get('villeArrivee')) {
            $qb->andWhere('t.villeArrivee LIKE :va')->setParameter('va', '%'.$a.'%');
        }
        if ($v = $request->query->get('ville')) {
            $qb->andWhere('t.villeDepart LIKE :v OR t.villeArrivee LIKE :v')->setParameter('v', '%'.$v.'%');
        }
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

        $items = $qb->getQuery()->getResult();
        $json = $this->serializer->serialize($items, 'json', [
            AbstractNormalizer::GROUPS => ['trajet:read', 'train:read', 'passage:read', 'gare:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_trajets_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getOne(int $id): JsonResponse
    {
        $trajet = $this->em->getRepository(Trajet::class)->find($id);
        if (!$trajet) {
            return $this->json(['error' => 'Trajet introuvable'], Response::HTTP_NOT_FOUND);
        }
        $json = $this->serializer->serialize($trajet, 'json', [
            AbstractNormalizer::GROUPS => ['trajet:read', 'train:read', 'passage:read', 'gare:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('', name: 'api_trajets_create', methods: ['POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function create(Request $request): JsonResponse
    {
        $trajet = $this->hydrateTrajetFromJson($request->getContent(), null);
        if ($trajet instanceof JsonResponse) {
            return $trajet;
        }
        $errors = $this->validator->validate($trajet);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }
        if (!$trajet->getTrain()) {
            return $this->json(['error' => 'Train requis (id invalide ou manquant)'], 422);
        }
        $this->em->persist($trajet);
        $this->em->flush();
        $this->appLog->log('TRAJET_CREATE', 'ID '.$trajet->getId(), $this->actor());
        $json = $this->serializer->serialize($trajet, 'json', [
            AbstractNormalizer::GROUPS => ['trajet:read', 'train:read', 'passage:read', 'gare:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_trajets_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AGENT')]
    public function update(int $id, Request $request): JsonResponse
    {
        $trajet = $this->em->getRepository(Trajet::class)->find($id);
        if (!$trajet) {
            return $this->json(['error' => 'Trajet introuvable'], Response::HTTP_NOT_FOUND);
        }
        $updated = $this->hydrateTrajetFromJson($request->getContent(), $trajet);
        if ($updated instanceof JsonResponse) {
            return $updated;
        }
        $errors = $this->validator->validate($updated);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }
        $this->em->flush();
        $this->appLog->log('TRAJET_UPDATE', 'ID '.$id, $this->actor());
        $json = $this->serializer->serialize($updated, 'json', [
            AbstractNormalizer::GROUPS => ['trajet:read', 'train:read', 'passage:read', 'gare:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_trajets_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AGENT')]
    public function delete(int $id): JsonResponse
    {
        $trajet = $this->em->getRepository(Trajet::class)->find($id);
        if (!$trajet) {
            return $this->json(['error' => 'Trajet introuvable'], Response::HTTP_NOT_FOUND);
        }
        $this->em->remove($trajet);
        $this->em->flush();
        $this->appLog->log('TRAJET_DELETE', 'ID '.$id, $this->actor());

        return $this->json(['message' => 'Supprimé']);
    }

    /**
     * Hydrate depuis JSON en chargeant le Train depuis la BDD (évite une entité Train « fantôme » non gérée par Doctrine).
     *
     * @param non-empty-string $content
     */
    private function hydrateTrajetFromJson(string $content, ?Trajet $target): Trajet|JsonResponse
    {
        try {
            /** @var mixed $data */
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (!\is_array($data)) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $trajet = $target ?? new Trajet();

        if (\array_key_exists('date', $data) && $data['date'] !== null && $data['date'] !== '') {
            try {
                $trajet->setDate(new \DateTimeImmutable((string) $data['date']));
            } catch (\Throwable) {
                return $this->json(['error' => 'Date invalide (utilisez le format YYYY-MM-DD)'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (\array_key_exists('villeDepart', $data) && $data['villeDepart'] !== null) {
            $trajet->setVilleDepart(trim((string) $data['villeDepart']));
        }
        if (\array_key_exists('villeArrivee', $data) && $data['villeArrivee'] !== null) {
            $trajet->setVilleArrivee(trim((string) $data['villeArrivee']));
        }

        $trainId = null;
        if (isset($data['trainId'])) {
            $trainId = (int) $data['trainId'];
        } elseif (isset($data['train']) && \is_array($data['train']) && isset($data['train']['id'])) {
            $trainId = (int) $data['train']['id'];
        }

        if ($trainId > 0) {
            $train = $this->em->find(Train::class, $trainId);
            if (!$train) {
                return $this->json(['error' => 'Aucun train avec l’id '.$trainId], Response::HTTP_BAD_REQUEST);
            }
            $trajet->setTrain($train);
        }

        return $trajet;
    }

    private function validationError(\Symfony\Component\Validator\ConstraintViolationListInterface $errors): JsonResponse
    {
        $list = [];
        foreach ($errors as $e) {
            $list[] = ['path' => $e->getPropertyPath(), 'message' => $e->getMessage()];
        }

        return $this->json(['error' => 'Validation', 'violations' => $list], 422);
    }

    private function actor(): ?\App\Entity\Utilisateur
    {
        $u = $this->getUser();

        return $u instanceof \App\Entity\Utilisateur ? $u : null;
    }
}
