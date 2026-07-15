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
            self::TurnoverProof => __('Proof of turnover'),
            self::TechnicalDocumentation => __('Technical documentation'),
            self::Other => __('Other document'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::TurnoverProof => __('A recent statement or report showing your annual turnover.'),
            self::TechnicalDocumentation => __('Product technical file: specs, test reports, or declarations of conformity.'),
            self::Other => __('Any additional supporting document.'),
        };
    }
}
