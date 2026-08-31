<?php
// lib/TestRenderer.php - с валидацией обязательных вопросов

class TestRenderer {
    private $test;
    private $pages;
    private $allQuestions;
    
    public function __construct(array $test) {
        $this->test = $test;
        $this->pages = $test['pages'] ?? [];
        $this->allQuestions = $this->getAllQuestions();
    }
    
    private function getAllQuestions(): array {
        $questions = [];
        foreach ($this->pages as $page) {
            if (isset($page['questions']) && is_array($page['questions'])) {
                $questions = array_merge($questions, $page['questions']);
            }
        }
        return $questions;
    }
    
    public function render(): string {
        $html = '<div class="test-container">';
        $html .= '<div class="test-header">';
        $html .= '<h2>' . htmlspecialchars($this->test['title'] ?? 'Без названия') . '</h2>';
        if (!empty($this->test['description'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($this->test['description'])) . '</p>';
        }
        $html .= '</div>';
        
        // Индикатор прогресса
        $totalQuestions = count($this->allQuestions);
        $html .= '<div class="progress-container">';
        $html .= '<div class="progress-bar" id="progressBar">';
        $html .= '<div class="progress-fill" id="progressFill" style="width: 0%;"></div>';
        $html .= '</div>';
        $html .= '<span class="progress-text" id="progressText">0 / ' . $totalQuestions . '</span>';
        $html .= '</div>';
        
        $html .= '<form id="testForm" method="POST" action="submit_test.php" onsubmit="return validateForm()">';
        $html .= '<input type="hidden" name="test_id" value="' . htmlspecialchars($this->test['id'] ?? '') . '">';
        $html .= '<input type="hidden" name="employee_id" value="' . htmlspecialchars($_GET['employee_id'] ?? '0') . '">';
        
        // Рендерим страницы
        $totalPages = count($this->pages);
        $pageIndex = 0;
        foreach ($this->pages as $page) {
            $pageIndex++;
            $isActive = $pageIndex === 1 ? 'active' : '';
            $isLast = $pageIndex === $totalPages;
            
            $html .= '<div class="page-container ' . $isActive . '" data-page="' . $pageIndex . '" id="page_' . $pageIndex . '">';
            $html .= '<div class="page-header">';
            $html .= '<h3 class="page-title">' . htmlspecialchars($page['title'] ?? 'Страница ' . $pageIndex) . '</h3>';
            $html .= '<span class="page-number">Страница ' . $pageIndex . ' из ' . $totalPages . '</span>';
            $html .= '</div>';
            
            foreach ($page['questions'] ?? [] as $index => $question) {
                $html .= $this->renderQuestion($index + 1, $question, $pageIndex);
            }
            
            $html .= '<div class="page-actions">';
            if ($pageIndex > 1) {
                $html .= '<button type="button" class="btn btn-outline" onclick="goToPage(' . ($pageIndex - 1) . ')">⬅ Назад</button>';
            }
            if (!$isLast) {
                $html .= '<button type="button" class="btn btn-primary" onclick="validateAndGoToPage(' . ($pageIndex + 1) . ')">Далее ➡</button>';
            } else {
                $html .= '<button type="submit" class="btn btn-success">📤 Отправить ответы</button>';
            }
            $html .= '</div>';
            
            $html .= '</div>';
        }
        
        $html .= '</form>';
        $html .= '</div>';
        
        // Добавляем JavaScript с валидацией
        $html .= $this->getNavigationScript();
        
        return $html;
    }
    
    private function renderQuestion(int $num, array $question, int $pageNum): string {
        $html = '<div class="question-block" data-id="' . htmlspecialchars($question['id'] ?? '') . '" data-page="' . $pageNum . '" data-required="' . ($question['required'] ? 'true' : 'false') . '">';
        $html .= '<div class="question-header">';
        $html .= '<span class="question-number">Вопрос ' . $num . '</span>';
        if (!empty($question['required'])) {
            $html .= '<span class="required-badge">* Обязательный</span>';
        }
        if (!empty($question['points'])) {
            $html .= '<span class="points-badge">' . intval($question['points']) . ' баллов</span>';
        }
        $html .= '</div>';
        
        $html .= '<div class="question-text">' . nl2br(htmlspecialchars($question['text'] ?? '')) . '</div>';
        
        if (!empty($question['image'])) {
            $imagePath = $question['image'];
            if (strpos($imagePath, 'data:image') === 0) {
                $html .= '<div class="question-image"><img src="' . $imagePath . '" alt="Изображение к вопросу" loading="lazy"></div>';
            } else {
                $html .= '<div class="question-image"><img src="' . htmlspecialchars($imagePath) . '" alt="Изображение к вопросу" loading="lazy"></div>';
            }
        }
        
        $type = $question['type'] ?? 'text';
        switch ($type) {
            case 'single':
                $html .= $this->renderSingleChoice($question);
                break;
            case 'multiple':
                $html .= $this->renderMultipleChoice($question);
                break;
            case 'text':
                $html .= $this->renderTextInput($question);
                break;
            case 'textarea':
                $html .= $this->renderTextarea($question);
                break;
            case 'rating':
                $html .= $this->renderRating($question);
                break;
            case 'scale':
                $html .= $this->renderScale($question);
                break;
            default:
                $html .= '<p class="error">Неизвестный тип вопроса</p>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    private function renderSingleChoice(array $question): string {
        $html = '<div class="options-container">';
        foreach ($question['options'] ?? [] as $key => $option) {
            $optionText = $this->getOptionText($option);
            $html .= '<label class="option-item">';
            $html .= '<input type="radio" name="q_' . htmlspecialchars($question['id'] ?? '') . '" value="' . htmlspecialchars($optionText) . '">';
            $html .= '<span>' . htmlspecialchars($optionText) . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }
    
    private function renderMultipleChoice(array $question): string {
        $html = '<div class="options-container">';
        foreach ($question['options'] ?? [] as $key => $option) {
            $optionText = $this->getOptionText($option);
            $html .= '<label class="option-item">';
            $html .= '<input type="checkbox" name="q_' . htmlspecialchars($question['id'] ?? '') . '[]" value="' . htmlspecialchars($optionText) . '">';
            $html .= '<span>' . htmlspecialchars($optionText) . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }
    
    private function getOptionText($option): string {
        if (is_array($option)) {
            return $option['text'] ?? '';
        }
        return (string)$option;
    }
    
    private function renderTextInput(array $question): string {
        return '<input type="text" name="q_' . htmlspecialchars($question['id'] ?? '') . '" class="text-input" placeholder="Введите ответ...">';
    }
    
    private function renderTextarea(array $question): string {
        return '<textarea name="q_' . htmlspecialchars($question['id'] ?? '') . '" class="textarea-input" rows="4" placeholder="Введите развернутый ответ..."></textarea>';
    }
    
    private function renderRating(array $question): string {
        $max = intval($question['max_rating'] ?? 5);
        $html = '<div class="rating-container">';
        for ($i = 1; $i <= $max; $i++) {
            $html .= '<label class="rating-item">';
            $html .= '<input type="radio" name="q_' . htmlspecialchars($question['id'] ?? '') . '" value="' . $i . '">';
            $html .= '<span>' . $i . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }
    
    private function renderScale(array $question): string {
        $min = intval($question['min'] ?? 1);
        $max = intval($question['max'] ?? 10);
        $html = '<div class="scale-container">';
        $html .= '<div class="scale-labels">';
        if (!empty($question['min_label'])) {
            $html .= '<span>' . htmlspecialchars($question['min_label']) . '</span>';
        }
        $html .= '<span class="scale-values">';
        for ($i = $min; $i <= $max; $i++) {
            $html .= '<label>';
            $html .= '<input type="radio" name="q_' . htmlspecialchars($question['id'] ?? '') . '" value="' . $i . '">';
            $html .= '<span>' . $i . '</span>';
            $html .= '</label>';
        }
        $html .= '</span>';
        if (!empty($question['max_label'])) {
            $html .= '<span>' . htmlspecialchars($question['max_label']) . '</span>';
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    private function getNavigationScript(): string {
        return '
        <script>
        let currentPage = 1;
        const totalPages = ' . count($this->pages) . ';
        const totalQuestions = ' . count($this->allQuestions) . ';
        
        function goToPage(pageNum) {
            document.querySelectorAll(".page-container").forEach(el => {
                el.classList.remove("active");
            });
            
            const target = document.getElementById("page_" + pageNum);
            if (target) {
                target.classList.add("active");
                currentPage = pageNum;
                updateProgress();
            }
            
            target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
        
        function validatePage(pageNum) {
            const page = document.getElementById("page_" + pageNum);
            if (!page) return true;
            
            const requiredQuestions = page.querySelectorAll(".question-block[data-required=\"true\"]");
            let hasError = false;
            let errorMessages = [];
            
            requiredQuestions.forEach((q, index) => {
                const inputs = q.querySelectorAll("input, textarea");
                let hasAnswer = false;
                
                inputs.forEach(input => {
                    if (input.type === "radio" || input.type === "checkbox") {
                        if (input.checked) hasAnswer = true;
                    } else if (input.type === "text" || input.type === "textarea") {
                        if (input.value.trim() !== "") hasAnswer = true;
                    }
                });
                
                if (!hasAnswer) {
                    hasError = true;
                    const questionText = q.querySelector(".question-text")?.textContent?.trim() || "Вопрос " + (index + 1);
                    errorMessages.push("❌ \"" + questionText + "\"");
                    q.style.borderColor = "#e74c3c";
                    q.style.backgroundColor = "#fff5f5";
                } else {
                    q.style.borderColor = "";
                    q.style.backgroundColor = "";
                }
            });
            
            if (hasError) {
                alert("Пожалуйста, ответьте на обязательные вопросы:\n\n" + errorMessages.join("\n"));
                return false;
            }
            
            return true;
        }
        
        function validateAndGoToPage(pageNum) {
            if (validatePage(currentPage)) {
                goToPage(pageNum);
            }
        }
        
        function validateForm() {
            return validatePage(currentPage);
        }
        
        function updateProgress() {
            const currentPageEl = document.getElementById("page_" + currentPage);
            if (!currentPageEl) return;
            
            const questions = currentPageEl.querySelectorAll(".question-block");
            let answered = 0;
            
            questions.forEach(q => {
                const inputs = q.querySelectorAll("input, textarea");
                let hasAnswer = false;
                inputs.forEach(input => {
                    if (input.type === "radio" || input.type === "checkbox") {
                        if (input.checked) hasAnswer = true;
                    } else if (input.type === "text" || input.type === "textarea") {
                        if (input.value.trim() !== "") hasAnswer = true;
                    }
                });
                if (hasAnswer) answered++;
            });
            
            const progressFill = document.getElementById("progressFill");
            const progressText = document.getElementById("progressText");
            const percent = questions.length > 0 ? (answered / questions.length) * 100 : 0;
            progressFill.style.width = percent + "%";
            progressText.textContent = answered + " / " + questions.length;
        }
        
        // Очищаем подсветку ошибок при изменении
        document.addEventListener("change", function() {
            document.querySelectorAll(".question-block[data-required=\"true\"]").forEach(q => {
                const inputs = q.querySelectorAll("input, textarea");
                let hasAnswer = false;
                inputs.forEach(input => {
                    if (input.type === "radio" || input.type === "checkbox") {
                        if (input.checked) hasAnswer = true;
                    } else if (input.type === "text" || input.type === "textarea") {
                        if (input.value.trim() !== "") hasAnswer = true;
                    }
                });
                if (hasAnswer) {
                    q.style.borderColor = "";
                    q.style.backgroundColor = "";
                }
            });
            updateProgress();
        });
        
        document.addEventListener("input", function() {
            document.querySelectorAll(".question-block[data-required=\"true\"]").forEach(q => {
                const inputs = q.querySelectorAll("input, textarea");
                let hasAnswer = false;
                inputs.forEach(input => {
                    if (input.type === "text" || input.type === "textarea") {
                        if (input.value.trim() !== "") hasAnswer = true;
                    }
                });
                if (hasAnswer) {
                    q.style.borderColor = "";
                    q.style.backgroundColor = "";
                }
            });
            updateProgress();
        });
        
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".page-container").forEach(el => {
                el.classList.remove("active");
            });
            const firstPage = document.getElementById("page_1");
            if (firstPage) {
                firstPage.classList.add("active");
            }
            updateProgress();
        });
        </script>
        ';
    }
}