{{-- Reponses au quiz d'eligibilite, sur page blanche. En tete, la bande des nombres de reponses par resultat, qui
     sert aussi de filtre (un clic choisit le resultat, la cellule choisie est soulignee), comme la liste des
     articles. Puis le tableau sans carte. --}}
@php
    $outcomeCells = ['' => __('Tous')] + collect($outcomes)->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    $chosenOutcome = \App\Enums\Quiz\QuizOutcome::tryFrom($outcome)?->value ?? '';
    $totalCount = array_sum(array_map('intval', $counts));
@endphp
<div>
    <x-ui::page-header :title="__('Quiz')" :description="__('Réponses au test d\'éligibilité (30 secondes) du site public.')" class="mb-8" />

    @error('quiz-load-failed')
        <x-ui::load-failure :title="$message" :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
        <x-admin.stats tag="div" :columns="4" role="group" aria-label="{{ __('Filtrer par résultat') }}" data-outcome-filter>
            @foreach ($outcomeCells as $value => $label)
                {{-- Forme en bloc : la forme courte, entre parentheses, serait avalee par le bloc php du tableau plus bas. --}}
                @php
                    $selected = $chosenOutcome === $value;
                @endphp
                <button type="button" wire:click="$set('outcome', '{{ $value }}')" aria-pressed="{{ $selected ? 'true' : 'false' }}" data-outcome="{{ $value === '' ? 'all' : $value }}"
                    @class([
                        'group relative flex min-w-0 flex-col bg-surface px-4 py-5 text-left transition-colors sm:px-5',
                        'hover:bg-elevated' => ! $selected,
                    ])>
                    <span @class(['text-[12px] font-medium', 'text-primary' => $selected, 'text-muted group-hover:text-secondary' => ! $selected])>{{ $label }}</span>
                    <span class="mt-2 text-[26px] font-semibold leading-none tracking-tight tabular-nums text-primary">{{ $value === '' ? $totalCount : ($counts[$value] ?? 0) }}</span>
                    <span @class(['absolute inset-x-0 -bottom-px h-0.5', 'bg-gray-900 dark:bg-white' => $selected, 'bg-transparent' => ! $selected])></span>
                </button>
            @endforeach
        </x-admin.stats>

        <div class="relative -mx-4 mt-8 border-t border-default sm:-mx-5">
            @if ($results === null || $results->isEmpty())
                <x-ui::empty-state icon="question-mark-circle" :title="$outcome !== '' ? __('Aucune réponse ne correspond.') : __('Aucune réponse au quiz pour le moment.')" />
            @else
                <x-ui::table>
                    <x-ui::table.head>
                        <x-ui::table.header-cell :first="true">{{ __('Résultat') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Hors UE') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Vend dans l\'UE') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Catégorie exclue') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Langue') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell :last="true">{{ __('Date') }}</x-ui::table.header-cell>
                    </x-ui::table.head>
                    <x-ui::table.body>
                        @foreach ($results as $result)
                            @php
                                $color = match ($result->outcome) {
                                    \App\Enums\Quiz\QuizOutcome::Concerned => 'emerald',
                                    \App\Enums\Quiz\QuizOutcome::Excluded => 'amber',
                                    \App\Enums\Quiz\QuizOutcome::NotConcerned => 'gray',
                                };
                            @endphp
                            <x-ui::table.row wire:key="quiz-{{ $result->id }}">
                                <x-ui::table.cell :first="true"><x-ui::badge :color="$color" :dot="true">{{ $result->outcome->label() }}</x-ui::badge></x-ui::table.cell>
                                <x-ui::table.cell>{{ $result->q1_based_outside_eu ? __('Oui') : __('Non') }}</x-ui::table.cell>
                                <x-ui::table.cell>{{ $result->q2_sells_to_eu ? __('Oui') : __('Non') }}</x-ui::table.cell>
                                <x-ui::table.cell>{{ $result->q3_sells_restricted ? __('Oui') : __('Non') }}</x-ui::table.cell>
                                <x-ui::table.cell variant="muted">{{ strtoupper((string) $result->locale) }}</x-ui::table.cell>
                                <x-ui::table.cell :last="true" variant="muted">{{ $result->created_at->format('d/m/Y H:i') }}</x-ui::table.cell>
                            </x-ui::table.row>
                        @endforeach
                    </x-ui::table.body>
                </x-ui::table>

                <x-ui::pagination :paginator="$results" mode="livewire" />
            @endif
        </div>
    @enderror
</div>
