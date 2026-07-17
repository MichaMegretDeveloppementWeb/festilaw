<section id="quiz" class="quiz">
    <div class="quiz__inner">
        <span class="quiz__badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="2.5" x2="14" y2="2.5"/><line x1="12" y1="2.5" x2="12" y2="4.5"/><circle cx="12" cy="14" r="8"/><line x1="12" y1="14" x2="12" y2="9.5"/><line x1="17.5" y1="8.5" x2="19" y2="7"/></svg>
            <span class="quiz__badge-num">{{ __('30-second') }}</span>
            <span class="quiz__badge-label">{{ __('eligibility check') }}</span>
        </span>
        <h2 class="quiz__title">{{ __('Am I concerned by GPSR?') }}</h2>

        <div class="quiz__card" x-data="quiz">
            <div class="quiz__tracker">
                <div class="quiz__tracker-line"></div>
                <div class="quiz__stops">
                    <div class="quiz__stop is-current" :class="{ 'is-current': step === 0 && !done, 'is-done': answers.length > 0 }">
                        <div class="quiz__stop-circle">1</div><span class="quiz__stop-label">{{ __('Location') }}</span>
                    </div>
                    <div class="quiz__stop" :class="{ 'is-current': step === 1 && !done, 'is-done': answers.length > 1 }">
                        <div class="quiz__stop-circle">2</div><span class="quiz__stop-label">{{ __('Market') }}</span>
                    </div>
                    <div class="quiz__stop" :class="{ 'is-current': step === 2 && !done, 'is-done': answers.length > 2 }">
                        <div class="quiz__stop-circle">3</div><span class="quiz__stop-label">{{ __('Products') }}</span>
                    </div>
                </div>
            </div>

            {{-- Question courante --}}
            <div class="quiz__q" x-show="!done">
                <span class="quiz__q-count">{{ __('QUESTION') }} <span x-text="step + 1">1</span> {{ __('OF') }} <span x-text="questions.length">3</span></span>
                <h3 class="quiz__q-text" x-text="questions[step]">{{ __('Is your company based outside the European Union?') }}</h3>
                <div class="quiz__answers">
                    <button type="button" class="quiz__answer quiz__answer--yes" x-on:click="answer(true)">
                        <span class="quiz__answer-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                        {{ __('Yes') }}
                    </button>
                    <button type="button" class="quiz__answer quiz__answer--no" x-on:click="answer(false)">
                        <span class="quiz__answer-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg></span>
                        {{ __('No') }}
                    </button>
                </div>
            </div>

            {{-- Resultat --}}
            <div class="quiz__result" x-show="done" x-cloak>
                <div class="quiz__result-check" :class="{ 'quiz__result-check--muted': !concerned }">
                    <svg x-show="concerned" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <svg x-show="!concerned" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                </div>
                <h3 class="quiz__result-title" x-text="resultTitle">{{ __('Your eligibility result') }}</h3>
                <p class="quiz__result-text" x-text="resultText"></p>
                <div class="quiz__result-actions">
                    <a x-show="concerned" href="{{ route('pricing') }}" class="btn btn--coral btn--sm">{{ __('See the plans') }}</a>
                    <a x-show="excluded" href="{{ route('excluded-products') }}" class="btn btn--coral btn--sm">{{ __('See excluded products') }}</a>
                    <a x-show="!concerned && !excluded" href="{{ route('contact') }}" class="btn btn--coral btn--sm">{{ __('Contact us') }}</a>
                    <button type="button" class="btn btn--outline-dark btn--sm" x-on:click="restart()">{{ __('Start over') }}</button>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('quiz', () => ({
            step: 0,
            answers: [],
            done: false,
            questions: [
                @js(__('Is your company based outside the European Union?')),
                @js(__('Do you sell products to consumers in the European Union?')),
                @js(__('Do you sell any of these: cosmetics, food & drinks, tobacco, medical devices, or chemicals?')),
            ],
            stops: [@js(__('Location')), @js(__('Market')), @js(__('Products'))],
            answer(value) {
                this.answers.push(value);
                if (this.answers.length >= this.questions.length) {
                    this.done = true;
                    this.persist();
                } else {
                    this.step++;
                }
            },
            // Enregistrement anonyme cote serveur (une fois par quiz complete). Peripherique : un echec
            // ne casse pas l'affichage du resultat.
            persist() {
                const token = document.querySelector('meta[name="csrf-token"]');
                fetch(@js(route('quiz.result')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token ? token.content : '',
                    },
                    body: JSON.stringify({
                        q1_based_outside_eu: this.answers[0],
                        q2_sells_to_eu: this.answers[1],
                        q3_sells_restricted: this.answers[2],
                    }),
                }).catch(() => {});
            },
            restart() {
                this.step = 0;
                this.answers = [];
                this.done = false;
            },
            // Concerne : base hors UE + vend dans l'UE + ne vend PAS de categorie exclue.
            get concerned() {
                return this.answers[0] === true && this.answers[1] === true && this.answers[2] === false;
            },
            // Vend une categorie que Festilaw ne prend pas en charge.
            get excluded() {
                return this.answers[2] === true;
            },
            get resultTitle() {
                if (this.concerned) {
                    return @js(__('Yes, GPSR applies to you.'));
                }
                if (this.excluded) {
                    return @js(__('This is outside what we cover.'));
                }
                return @js(__('You\'re likely not concerned.'));
            },
            get resultText() {
                if (this.concerned) {
                    return @js(__('You must have a GPSR Responsible Person. Festilaw can provide your official mandate within 24 hours.'));
                }
                if (this.excluded) {
                    return @js(__('The categories you sell (cosmetics, food & drinks, tobacco, medical devices, chemicals) aren\'t covered by Festilaw. If you have any doubts, get in touch.'));
                }
                return @js(__('Based on your answers, you are likely not affected by GPSR through our services. If you have any doubts, please contact us.'));
            },
        }));
    });
</script>
@endpush
