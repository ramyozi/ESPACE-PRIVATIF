<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Etats possibles d'un document. On expose des helpers pour valider
 * les transitions autorisees (machine a etats simple).
 */
enum DocumentState: string
{
    case EN_ATTENTE_SIGNATURE = 'en_attente_signature';
    case SIGNATURE_EN_COURS = 'signature_en_cours';
    case SIGNE = 'signe';
    case SIGNE_VALIDE = 'signe_valide';
    case REFUSE = 'refuse';
    case EXPIRE = 'expire';

    /**
     * Liste des transitions autorisees a partir de l'etat courant.
     *
     * @return self[]
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::EN_ATTENTE_SIGNATURE => [self::SIGNATURE_EN_COURS, self::EXPIRE, self::REFUSE],
            self::SIGNATURE_EN_COURS => [self::SIGNE, self::EN_ATTENTE_SIGNATURE, self::REFUSE],
            self::SIGNE => [self::SIGNE_VALIDE],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::SIGNE_VALIDE, self::REFUSE, self::EXPIRE], true);
    }
}
