<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Contract\ContractRole;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionType;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mandat de Personne Responsable d'un dossier. Un dossier peut en avoir plusieurs au fil des changements de
 * pack apres paiement (SC12) : un seul en vigueur (role "current", lu par Submission::contract()), celui d'un
 * changement en cours ("pending") et les anciens ("superseded"), conserves comme trace. Le pack du mandat
 * (`pack`) fixe son contenu ; a defaut (mandat anterieur), c'est celui du dossier.
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'role' => 'current',
    ];

    protected $fillable = [
        'submission_id',
        'pack',
        'role',
        'superseded_at',
        'filled_fields',
        'signature_status',
        'signature_provider',
        'signature_provider_reference',
        'signed_file_path',
        'signed_at',
        'countersigned_file_path',
        'countersigned_at',
    ];

    protected function casts(): array
    {
        return [
            'pack' => SubmissionType::class,
            'role' => ContractRole::class,
            'superseded_at' => 'datetime',
            'filled_fields' => 'array',
            'signature_status' => SignatureStatus::class,
            'signed_at' => 'datetime',
            'countersigned_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** Pack dont ce mandat porte le contenu et le tarif (celui du dossier pour un mandat anterieur a SC12). */
    public function packType(): SubmissionType
    {
        return $this->pack ?? $this->submission->type;
    }

    /** Display name of the signature provider (for user-facing messages), e.g. "SignWell". */
    public function signatureProviderLabel(): string
    {
        return match ((string) $this->signature_provider) {
            'signwell' => 'SignWell',
            default => ucfirst((string) $this->signature_provider),
        };
    }
}
