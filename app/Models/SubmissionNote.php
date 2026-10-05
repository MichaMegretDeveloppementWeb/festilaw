<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SubmissionNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note interne de l'equipe sur un dossier (back-office). Une note met a jour le dossier (updated_at) : pour
 * une demande de contact, c'est la date du dernier echange qui fait courir le delai de suppression (RGPD).
 */
class SubmissionNote extends Model
{
    /** @use HasFactory<SubmissionNoteFactory> */
    use HasFactory;

    protected $fillable = ['submission_id', 'author_id', 'body'];

    /** @var list<string> */
    protected $touches = ['submission'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
