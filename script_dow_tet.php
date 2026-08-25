// Разрешаем вставку: введите "allow pasting" и нажмите Enter

const questions = [];

// Ищем все блоки вопросов на странице
document.querySelectorAll('.BaseQuestion').forEach(block => {
    // Ищем ID вопроса
    const inputField = block.querySelector('[name^="answer_"]');
    const questionId = inputField ? inputField.getAttribute('name') : 'ID не найден';
    
    // Ищем текст вопроса
    const titleTextarea = block.querySelector('.BaseQuestion-TitleInput .g-text-area__control');
    const questionText = titleTextarea ? titleTextarea.value?.trim() : 'Текст вопроса не найден';
    
    // Определяем тип вопроса
    let type = 'unknown';
    if (block.querySelector('[data-qa="type-select"]')) {
        const typeText = block.querySelector('[data-qa="type-select"]')?.innerText || '';
        if (typeText.includes('Короткий ответ')) type = 'short_text';
        else if (typeText.includes('Один вариант')) type = 'radio';
        else if (typeText.includes('Несколько вариантов')) type = 'checkbox';
    }
    
    // Ищем все варианты ответов
    const options = [];
    const correctOptions = [];
    const incorrectOptions = [];
    let totalScore = 0;
    
    const optionItems = block.querySelectorAll('.ChoicesQuestionItem');
    optionItems.forEach(item => {
        // Текст варианта
        const optionTextarea = item.querySelector('.ChoicesQuestionItem-NameField .g-text-area__control');
        const optionText = optionTextarea ? optionTextarea.value?.trim() : '';
        
        if (!optionText) return;
        
        // ID варианта
        const optionInput = item.querySelector('input[type="hidden"], input[value]');
        const optionId = optionInput ? optionInput.getAttribute('value') : 'ID не найден';
        
        // БАЛЛЫ
        let score = 0;
        const scoreInput = item.querySelector('.ScoreInput-Control');
        if (scoreInput) {
            score = parseInt(scoreInput.value) || 0;
        }
        
        // Определяем правильность по баллам (если балл > 0 - правильный)
        const isCorrect = score > 0;
        
        // Суммируем общий балл за вопрос
        totalScore += score;
        
        const optionData = {
            id: optionId,
            text: optionText,
            score: score,
            isCorrect: isCorrect
        };
        
        options.push(optionData);
        
        // Разделяем на правильные и неправильные
        if (isCorrect) {
            correctOptions.push(optionData);
        } else {
            incorrectOptions.push(optionData);
        }
    });
    
    // Добавляем вопрос, только если есть варианты
    if (options.length > 0) {
        questions.push({
            question: {
                id: questionId,
                type: type,
                text: questionText
            },
            summary: {
                totalOptions: options.length,
                correctCount: correctOptions.length,
                incorrectCount: incorrectOptions.length,
                totalScore: totalScore,
                maxPossibleScore: totalScore
            },
            correctAnswers: correctOptions,
            incorrectAnswers: incorrectOptions,
            allOptions: options
        });
    }
});

// === ВЫВОД В КОНСОЛЬ В КРАСИВОМ ФОРМАТЕ ===

console.log('═══════════════════════════════════════════════════');
console.log('📊  АНАЛИЗ ТЕСТА: ВСЕ ВОПРОСЫ С ОТВЕТАМИ');
console.log('═══════════════════════════════════════════════════');
console.log(`📝 Всего вопросов: ${questions.length}`);
console.log('');

questions.forEach((q, index) => {
    console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
    console.log(`❓ ВОПРОС ${index + 1}: ${q.question.text}`);
    console.log(`📌 Тип: ${q.question.type}`);
    console.log(`📌 ID: ${q.question.id}`);
    console.log(`📊 Итого: ${q.summary.totalOptions} вариантов`);
    console.log(`✅ Правильных: ${q.summary.correctCount}`);
    console.log(`❌ Неправильных: ${q.summary.incorrectCount}`);
    console.log(`🏆 Максимальный балл: ${q.summary.totalScore}`);
    console.log('');
    
    // Правильные ответы
    if (q.correctAnswers.length > 0) {
        console.log('  ✅ ПРАВИЛЬНЫЕ ОТВЕТЫ:');
        q.correctAnswers.forEach((opt, i) => {
            console.log(`    ${i + 1}) ${opt.text}`);
            console.log(`       Баллы: ${opt.score} | ID: ${opt.id}`);
        });
        console.log('');
    }
    
    // Неправильные ответы
    if (q.incorrectAnswers.length > 0) {
        console.log('  ❌ НЕПРАВИЛЬНЫЕ ОТВЕТЫ:');
        q.incorrectAnswers.forEach((opt, i) => {
            console.log(`    ${i + 1}) ${opt.text}`);
            console.log(`       Баллы: ${opt.score} | ID: ${opt.id}`);
        });
        console.log('');
    }
    
    console.log(`  💡 За правильный ответ можно получить: ${q.summary.totalScore} баллов`);
    console.log('');
});

console.log('═══════════════════════════════════════════════════');
console.log('📋 ВСЕ ДАННЫЕ В ФОРМАТЕ JSON (скопировано в буфер)');
console.log('═══════════════════════════════════════════════════');

// Копируем полный JSON в буфер обмена
const jsonResult = JSON.stringify(questions, null, 2);
if (navigator.clipboard) {
    navigator.clipboard.writeText(jsonResult).then(() => {
        console.log('✅ Полный JSON скопирован в буфер обмена!');
        console.log(`📊 Всего вопросов: ${questions.length}`);
    }).catch(() => {
        console.log('📋 Скопируйте JSON вручную:');
        console.log(jsonResult);
    });
} else {
    console.log('📋 Скопируйте JSON вручную:');
    console.log(jsonResult);
}