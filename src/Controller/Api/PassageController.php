<?php

namespace App\Controller\Api;

use App\Entity\Gare;
use App\Entity\Passage;
use App\Entity\Trajet;
use App\Enum\PassageClassification;
use App\Service\AppLogService;
use App\Service\PassageDelayService;
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

#[Route('/api/passages')]
final class PassageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly PassageDelayService $delayService,
        private readonly AppLogService $appLog,
    ) {
    }

    #[Route('', name: 'api_passages_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Passage::class)->createQueryBuilder('p')
            ->leftJoin('p.gare', 'g')->addSelect('g')
            ->leftJoin('p.trajet', 't')->addSelect('t')
            ->leftJoin('t.train', 'tr')->addSelect('tr')
            ->orderBy('p.id', 'DESC');

        if ($tid = $request->query->get('trajet')) {
            $qb->andWhere('p.trajet = :tid')->setParameter('tid', (int) $tid);
        }
        if ($cls = $request->query->get('classification')) {
            try {
                $enum = PassageClassification::from((string) $cls);
                $qb->andWhere('p.classification = :c')->setParameter('c', $enum);
            } catch (\ValueError) {
            }
        }

        $items = $qb->getQuery()->getResult();
        $json = $this->serializer->serialize($items, 'json', [
            AbstractNormalizer::GROUPS => ['passage:read', 'passage:list', 'gare:read', 'trajet:summary', 'train:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_passages_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getOne(int $id): JsonResponse
    {
        $p = $this->em->getRepository(Passage::class)->find($id);
        if (!$p) {
            return $this->json(['error' => 'Passage introuvable'], Response::HTTP_NOT_FOUND);
        }
        $json = $this->serializer->serialize($p, 'json', [
            AbstractNormalizer::GROUPS => ['passage:read', 'passage:list', 'gare:read', 'trajet:summary', 'train:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('', name: 'api_passages_create', methods: ['POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function create(Request $request): JsonResponse
    {
        $passage = $this->hydratePassageFromJson($request->getContent(), null);
        if ($passage instanceof JsonResponse) {
            return $passage;
        }
        $this->delayService->recalculate($passage);
        $errors = $this->validator->validate($passage);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }
        if (!$passage->getTrajet() || !$passage->getGare()) {
            return $this->json(['error' => 'Trajet et gare requis'], 422);
        }
        $this->em->persist($passage);
        $this->em->flush();
        $this->appLog->log('PASSAGE_CREATE', 'ID '.$passage->getId(), $this->actor());
        $json = $this->serializer->serialize($passage, 'json', [
            AbstractNormalizer::GROUPS => ['passage:read', 'passage:list', 'gare:read', 'trajet:summary', 'train:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_passages_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AGENT')]
    public function update(int $id, Request $request): JsonResponse
    {
        $passage = $this->em->getRepository(Passage::class)->find($id);
        if (!$passage) {
            return $this->json(['error' => 'Passage introuvable'], Response::HTTP_NOT_FOUND);
        }
        $updated = $this->hydratePassageFromJson($request->getContent(), $passage);
        if ($updated instanceof JsonResponse) {
            return $updated;
        }
        $this->delayService->recalculate($updated);
        $errors = $this->validator->validate($updated);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }
        $this->em->flush();
        $this->appLog->log('PASSAGE_UPDATE', 'ID '.$id.' retard '.$updated->getRetardMinutes(), $this->actor());
        $json = $this->serializer->serialize($updated, 'json', [
            AbstractNormalizer::GROUPS => ['passage:read', 'passage:list', 'gare:read', 'trajet:summary', 'train:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_passages_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AGENT')]
    public function delete(int $id): JsonResponse
    {
        $passage = $this->em->getRepository(Passage::class)->find($id);
        if (!$passage) {
            return $this->json(['error' => 'Passage introuvable'], Response::HTTP_NOT_FOUND);
        }
        $this->em->remove($passage);
        $this->em->flush();
        $this->appLog->log('PASSAGE_DELETE', 'ID '.$id, $this->actor());

        return $this->json(['message' => 'Supprimé']);
    }

    /**
     * Charge Trajet et Gare depuis la BDD (évite entités « fantômes ») et parse les heures (HH:MM ou HH:MM:SS).
     */
    private function hydratePassageFromJson(string $content, ?Passage $target): Passage|JsonResponse
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

        $passage = $target ?? new Passage();

        if (\array_key_exists('heureTheorique', $data)) {
            $parsed = $this->parseTimeValue($data['heureTheorique']);
            if ($data['heureTheorique'] !== null && $data['heureTheorique'] !== '' && $parsed === null) {
                return $this->json(['error' => 'Heure théorique invalide (ex. 08:30 ou 08:30:00)'], Response::HTTP_BAD_REQUEST);
            }
            $passage->setHeureTheorique($parsed);
        }

        if (\array_key_exists('heureReelle', $data)) {
            $raw = $data['heureReelle'];
            if ($raw === null || $raw === '') {
                $passage->setHeureReelle(null);
            } else {
                $parsed = $this->parseTimeValue($raw);
                if ($parsed === null) {
                    return $this->json(['error' => 'Heure réelle invalide (ex. 08:30 ou 08:30:00)'], Response::HTTP_BAD_REQUEST);
                }
                $passage->setHeureReelle($parsed);
            }
        }

        if (\array_key_exists('classification', $data) && $data['classification'] !== null && $data['classification'] !== '') {
            try {
                $passage->setClassification(PassageClassification::from((string) $data['classification']));
            } catch (\ValueError) {
                return $this->json(['error' => 'Classification invalide'], Response::HTTP_BAD_REQUEST);
            }
        }

        $trajetId = 0;
        if (isset($data['trajetId'])) {
            $trajetId = (int) $data['trajetId'];
        } elseif (isset($data['trajet']) && \is_array($data['trajet']) && isset($data['trajet']['id'])) {
            $trajetId = (int) $data['trajet']['id'];
        }
        if ($trajetId > 0) {
            $trajet = $this->em->find(Trajet::class, $trajetId);
            if (!$trajet) {
                return $this->json(['error' => 'Trajet introuvable (id '.$trajetId.')'], Response::HTTP_BAD_REQUEST);
            }
            $passage->setTrajet($trajet);
        }

        $gareId = 0;
        if (isset($data['gareId'])) {
            $gareId = (int) $data['gareId'];
        } elseif (isset($data['gare']) && \is_array($data['gare']) && isset($data['gare']['id'])) {
            $gareId = (int) $data['gare']['id'];
        }
        if ($gareId > 0) {
            $gare = $this->em->find(Gare::class, $gareId);
            if (!$gare) {
                return $this->json(['error' => 'Gare introuvable (id '.$gareId.')'], Response::HTTP_BAD_REQUEST);
            }
            $passage->setGare($gare);
        }

        return $passage;
    }

    private function parseTimeValue(mixed $raw): ?\DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim((string) $raw);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) {
            $parts = explode(':', $s);
            $h = str_pad($parts[0], 2, '0', \STR_PAD_LEFT);
            $m = str_pad($parts[1], 2, '0', \STR_PAD_LEFT);
            $sec = isset($parts[2]) ? str_pad($parts[2], 2, '0', \STR_PAD_LEFT) : '00';

            return \DateTimeImmutable::createFromFormat('H:i:s', $h.':'.$m.':'.$sec, new \DateTimeZone('UTC')) ?: null;
        }

        try {
            $dt = new \DateTimeImmutable($s);

            return \DateTimeImmutable::createFromFormat(
                'H:i:s',
                $dt->format('H:i:s'),
                new \DateTimeZone('UTC')
            ) ?: null;
        } catch (\Throwable) {
            return null;
        }
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
