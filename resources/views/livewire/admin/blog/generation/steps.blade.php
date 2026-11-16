{{-- Les etapes d'une generation, dans l'ordre du pipeline. $steps : liste de BlogGenerationStepView.
     Une etape qui a tourne dit, chacun avec son icone, le modele utilise, les tokens envoyes et recus, les
     recherches web et son cout, puis son compte rendu en une ou deux phrases. Une etape en echec se reconnait
     a sa pastille et a son nombre d'essais ; sa cause technique ne s'affiche pas (journal du serveur). --}}
@php
    $stepStatus = \App\Enums\Blog\Generation\BlogGenerationStepStatus::class;
@endphp
<ol class="divide-y divide-subtle" data-generation-steps>
    @foreach ($steps as $step)
        <li class="flex items-start gap-3 px-5 py-3.5" wire:key="step-{{ $step->key }}" data-step="{{ $step->key }}" data-step-status="{{ $step->status->value }}">
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
                    <p class="text-[12px] tabular-nums text-muted">
                        @if ($step->status !== $stepStatus::Pending && $step->hasRun() && $step->duration)
                            {{ $step->duration }} · {{ $step->cost }}
                        @else
                            {{ $step->status->label() }}
                        @endif
                    </p>
                </div>

                @if ($step->hasRun() && ($step->modelName || $step->inputTokens + $step->outputTokens > 0 || $step->webSearches > 0))
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-secondary" data-step-usage>
                        @if ($step->modelName)
                            <span class="inline-flex items-center gap-1.5" title="{{ __('Modèle utilisé') }}">
                                <x-admin.icons.robot class="h-3.5 w-3.5 text-muted" />{{ $step->modelName }}
                            </span>
                        @endif
                        @if ($step->inputTokens + $step->outputTokens > 0)
                            <span class="inline-flex items-center gap-1.5">
                                <x-ui::icon name="circle-stack" class="h-3.5 w-3.5 text-muted" />
                                <x-admin.blog.tokens :input="$step->inputTokens" :output="$step->outputTokens" />
                            </span>
                        @endif
                        @if ($step->webSearches > 0)
                            <span class="inline-flex items-center gap-1.5">
                                <x-ui::icon name="magnifying-glass" class="h-3.5 w-3.5 text-muted" />
                                {{ trans_choice('{1} :count recherche web|[2,*] :count recherches web', $step->webSearches, ['count' => $step->webSearches]) }}
                            </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5" title="{{ __('Coût de cette étape') }}">
                            <x-ui::icon name="currency-dollar" class="h-3.5 w-3.5 text-muted" />{{ $step->cost }}
                            @if ($step->costIsEstimated)
                                <span class="text-muted">({{ __('estimé') }})</span>
                            @endif
                        </span>
                    </div>
                @endif

                @if ($step->summary)
                    <p class="mt-1.5 max-w-3xl text-[13px] leading-relaxed text-secondary" data-step-summary>{{ $step->summary }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
