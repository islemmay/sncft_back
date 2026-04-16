<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur) {
            return new JsonResponse(['error' => 'Invalid user'], 500);
        }

        $json = $this->serializer->serialize($user, 'json', [
            AbstractNormalizer::GROUPS => ['user:read'],
        ]);

        return new JsonResponse(json_decode($json, true, 512, JSON_THROW_ON_ERROR), 200);
    }
}
