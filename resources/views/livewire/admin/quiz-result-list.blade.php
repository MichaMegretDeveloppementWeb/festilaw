<div>
    <x-ui::page-header :title="__('Quiz')" :description="__('Réponses au test d\'éligibilité (30 secondes) du site public.')" class="mb-6" />

    @error('quiz-load-failed')
        <x-ui::card :padding="false">
            <x-ui::load-failure :title="$message" :description="__('Réessayez ; si le problème persiste, contactez-nous.')" retry="$refresh" />
        </x-ui::card>
    @else
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-ui::stat-card :label="__('Concerné')" :value="$counts['concerned'] ?? 0" />
            <x-ui::stat-card :label="__('Catégorie exclue')" :value="$counts['excluded'] ?? 0" />
            <x-ui::stat-card :label="__('Non concerné')" :value="$counts['not_concerned'] ?? 0" />
        </div>

        <div class="mb-4 max-w-xs">
            <x-ui::form-group :label="__('Résultat')" for="f-outcome">
                <x-ui::select id="f-outcome" wire:model.live="outcome" :options="collect($outcomes)->mapWithKeys(fn ($o) => [$o->value => $o->label()])->prepend(__('Tous'), '')->all()" />
            </x-ui::form-group>
        </div>

        <x-ui::card :padding="false">
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
        </x-ui::card>
    @enderror
</div>
