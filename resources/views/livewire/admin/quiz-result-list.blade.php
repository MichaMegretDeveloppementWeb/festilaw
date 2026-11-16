{{-- Reponses au quiz d'eligibilite, sur page blanche. En tete, le filtre par resultat, une seule ligne avec le
     nombre de reponses de chacun (selecteur segmente du kit), comme la liste des articles. Puis le tableau
     sans carte. --}}
@php
    $outcomeOptions = ['' => __('Tous (:count)', ['count' => array_sum(array_map('intval', $counts))])]
        + collect($outcomes)->mapWithKeys(fn ($case) => [$case->value => $case->label().' ('.($counts[$case->value] ?? 0).')'])->all();
@endphp
<div>
    <x-ui::page-header :title="__('Quiz')" :description="__('Réponses au test d\'éligibilité (30 secondes) du site public.')" class="mb-8" />

    @error('quiz-load-failed')
        <x-ui::load-failure :title="$message" :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
        {{-- Sur telephone, le selecteur defile en largeur plutot que de couper ses libelles. --}}
        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" data-outcome-filter>
            <x-ui::segmented :legend="__('Filtrer par résultat')" wire:model.live="outcome" :options="$outcomeOptions" class="w-max max-w-none" />
        </div>

        <div class="relative -mx-4 mt-4 border-t border-default sm:-mx-5">
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
