import { test } from 'node:test';
import assert from 'node:assert/strict';
import { quizResult } from '../../resources/js/web/quiz-state.js';

for (const [answers, expected] of [
    [[false, false, false], 'notConcerned'],
    [[false, false, true], 'excluded'],
    [[false, true, false], 'notConcerned'],
    [[false, true, true], 'excluded'],
    [[true, false, false], 'notConcerned'],
    [[true, false, true], 'excluded'],
    [[true, true, false], 'concerned'],
    [[true, true, true], 'excluded'],
]) {
    test(`eligibility ${answers.join(',')} => ${expected}`, () => {
        assert.equal(quizResult(answers), expected);
    });
}

test('incomplete or malformed answers do not produce a result', () => {
    for (const answers of [[], [true], [true, true], [true, true, 'false'], [true, true, false, true]]) {
        assert.equal(quizResult(answers), null);
    }
});
