<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message interne (francophone) : la generation automatique retient son lot, car les brouillons generes qui
 * attendent une relecture ont atteint leur plafond. Envoye une fois par pause (controle du 20/11), pour que la
 * pause ne passe pas pour une panne et que la relecture la leve.
 */
final class BlogGenerationPaused extends Mailable
{
    public function __construct(
        public int $pendingDrafts,
        public string $draftsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('La génération automatique d\'articles attend vos relectures'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-generation-paused');
    }
}
