<?php

declare(strict_types=1);

namespace App\Enums\Document;

enum DocumentType: string
{
    case TurnoverProof = 'turnover_proof';
    case TechnicalDocumentation = 'technical_documentation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TurnoverProof => 'Proof of turnover',
            self::TechnicalDocumentation => 'Technical documentation',
            self::Other => 'Other document',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::TurnoverProof => 'A recent statement or report showing your annual turnover.',
            self::TechnicalDocumentation => 'Product technical file: specs, test reports, or declarations of conformity.',
            self::Other => 'Any additional supporting document.',
        };
    }
}
