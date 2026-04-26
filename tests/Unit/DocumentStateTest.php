<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DocumentState;
use PHPUnit\Framework\TestCase;

/**
 * Tests des transitions autorisees de la machine a etats document.
 */
final class DocumentStateTest extends TestCase
{
    public function testTransitionsAutoriseesDepuisEnAttente(): void
    {
        $state = DocumentState::EN_ATTENTE_SIGNATURE;
        self::assertTrue($state->canTransitionTo(DocumentState::SIGNATURE_EN_COURS));
        self::assertTrue($state->canTransitionTo(DocumentState::EXPIRE));
        self::assertFalse($state->canTransitionTo(DocumentState::SIGNE));
        self::assertFalse($state->canTransitionTo(DocumentState::SIGNE_VALIDE));
    }

    public function testTransitionSignatureEnCoursVersSigne(): void
    {
        self::assertTrue(
            DocumentState::SIGNATURE_EN_COURS->canTransitionTo(DocumentState::SIGNE)
        );
    }

    public function testEtatsTerminaux(): void
    {
        self::assertTrue(DocumentState::SIGNE_VALIDE->isTerminal());
        self::assertTrue(DocumentState::REFUSE->isTerminal());
        self::assertTrue(DocumentState::EXPIRE->isTerminal());
        self::assertFalse(DocumentState::EN_ATTENTE_SIGNATURE->isTerminal());
        self::assertFalse(DocumentState::SIGNE->isTerminal());
    }
}
