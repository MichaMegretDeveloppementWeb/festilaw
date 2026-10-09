// Same three answers and result precedence as the server's StoreQuizResultController.
export function quizResult(answers) {
    if (answers.length !== 3 || answers.some((answer) => typeof answer !== 'boolean')) {
        return null;
    }

    if (answers[0] && answers[1] && !answers[2]) return 'concerned';
    if (answers[2]) return 'excluded';
    return 'notConcerned';
}
