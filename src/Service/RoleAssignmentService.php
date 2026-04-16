<?php

namespace App\Service;

final class RoleAssignmentService
{
    /** @return list<string> */
    public function rolesForService(string $service): array
    {
        $code = strtoupper(trim($service));

        return match ($code) {
            'AD' => ['ROLE_ADMIN'],
            'AG' => ['ROLE_AGENT'],
            'RS' => ['ROLE_RESPONSABLE'],
            default => ['ROLE_USER'],
        };
    }
}
