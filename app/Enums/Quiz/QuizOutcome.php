<?php

declare(strict_types=1);

namespace App\Enums\Quiz;

enum QuizOutcome: string
{
    case Concerned = 'concerned';
    case NotConcerned = 'not_concerned';
}
