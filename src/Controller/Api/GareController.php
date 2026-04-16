<?php

namespace App\Controller\Api;

use App\Entity\Gare;
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

#[Route('/api/gares')]
final class GareController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly AppLogService $appLog,
    ) {
    }

    #[Route('', name: 'api_gares_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Gare::class)->createQueryBuilder('g')->orderBy('g.nom', 'ASC');
        if ($ville = $request->query->get('ville')) {
            $qb->andWhere('g.ville LIKE :v')->setParameter('v', '%'.$ville.'%');
        }
        $gares = $qb->getQuery()->getResult();
        $json = $this->serializer->serialize($gares, 'json', [AbstractNormalizer::GROUPS => ['gare:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_gares_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getOne(int $id): JsonResponse
    {
        $gare = $this->em->getRepository(Gare::class)->find($id);
        if (!$gare) {
            return $this->json(['error' => 'Gare introuvable'], Response::HTTP_NOT_FOUND);
        }
        $json = $this->serializer->serialize($gare, 'json', [AbstractNormalizer::GROUPS => ['gare:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('', name: 'api_gares_create', methods: ['POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function create(Request $request): JsonResponse
    {
        $gare = $this->deserialize($request->getContent());
        if ($gare instanceof JsonResponse) {
            return $gare;
        }
        $errors = $this->validator->validate($gare);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }
        $this->em->persist($gare);
        $this->em->flush();
        $this->appLog->log('GARE_CREATE', $gare->getNom(), $this->actor());
        $json = $this->serializer->serialize($gare, 'json', [AbstractNormalizer::GROUPS => ['gare:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_gares_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AGENT')]
    public function update(int $id, Request $request): JsonResponse
    {
        $gare = $this->em->getRepository(Gare::class)->find($id);
        if (!$gare) {
            return $this->json(['error' => 'Gare introuvable'], Response::HTTP_NOT_FOUND);
        }
        $updated = $this->deserialize($request->getContent(), $gare);
        if ($updated instanceof JsonResponse) {
            return $updated;
        }
        $errors = $this->validator->validate($updated);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }
        $this->em->flush();
        $this->appLog->log('GARE_UPDATE', 'ID '.$id, $this->actor());
        $json = $this->serializer->serialize($updated, 'json', [AbstractNormalizer::GROUPS => ['gare:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_gares_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AGENT')]
    public function delete(int $id): JsonResponse
    {
        $gare = $this->em->getRepository(Gare::class)->find($id);
        if (!$gare) {
            return $this->json(['error' => 'Gare introuvable'], Response::HTTP_NOT_FOUND);
        }
        $this->em->remove($gare);
        $this->em->flush();
        $this->appLog->log('GARE_DELETE', 'ID '.$id, $this->actor());

        return $this->json(['message' => 'Supprimé']);
    }

    private function deserialize(string $content, ?Gare $target = null): Gare|JsonResponse
    {
        try {
            /** @var Gare $gare */
            $gare = $this->serializer->deserialize($content, Gare::class, 'json', [
                AbstractNormalizer::GROUPS => ['gare:write'],
                'object_to_populate' => $target,
            ]);
        } catch (\Throwable) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        return $gare;
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
