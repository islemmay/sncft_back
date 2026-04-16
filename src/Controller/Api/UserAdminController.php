<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Service\AppLogService;
use App\Service\RoleAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RoleAssignmentService $roleAssignment,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly AppLogService $appLog,
    ) {
    }

    #[Route('', name: 'api_users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $users = $this->em->getRepository(Utilisateur::class)->findBy([], ['nom' => 'ASC']);
        $json = $this->serializer->serialize($users, 'json', [AbstractNormalizer::GROUPS => ['user:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_users_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOne(int $id): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }
        $json = $this->serializer->serialize($user, 'json', [AbstractNormalizer::GROUPS => ['user:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('', name: 'api_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $user = new Utilisateur();
        $this->applyUserData($user, $data, true);
        if (isset($data['roles']) && \is_array($data['roles'])) {
            /** @var list<string> $roles */
            $roles = array_values(array_filter($data['roles'], 'is_string'));
            $user->setRoles($roles);
        }

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }

        if ($this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $user->getEmail()])) {
            return $this->json(['error' => 'Email déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $plain = (string) ($data['password'] ?? '');
        if ($plain === '') {
            return $this->json(['error' => 'Mot de passe requis'], 422);
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        $this->em->persist($user);
        $this->em->flush();
        $this->appLog->log('USER_CREATE', 'ID '.$user->getId(), $this->getUser() instanceof Utilisateur ? $this->getUser() : null);

        $json = $this->serializer->serialize($user, 'json', [AbstractNormalizer::GROUPS => ['user:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_users_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $this->applyUserData($user, $data, false);
        if (isset($data['roles']) && \is_array($data['roles'])) {
            /** @var list<string> $roles */
            $roles = array_values(array_filter($data['roles'], 'is_string'));
            $user->setRoles($roles);
        }

        if (!empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, (string) $data['password']));
        }

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            return $this->validationError($errors);
        }

        $this->em->flush();
        $this->appLog->log('USER_UPDATE', 'ID '.$id, $this->getUser() instanceof Utilisateur ? $this->getUser() : null);

        $json = $this->serializer->serialize($user, 'json', [AbstractNormalizer::GROUPS => ['user:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Route('/{id}', name: 'api_users_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }
        $current = $this->getUser();
        if ($current instanceof Utilisateur && $current->getId() === $user->getId()) {
            return $this->json(['error' => 'Impossible de supprimer votre propre compte'], Response::HTTP_BAD_REQUEST);
        }

        $this->em->remove($user);
        $this->em->flush();
        $this->appLog->log('USER_DELETE', 'ID '.$id, $current instanceof Utilisateur ? $current : null);

        return $this->json(['message' => 'Supprimé']);
    }

    /** @param array<string, mixed> $data */
    private function applyUserData(Utilisateur $user, array $data, bool $isNew): void
    {
        if (isset($data['nom'])) {
            $user->setNom((string) $data['nom']);
        }
        if (isset($data['email'])) {
            $user->setEmail((string) $data['email']);
        }
        if (isset($data['service'])) {
            $user->setService((string) $data['service']);
            if ($isNew && !isset($data['roles'])) {
                $user->setRoles($this->roleAssignment->rolesForService($user->getService()));
            }
        }
        if (isset($data['matricule'])) {
            $user->setMatricule((string) $data['matricule']);
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
}
