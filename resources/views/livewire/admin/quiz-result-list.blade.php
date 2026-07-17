<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ __('Quiz') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Réponses au test d\'éligibilité (30 secondes) du site public.') }}</p>
    </div>

    {{-- Recapitulatif --}}
    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-2xl font-semibold text-emerald-600">{{ $counts['concerned'] }}</div>
            <div class="text-sm text-slate-500">{{ __('Concerné') }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-2xl font-semibold text-amber-600">{{ $counts['excluded'] }}</div>
            <div class="text-sm text-slate-500">{{ __('Catégorie exclue') }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-2xl font-semibold text-slate-600">{{ $counts['not_concerned'] }}</div>
            <div class="text-sm text-slate-500">{{ __('Non concerné') }}</div>
        </div>
    </div>

    {{-- Filtre --}}
    <div class="mb-4">
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="f-outcome">{{ __('Résultat') }}</label>
        <select id="f-outcome" wire:model.live="outcome"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
            <option value="">{{ __('Tous') }}</option>
            @foreach ($outcomes as $o)
                <option value="{{ $o->value }}">{{ $o->label() }}</option>
            @endforeach
        </select>
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($results->isEmpty())
            <p class="px-6 py-16 text-center text-sm text-slate-500">{{ __('Aucune réponse au quiz pour le moment.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">{{ __('Résultat') }}</th>
                            <th class="px-4 py-3">{{ __('Hors UE') }}</th>
                            <th class="px-4 py-3">{{ __('Vend dans l\'UE') }}</th>
                            <th class="px-4 py-3">{{ __('Catégorie exclue') }}</th>
                            <th class="px-4 py-3">{{ __('Langue') }}</th>
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($results as $result)
                            @php
                                $tone = match ($result->outcome) {
                                    \App\Enums\Quiz\QuizOutcome::Concerned => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                    \App\Enums\Quiz\QuizOutcome::Excluded => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                    \App\Enums\Quiz\QuizOutcome::NotConcerned => 'bg-slate-100 text-slate-600 ring-slate-500/20',
                                };
                            @endphp
                            <tr wire:key="quiz-{{ $result->id }}">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $tone }}">{{ $result->outcome->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $result->q1_based_outside_eu ? __('Oui') : __('Non') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $result->q2_sells_to_eu ? __('Oui') : __('Non') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $result->q3_sells_restricted ? __('Oui') : __('Non') }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ strtoupper((string) $result->locale) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $result->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $results->links('pagination.admin') }}
</div>
