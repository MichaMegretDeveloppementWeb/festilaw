@php
    $quizConfig = [
        'endpoint' => route('quiz.result'),
        'questions' => [
            __('Is your company based outside the European Union?'),
            __('Do you sell products to consumers in the European Union?'),
            __('Do you sell any of these: cosmetics, food & drinks, tobacco, medical devices, or chemicals?'),
        ],
        'results' => [
            'concerned' => [
                'title' => __('Yes, GPSR applies to you.'),
                'text' => __('You must have a GPSR Responsible Person. Festilaw can provide your official mandate within 24 hours.'),
            ],
            'excluded' => [
                'title' => __('This is outside what we cover.'),
                'text' => __('The categories you sell (cosmetics, food & drinks, tobacco, medical devices, chemicals) aren\'t covered by Festilaw. If you have any doubts, get in touch.'),
            ],
            'notConcerned' => [
                'title' => __('You\'re likely not concerned.'),
                'text' => __('Based on your answers, you are likely not affected by GPSR through our services. If you have any doubts, please contact us.'),
            ],
        ],
    ];
@endphp
<section id="quiz" class="quiz">
    <div class="quiz__inner">
        <span class="quiz__badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="2.5" x2="14" y2="2.5"/><line x1="12" y1="2.5" x2="12" y2="4.5"/><circle cx="12" cy="14" r="8"/><line x1="12" y1="14" x2="12" y2="9.5"/><line x1="17.5" y1="8.5" x2="19" y2="7"/></svg>
            <span class="quiz__badge-num">{{ __('30-second') }}</span>
            <span class="quiz__badge-label">{{ __('eligibility check') }}</span>
        </span>
        <h2 class="quiz__title">{{ __('Am I concerned by GPSR?') }}</h2>

        <div class="quiz__card" data-quiz data-quiz-config="{{ json_encode($quizConfig) }}">
            <div class="quiz__tracker">
                <div class="quiz__tracker-line"></div>
                <div class="quiz__stops">
                    <div class="quiz__stop is-current" >
                        <div class="quiz__stop-circle">1</div><span class="quiz__stop-label">{{ __('Location') }}</span>
                    </div>
                    <div class="quiz__stop" >
                        <div class="quiz__stop-circle">2</div><span class="quiz__stop-label">{{ __('Market') }}</span>
                    </div>
                    <div class="quiz__stop" >
                        <div class="quiz__stop-circle">3</div><span class="quiz__stop-label">{{ __('Products') }}</span>
                    </div>
                </div>
            </div>

            <div class="quiz__q">
                <span class="quiz__q-count">{{ __('QUESTION') }} <span data-question-number>1</span> {{ __('OF') }} <span>3</span></span>
                <h3 class="quiz__q-text" tabindex="-1">{{ __('Is your company based outside the European Union?') }}</h3>
                <div class="quiz__answers">
                    <button type="button" class="quiz__answer quiz__answer--yes" data-answer="true" disabled>
                        <span class="quiz__answer-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                        {{ __('Yes') }}
                    </button>
                    <button type="button" class="quiz__answer quiz__answer--no" data-answer="false" disabled>
                        <span class="quiz__answer-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg></span>
                        {{ __('No') }}
                    </button>
                </div>
            </div>

            <div class="quiz__result" hidden>
                <div class="quiz__result-check" >
                    <svg data-result-check width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <svg data-result-info hidden width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                </div>
                <h3 class="quiz__result-title" tabindex="-1">{{ __('Your eligibility result') }}</h3>
                <p class="quiz__result-text"></p>
                <div class="quiz__result-actions">
                    <a data-result-link="concerned" href="{{ route('pricing') }}" class="btn btn--coral btn--sm">{{ __('See the plans') }}</a>
                    <a data-result-link="excluded" hidden href="{{ route('excluded-products') }}" class="btn btn--coral btn--sm">{{ __('See excluded products') }}</a>
                    <a data-result-link="notConcerned" hidden href="{{ route('contact') }}" class="btn btn--coral btn--sm">{{ __('Contact us') }}</a>
                    <button type="button" class="btn btn--outline-dark btn--sm" data-restart>{{ __('Start over') }}</button>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
    @vite('resources/js/web/quiz.js')
@endpush
