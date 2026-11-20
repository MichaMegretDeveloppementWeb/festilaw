<?php

declare(strict_types=1);

namespace App\Mail;

use App\Data\System\ErrorLogGroup;
use App\Data\System\ErrorLogSummary;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Rapport d'erreurs du matin (francophone), pour le prestataire technique : les erreurs du journal du
 * serveur depuis le dernier rapport, regroupees, les plus frequentes d'abord. Les heures sont donnees a
 * l'heure de Paris.
 *
 * @phpstan-type ErrorRow array{title: string, meta: string, exception: ?string, location: ?string, routes: ?string, file: string}
 */
final class DailyErrorReport extends Mailable
{
    public const MAX_GROUPS = 30;

    private const TIMEZONE = 'Europe/Paris';

    private const LEVELS = [
        'ERROR' => 'Erreur',
        'CRITICAL' => 'Critique',
        'ALERT' => 'Alerte',
        'EMERGENCY' => 'Urgence',
    ];

    public function __construct(public ErrorLogSummary $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Erreurs relevées sur festilaw.com depuis le dernier rapport ('.$this->summary->occurrences().')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-error-report', with: [
            'occurrences' => $this->summary->occurrences(),
            'rows' => array_map($this->row(...), array_slice($this->summary->groups, 0, self::MAX_GROUPS)),
            'hiddenGroups' => max(0, count($this->summary->groups) - self::MAX_GROUPS),
            'unreadableFiles' => $this->summary->unreadableFiles,
            'since' => $this->moment($this->summary->since),
            'until' => $this->moment($this->summary->until),
        ]);
    }

    /** @return ErrorRow */
    private function row(ErrorLogGroup $group): array
    {
        $exception = $group->exceptionClass;

        if ($exception !== null && $group->exceptionMessage !== null && $group->exceptionMessage !== $group->message) {
            $exception .= ' ('.$group->exceptionMessage.')';
        }

        return [
            'title' => $group->message !== '' ? $group->message : ($group->exceptionMessage ?? 'Erreur sans message'),
            'meta' => (self::LEVELS[$group->level] ?? $group->level).', '.$group->count.' fois, '.$this->period($group),
            'exception' => $exception,
            'location' => $group->location,
            'routes' => $group->routes === [] ? null : implode(', ', $group->routes),
            'file' => $group->lastFile,
        ];
    }

    /** « le 21/11 a 07:12 », « de 07:12 a 09:40 le 21/11 », ou « du 20/11 a 23:10 au 21/11 a 02:05 ». */
    private function period(ErrorLogGroup $group): string
    {
        $first = $group->firstAt->setTimezone(self::TIMEZONE);
        $last = $group->lastAt->setTimezone(self::TIMEZONE);

        if ($first->equalTo($last)) {
            return 'le '.$this->moment($first);
        }

        if ($first->isSameDay($last)) {
            return 'de '.$first->format('H:i').' à '.$last->format('H:i').' le '.$first->format('d/m');
        }

        return 'du '.$this->moment($first).' au '.$this->moment($last);
    }

    private function moment(CarbonImmutable $at): string
    {
        $local = $at->setTimezone(self::TIMEZONE);

        return $local->format('d/m').' à '.$local->format('H:i');
    }
}
