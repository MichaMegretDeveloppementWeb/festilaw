<?php

declare(strict_types=1);

namespace App\Enums\Document;

enum DocumentType: string
{
    case TurnoverProof = 'turnover_proof';
    case TechnicalDocumentation = 'technical_documentation';
    case Other = 'other';
}
