<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use Database\Factories\PackChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Changement de pack d'un dossier deja paye (SC12) : montee Creator -> Pro (mandat Pro signe puis difference
 * payee au prorata) ou retour Pro -> Creator (demande validee par Festilaw, effet au renouvellement). Une ligne
 * par demande, conservee comme historique (cf. PackChangeStatus).
 */
class PackChange extends Model
{
    /** @use HasFactory<PackChangeFactory> */
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'from_pack',
        'to_pack',
        'status',
        'contract_id',
        'payment_id',
        'amount_cents',
        'effective_year',
        'eligibility_confirmed_at',
        'decided_by',
        'decided_at',
        'applied_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'from_pack' => SubmissionType::class,
            'to_pack' => SubmissionType::class,
            'status' => PackChangeStatus::class,
            'amount_cents' => 'integer',
            'effective_year' => 'integer',
            'eligibility_confirmed_at' => 'datetime',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** Le nouveau mandat (pack cible). */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** Le paiement de la montee (difference au prorata). */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Montee vers le Pro (sinon retour au Creator). */
    public function isUpgrade(): bool
    {
        return $this->to_pack === SubmissionType::Pro;
    }
}
