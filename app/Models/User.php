<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/**
 * Representation d'un locataire en memoire.
 * On garde l'objet "anemique" : aucune logique BDD ici.
 */
final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly ?int $residenceId,
        public readonly string $externalId,
        public readonly string $email,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $passwordHash,
        public readonly int $failedLogins,
        public readonly ?DateTimeImmutable $lockedUntil,
        public readonly string $role = self::ROLE_USER,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            tenantId: (int) $row['tenant_id'],
            residenceId: $row['residence_id'] !== null ? (int) $row['residence_id'] : null,
            externalId: (string) $row['external_id'],
            email: (string) $row['email'],
            firstName: $row['first_name'] ?? null,
            lastName: $row['last_name'] ?? null,
            passwordHash: $row['password_hash'] ?? null,
            failedLogins: (int) ($row['failed_logins'] ?? 0),
            lockedUntil: !empty($row['locked_until']) ? new DateTimeImmutable($row['locked_until']) : null,
            role: (string) ($row['role'] ?? self::ROLE_USER),
        );
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > new DateTimeImmutable('now');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function fullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }
}
