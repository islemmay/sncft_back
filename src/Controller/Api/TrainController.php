<?php

namespace App\Controller\Api;

use App\Entity\Train;
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

#[Route('/api/trains')]
final class TrainController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly AppLogService $appLog,
    ) {
    }

    #[Route('', name: 'api_trains_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        $trains = $this->em->getRepository(Train::class)->findBy([], ['numero' => 'ASC']);
        $json = $this->serializer->serialize($trains, 'json', [AbstractNormalizer::GROUPS => ['train:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_trains_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getOne(int $id): JsonResponse
    {
        $train = $this->em->getRepository(Train::class)->find($id);
        if (!$train) {
            return $this->json(['error' => 'Train introuvable'], Response::HTTP_NOT_FOUND);
        }
        $json = $this->serializer->serialize($train, 'json', [AbstractNormalizer::GROUPS => ['train:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('', name: 'api_trains_create', methods: ['POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function create(Request $request): JsonResponse
    {
        $train = $this->deserializeTrain($request->getContent());
        if ($train instanceof JsonResponse) {
            return $train;
        }

        $errors = $this->validator->validate($train);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }

        $this->em->persist($train);
        $this->em->flush();
        $this->appLog->log('TRAIN_CREATE', $train->getNumero(), $this->actor());

        $json = $this->serializer->serialize($train, 'json', [AbstractNormalizer::GROUPS => ['train:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_trains_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AGENT')]
    public function update(int $id, Request $request): JsonResponse
    {
        $train = $this->em->getRepository(Train::class)->find($id);
        if (!$train) {
            return $this->json(['error' => 'Train introuvable'], Response::HTTP_NOT_FOUND);
        }

        $updated = $this->deserializeTrain($request->getContent(), $train);
        if ($updated instanceof JsonResponse) {
            return $updated;
        }

        $errors = $this->validator->validate($updated);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }

        $this->em->flush();
        $this->appLog->log('TRAIN_UPDATE', 'ID '.$id, $this->actor());

        $json = $this->serializer->serialize($updated, 'json', [AbstractNormalizer::GROUPS => ['train:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_trains_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AGENT')]
    public function delete(int $id): JsonResponse
    {
        $train = $this->em->getRepository(Train::class)->find($id);
        if (!$train) {
            return $this->json(['error' => 'Train introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($train);
        $this->em->flush();
        $this->appLog->log('TRAIN_DELETE', 'ID '.$id, $this->actor());

        return $this->json(['message' => 'Supprimé']);
    }

    private function deserializeTrain(string $content, ?Train $target = null): Train|JsonResponse
    {
        try {
            /** @var Train $train */
            $train = $this->serializer->deserialize(
                $content,
                Train::class,
                'json',
                [
                    AbstractNormalizer::GROUPS => ['train:write'],
                    'object_to_populate' => $target,
                ]
            );
        } catch (\Throwable) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        return $train;
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
