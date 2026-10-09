import { quizResult } from './quiz-state.js';

document.querySelectorAll('[data-quiz]').forEach((quiz) => {
    const config = JSON.parse(quiz.dataset.quizConfig);
    const question = quiz.querySelector('.quiz__q');
    const questionTitle = quiz.querySelector('.quiz__q-text');
    const result = quiz.querySelector('.quiz__result');
    const resultTitle = quiz.querySelector('.quiz__result-title');
    const answerButtons = quiz.querySelectorAll('[data-answer]');
    let answers = [];

    const render = () => {
        const outcome = quizResult(answers);
        question.hidden = !!outcome;
        result.hidden = !outcome;
        quiz.querySelectorAll('.quiz__stop').forEach((stop, index) => {
            stop.classList.toggle('is-current', !outcome && answers.length === index);
            stop.classList.toggle('is-done', answers.length > index);
        });

        if (!outcome) {
            questionTitle.textContent = config.questions[answers.length];
            quiz.querySelector('[data-question-number]').textContent = answers.length + 1;
            return;
        }

        resultTitle.textContent = config.results[outcome].title;
        quiz.querySelector('.quiz__result-text').textContent = config.results[outcome].text;
        quiz.querySelector('.quiz__result-check').classList.toggle('quiz__result-check--muted', outcome !== 'concerned');
        quiz.querySelector('[data-result-check]').toggleAttribute('hidden', outcome !== 'concerned');
        quiz.querySelector('[data-result-info]').toggleAttribute('hidden', outcome === 'concerned');
        quiz.querySelectorAll('[data-result-link]').forEach((link) => {
            link.hidden = link.dataset.resultLink !== outcome;
        });
    };

    answerButtons.forEach((button) => {
        button.disabled = false;
        button.addEventListener('click', () => {
            if (answers.length === config.questions.length) return;
            answers.push(button.dataset.answer === 'true');
            render();
            if (answers.length < config.questions.length) {
                questionTitle.focus({ preventScroll: true });
                return;
            }

            resultTitle.focus({ preventScroll: true });
            // Anonymous statistics stay peripheral: a failed request never hides the result.
            fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    q1_based_outside_eu: answers[0],
                    q2_sells_to_eu: answers[1],
                    q3_sells_restricted: answers[2],
                }),
            }).catch(() => {});
        });
    });

    quiz.querySelector('[data-restart]').addEventListener('click', () => {
        answers = [];
        render();
        questionTitle.focus({ preventScroll: true });
    });
});
