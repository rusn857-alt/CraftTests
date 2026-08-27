// Разрешаем вставку: введите "allow pasting" и нажмите Enter

// === ПОЛУЧАЕМ НАЗВАНИЕ ТЕСТА ===
const testTitleElement = document.querySelector('.EditableHeading-EditableText h1, .EditableHeading-EditableText .yc-editable-text__view');
const testTitle = testTitleElement ? testTitleElement.innerText?.trim() : 'Тест без названия';

// === ПОЛУЧАЕМ ID ФОРМЫ ===
const formIdMatch = window.location.pathname.match(/\/([a-f0-9]+)\/edit/);
const formId = formIdMatch ? formIdMatch[1] : 'ID не найден';

console.log(`📝 Название теста: ${testTitle}`);
console.log(`🔑 ID формы: ${formId}`);
console.log('');

// === СОБИРАЕМ ВОПРОСЫ ===
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
    let typeLabel = 'Неизвестный тип';
    if (block.querySelector('[data-qa="type-select"]')) {
        const typeText = block.querySelector('[data-qa="type-select"]')?.innerText || '';
        if (typeText.includes('Короткий ответ')) {
            type = 'short_text';
            typeLabel = 'Короткий ответ';
        } else if (typeText.includes('Один вариант')) {
            type = 'radio';
            typeLabel = 'Один вариант';
        } else if (typeText.includes('Несколько вариантов')) {
            type = 'checkbox';
            typeLabel = 'Несколько вариантов';
        }
    }
    
    // Проверяем, обязательный ли вопрос
    const isRequired = block.querySelector('.BaseQuestion-RequiredSwitch .g-switch__control[checked]') !== null;
    
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
    
    // Добавляем вопрос, только если есть варианты или это текстовый вопрос
    if (options.length > 0 || type === 'short_text') {
        questions.push({
            question: {
                id: questionId,
                type: type,
                typeLabel: typeLabel,
                text: questionText,
                isRequired: isRequired
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

// === ФОРМИРУЕМ ИТОГОВЫЙ JSON ===
const testData = {
    test: {
        id: formId,
        title: testTitle,
        url: window.location.href,
        totalQuestions: questions.length,
        totalPossibleScore: questions.reduce((sum, q) => sum + q.summary.totalScore, 0)
    },
    questions: questions
};

// === ВЫВОД В КОНСОЛЬ В КРАСИВОМ ФОРМАТЕ ===

console.log('═══════════════════════════════════════════════════');
console.log('📊  АНАЛИЗ ТЕСТА');
console.log('═══════════════════════════════════════════════════');
console.log(`📝 Название: ${testTitle}`);
console.log(`🔑 ID формы: ${formId}`);
console.log(`📋 Всего вопросов: ${testData.test.totalQuestions}`);
console.log(`🏆 Максимальный балл за весь тест: ${testData.test.totalPossibleScore}`);
console.log('');

questions.forEach((q, index) => {
    console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
    console.log(`❓ ВОПРОС ${index + 1}: ${q.question.text}`);
    console.log(`📌 Тип: ${q.question.typeLabel}`);
    console.log(`📌 Обязательный: ${q.question.isRequired ? 'Да' : 'Нет'}`);
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
console.log('📋 ПОЛНЫЙ JSON (скопирован в буфер обмена)');
console.log('═══════════════════════════════════════════════════');

// Копируем полный JSON в буфер обмена
const jsonResult = JSON.stringify(testData, null, 2);
if (navigator.clipboard) {
    navigator.clipboard.writeText(jsonResult).then(() => {
        console.log('✅ Полный JSON скопирован в буфер обмена!');
        console.log(`📝 Название теста: ${testTitle}`);
        console.log(`📊 Всего вопросов: ${testData.test.totalQuestions}`);
        console.log(`🏆 Общий максимальный балл: ${testData.test.totalPossibleScore}`);
    }).catch(() => {
        console.log('📋 Скопируйте JSON вручную:');
        console.log(jsonResult);
    });
} else {
    console.log('📋 Скопируйте JSON вручную:');
    console.log(jsonResult);
}


/////////////В файл -

// Разрешаем вставку: введите "allow pasting" и нажмите Enter

// === ПОЛУЧАЕМ НАЗВАНИЕ ТЕСТА ===
const testTitleElement = document.querySelector('.EditableHeading-EditableText h1, .EditableHeading-EditableText .yc-editable-text__view');
const testTitle = testTitleElement ? testTitleElement.innerText?.trim() : 'Тест без названия';

// === ПОЛУЧАЕМ ID ФОРМЫ ===
const formIdMatch = window.location.pathname.match(/\/([a-f0-9]+)\/edit/);
const formId = formIdMatch ? formIdMatch[1] : 'ID не найден';

console.log(`📝 Название теста: ${testTitle}`);
console.log(`🔑 ID формы: ${formId}`);
console.log('');

// === СОБИРАЕМ ВОПРОСЫ ===
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
    let typeLabel = 'Неизвестный тип';
    if (block.querySelector('[data-qa="type-select"]')) {
        const typeText = block.querySelector('[data-qa="type-select"]')?.innerText || '';
        if (typeText.includes('Короткий ответ')) {
            type = 'short_text';
            typeLabel = 'Короткий ответ';
        } else if (typeText.includes('Один вариант')) {
            type = 'radio';
            typeLabel = 'Один вариант';
        } else if (typeText.includes('Несколько вариантов')) {
            type = 'checkbox';
            typeLabel = 'Несколько вариантов';
        }
    }
    
    // Проверяем, обязательный ли вопрос
    const isRequired = block.querySelector('.BaseQuestion-RequiredSwitch .g-switch__control[checked]') !== null;
    
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
    
    // Добавляем вопрос, только если есть варианты или это текстовый вопрос
    if (options.length > 0 || type === 'short_text') {
        questions.push({
            question: {
                id: questionId,
                type: type,
                typeLabel: typeLabel,
                text: questionText,
                isRequired: isRequired
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

// === ФОРМИРУЕМ ИТОГОВЫЙ JSON ===
const testData = {
    test: {
        id: formId,
        title: testTitle,
        url: window.location.href,
        totalQuestions: questions.length,
        totalPossibleScore: questions.reduce((sum, q) => sum + q.summary.totalScore, 0)
    },
    questions: questions
};

// === ВЫВОД В КОНСОЛЬ В КРАСИВОМ ФОРМАТЕ ===

console.log('═══════════════════════════════════════════════════');
console.log('📊  АНАЛИЗ ТЕСТА');
console.log('═══════════════════════════════════════════════════');
console.log(`📝 Название: ${testTitle}`);
console.log(`🔑 ID формы: ${formId}`);
console.log(`📋 Всего вопросов: ${testData.test.totalQuestions}`);
console.log(`🏆 Максимальный балл за весь тест: ${testData.test.totalPossibleScore}`);
console.log('');

questions.forEach((q, index) => {
    console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
    console.log(`❓ ВОПРОС ${index + 1}: ${q.question.text}`);
    console.log(`📌 Тип: ${q.question.typeLabel}`);
    console.log(`📌 Обязательный: ${q.question.isRequired ? 'Да' : 'Нет'}`);
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

// === СОХРАНЕНИЕ JSON В ФАЙЛ ===
const jsonResult = JSON.stringify(testData, null, 2);

// Формируем имя файла (заменяем недопустимые символы)
const fileName = `test_${testTitle.replace(/[^a-zA-Zа-яА-Я0-9 ]/g, '_').trim()}_${formId}.json`;

// Создаем и скачиваем файл
const blob = new Blob([jsonResult], { type: 'application/json;charset=utf-8' });
const link = document.createElement('a');
link.href = URL.createObjectURL(blob);
link.download = fileName;
document.body.appendChild(link);
link.click();
document.body.removeChild(link);
URL.revokeObjectURL(link.href);

console.log(`✅ JSON-файл сохранен в папке "Загрузки" как: ${fileName}`);
console.log(`📝 Название теста: ${testTitle}`);
console.log(`📊 Всего вопросов: ${testData.test.totalQuestions}`);
console.log(`🏆 Общий максимальный балл: ${testData.test.totalPossibleScore}`);

// Также копируем в буфер обмена для удобства
if (navigator.clipboard) {
    navigator.clipboard.writeText(jsonResult).then(() => {
        console.log('📋 JSON также скопирован в буфер обмена!');
    }).catch(() => {
        console.log('⚠️ Не удалось скопировать в буфер обмена');
    });
}