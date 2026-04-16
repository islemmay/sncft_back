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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class AuthController extends AbstractController
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

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): void
    {
        throw new \LogicException('Géré par Symfony Security (json_login).');
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $user = new Utilisateur();
        $user->setNom((string) ($data['nom'] ?? ''));
        $user->setEmail((string) ($data['email'] ?? ''));
        $user->setService((string) ($data['service'] ?? ''));
        $user->setMatricule((string) ($data['matricule'] ?? ''));
        $plain = (string) ($data['password'] ?? '');
        $user->setPassword($plain);
        $user->setRoles($this->roleAssignment->rolesForService($user->getService()));

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            $list = [];
            foreach ($errors as $e) {
                $list[] = ['path' => $e->getPropertyPath(), 'message' => $e->getMessage()];
            }

            return $this->json(['error' => 'Validation', 'violations' => $list], 422);
        }

        $existing = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existing) {
            return $this->json(['error' => 'Cet email est déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        $this->em->persist($user);
        $this->em->flush();

        $this->appLog->log('REGISTER', 'Nouvel utilisateur: '.$user->getEmail());

        $json = $this->serializer->serialize($user, 'json', [AbstractNormalizer::GROUPS => ['user:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $json = $this->serializer->serialize($user, 'json', [AbstractNormalizer::GROUPS => ['user:read']]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }
}
