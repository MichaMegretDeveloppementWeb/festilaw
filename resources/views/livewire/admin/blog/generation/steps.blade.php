{{-- Les etapes d'une generation, dans l'ordre du pipeline. $steps : liste de BlogGenerationStepView.
     $detailed : affiche aussi le modele, les jetons et le cout estime. Une etape en echec se reconnait a sa
     pastille et a son nombre d'essais ; sa cause technique ne s'affiche pas (journal du serveur). --}}
@php
    $stepStatus = \App\Enums\Blog\Generation\BlogGenerationStepStatus::class;
@endphp
<ol class="divide-y divide-subtle" data-generation-steps>
    @foreach ($steps as $step)
        <li class="flex items-start gap-3 px-5 py-3" wire:key="step-{{ $step->key }}" data-step="{{ $step->key }}" data-step-status="{{ $step->status->value }}">
            <span @class([
                'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full',
                'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' => $step->status === $stepStatus::Completed,
                'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' => $step->status === $stepStatus::Running,
                'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' => $step->status === $stepStatus::Failed,
                'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' => $step->status === $stepStatus::Pending,
            ])>
                @if ($step->status === $stepStatus::Completed)
                    <x-ui::icon name="check" class="h-3 w-3" />
                @elseif ($step->status === $stepStatus::Running)
                    <x-ui::spinner size="xs" />
                @elseif ($step->status === $stepStatus::Failed)
                    <x-ui::icon name="x-mark" class="h-3 w-3" />
                @else
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                @endif
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5">
                    <p @class(['text-[13px]', 'font-medium text-primary' => $step->status !== $stepStatus::Pending, 'text-muted' => $step->status === $stepStatus::Pending])>
                        {{ $step->label }}
                        @if ($step->attempt > 1)
                            <span class="font-normal text-muted">· {{ __('essai :attempt', ['attempt' => $step->attempt]) }}</span>
                        @endif
                    </p>
                    <p class="text-[12px] text-muted">
                        @if ($step->status === $stepStatus::Pending)
                            {{ $step->status->label() }}
                        @elseif ($step->hasRun() && $step->duration)
                            {{ $step->duration }} · {{ $step->cost }}
                        @else
                            {{ $step->status->label() }}
                        @endif
                    </p>
                </div>

                @if ($detailed && $step->hasRun())
                    <p class="mt-0.5 text-[12px] text-muted">
                        @if ($step->model)
                            {{ $step->model }} ·
                        @endif
                        {{ __(':input jetons lus, :output produits', ['input' => number_format($step->inputTokens, 0, ',', ' '), 'output' => number_format($step->outputTokens, 0, ',', ' ')]) }}
                        @if ($step->webSearches > 0)
                            · {{ trans_choice('{1} :count recherche web|[2,*] :count recherches web', $step->webSearches, ['count' => $step->webSearches]) }}
                        @endif
                        @if ($step->costIsEstimated)
                            · {{ __('coût estimé') }}
                        @endif
                    </p>
                @endif

            </div>
        </li>
    @endforeach
</ol>
