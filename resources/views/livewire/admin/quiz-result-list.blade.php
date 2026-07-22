<div>
    <x-ui.page-header title="{{ __('Quiz') }}" description="{{ __('Réponses au test d\'éligibilité (30 secondes) du site public.') }}" class="mb-6" />

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.stat-card class="border border-base" label="{{ __('Concerné') }}" value="{{ $counts['concerned'] }}" />
        <x-ui.stat-card class="border border-base" label="{{ __('Catégorie exclue') }}" value="{{ $counts['excluded'] }}" />
        <x-ui.stat-card class="border border-base" label="{{ __('Non concerné') }}" value="{{ $counts['not_concerned'] }}" />
    </div>

    <div class="mb-4 max-w-xs">
        <x-ui.form-group label="{{ __('Résultat') }}" for="f-outcome">
            <x-ui.select id="f-outcome" wire:model.live="outcome" :options="collect($outcomes)->mapWithKeys(fn ($o) => [$o->value => $o->label()])->prepend(__('Tous'), '')->all()" />
        </x-ui.form-group>
    </div>

    <div class="overflow-hidden rounded-xl border border-base bg-surface">
        @if ($results->isEmpty())
            <p class="px-6 py-16 text-center text-[13px] text-secondary">{{ __('Aucune réponse au quiz pour le moment.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-base bg-elevated text-left text-[11px] font-semibold uppercase tracking-wide text-muted">
                            <th class="px-4 py-3">{{ __('Résultat') }}</th>
                            <th class="px-4 py-3">{{ __('Hors UE') }}</th>
                            <th class="px-4 py-3">{{ __('Vend dans l\'UE') }}</th>
                            <th class="px-4 py-3">{{ __('Catégorie exclue') }}</th>
                            <th class="px-4 py-3">{{ __('Langue') }}</th>
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($results as $result)
                            @php
                                $color = match ($result->outcome) {
                                    \App\Enums\Quiz\QuizOutcome::Concerned => 'emerald',
                                    \App\Enums\Quiz\QuizOutcome::Excluded => 'amber',
                                    \App\Enums\Quiz\QuizOutcome::NotConcerned => 'gray',
                                };
                            @endphp
                            <tr wire:key="quiz-{{ $result->id }}">
                                <td class="px-4 py-3"><x-ui.badge :color="$color" dot>{{ $result->outcome->label() }}</x-ui.badge></td>
                                <td class="px-4 py-3 text-secondary">{{ $result->q1_based_outside_eu ? __('Oui') : __('Non') }}</td>
                                <td class="px-4 py-3 text-secondary">{{ $result->q2_sells_to_eu ? __('Oui') : __('Non') }}</td>
                                <td class="px-4 py-3 text-secondary">{{ $result->q3_sells_restricted ? __('Oui') : __('Non') }}</td>
                                <td class="px-4 py-3 text-muted">{{ strtoupper((string) $result->locale) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $result->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $results->links('pagination.admin') }}
</div>
