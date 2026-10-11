<?php
/**
 * Random Bag Report Quiz Question Renderer
 * 
 * This renderer extends Moodle's core question rendering to provide
 * custom formatting for quiz reports and question display.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/rendererbase.php');

class random_bag_report_quiz_question_renderer extends plugin_renderer_base {
    
    /**
     * Render a complete question with answers for reporting
     * 
     * @param object $question_data Raw question data from database
     * @param object $attempt_data Student attempt data
     * @param bool $show_correct Whether to show correct answers
     * @return string HTML output
     */
    public function render_question_report($question_data, $attempt_data = null, $show_correct = true) {
        
        $output = '';
        
        // Process question text with proper URL rewriting
        $question_html = $this->render_question_text($question_data);
        
        // Get student's answer if attempt data provided
        $student_answer_html = '';
        if ($attempt_data) {
            $student_answer_html = $this->render_student_answer($question_data, $attempt_data);
        }
        
        // Get correct answer if requested
        $correct_answer_html = '';
        if ($show_correct) {
            $correct_answer_html = $this->render_correct_answer($question_data);
        }
        
        // Determine if answer is correct
        $is_correct = $this->is_answer_correct($attempt_data);
        $status_class = $is_correct ? 'correct' : 'wrong';
        
        // Build complete question HTML
        $output .= html_writer::start_div('question-item ' . $status_class);
        
        // Question text
        $output .= html_writer::div($question_html, 'question-text');
        
        // Student answer
        if ($student_answer_html) {
            $output .= html_writer::start_div('answer-row');
            $output .= html_writer::span('Your Answer:', 'answer-label');
            $output .= html_writer::span($student_answer_html, 'answer-value student-answer');
            $output .= html_writer::end_div();
        }
        
        // Correct answer
        if ($correct_answer_html) {
            $output .= html_writer::start_div('answer-row');
            $output .= html_writer::span('Correct Answer:', 'answer-label');
            $output .= html_writer::span($correct_answer_html, 'answer-value correct-answer');
            $output .= html_writer::end_div();
        }
        
        // Status badge
        $status_text = $is_correct ? 'Correct' : 'Wrong';
        $output .= html_writer::span($status_text, 'status-badge status-' . $status_class);
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render question text with proper file URL rewriting
     * 
     * @param object $question_data Question data from database
     * @return string Formatted question text HTML
     */
    public function render_question_text($question_data) {
        global $CFG;
        
        if (empty($question_data->questiontext)) {
            return 'Question text not available';
        }
        
        // Ensure we have context ID
        $contextid = !empty($question_data->contextid) ? (int)$question_data->contextid : 0;

        // Rewrite question URLs for file access
        $rewritten_text = question_rewrite_question_urls(
            $question_data->questiontext,
            'pluginfile.php',
            $contextid,
            'question',
            'questiontext',
            [$question_data->question_usage_id, $question_data->slot],
            $question_data->questionid
        );
        // Get question text format (default to HTML if not specified)
        $format = !empty($question_data->questiontextformat) ? $question_data->questiontextformat : FORMAT_HTML;
        
        // Format the text with proper context
        $format_options = [
            'noclean' => true,
            'para' => false,
            'overflowdiv' => true
        ];
        
        if ($contextid) {
            $format_options['context'] = context::instance_by_id($contextid);
        }
        
        return format_text($rewritten_text, $format, $format_options);
    }
    
    /**
     * Render student's answer based on question type
     * 
     * @param object $question_data Question data
     * @param object $attempt_data Attempt step data
     * @return string Student answer HTML
     */
    public function render_student_answer($question_data, $attempt_data) {
        
        if (empty($attempt_data->stepdata_value) && $attempt_data->stepdata_value !== 0 && $attempt_data->stepdata_value !== '0') {
            return 'No answer provided';
        }
        
        // Handle different question types
        switch ($question_data->qtype ?? 'unknown') {
            case 'multichoice':
                return $this->render_multichoice_student_answer($question_data, $attempt_data);
            case 'truefalse':
                return $this->render_truefalse_student_answer($question_data, $attempt_data);
            case 'shortanswer':
            case 'essay':
                return $this->render_text_student_answer($question_data, $attempt_data);
            default:
                return $this->render_generic_student_answer($attempt_data);
        }
    }
    
    /**
     * Render correct answer for the question
     * 
     * @param object $question_data Question data
     * @return string Correct answer HTML
     */
    public function render_correct_answer($question_data) {
        global $DB;
        
        // Get correct answer(s) from question_answers table
        $correct_answers = $DB->get_records_sql("
            SELECT qa.id, qa.answer, qa.fraction, qa.answerformat
            FROM {question_answers} qa
            WHERE qa.question = :question_id
            AND qa.fraction >= 0.99
            ORDER BY qa.fraction DESC, qa.id ASC
        ", ['question_id' => $question_data->questionid]);
        
        if (empty($correct_answers)) {
            return 'Answer not available';
        }
        
        $answers_html = [];
        foreach ($correct_answers as $answer) {
            if (!empty($answer->answer)) {
                // Rewrite URLs in answer text
                $rewritten_answer = question_rewrite_question_urls(
                    $answer->answer,
                    'pluginfile.php',
                    $question_data->contextid ?? 0,
                    'question',
                    'answer',
                    [$question_data->question_usage_id, $question_data->slot],
                    $answer->id
                );
                
                $format = $answer->answerformat ?? FORMAT_HTML;
                $answers_html[] = format_text($rewritten_answer, $format, ['noclean' => true]);
            }
        }
        
        return !empty($answers_html) ? implode('; ', $answers_html) : 'Answer not available';
    }
    
    /**
     * Render multichoice student answer
     */
    private function render_multichoice_student_answer($question_data, $attempt_data) {
        
        global $DB;
        
        $choice_index = $attempt_data->stepdata_value;
        
        // First, let's see what answers exist for this question
        try {
            $all_answers = $DB->get_records('question_answers', 
                ['question' => $question_data->questionid], 
                'id ASC'
            );
            
            // Convert to array and get by index
            $answers_array = array_values($all_answers);
            
            if (isset($answers_array[$choice_index])) {
                $answer = $answers_array[$choice_index];
                
                if (!empty($answer->answer)) {
                    $rewritten_answer = question_rewrite_question_urls(
                        $answer->answer,
                        'pluginfile.php',
                        $question_data->contextid ?? 0,
                        'question',
                        'answer',
                        [$question_data->question_usage_id, $question_data->slot],
                        $answer->id
                    );
                    
                    return format_text($rewritten_answer, $answer->answerformat ?? FORMAT_HTML, ['noclean' => true]);
                }
            }
            
            return 'Choice index: ' . $choice_index . ' (Answer not found in array of ' . count($answers_array) . ' answers)';
            
        } catch (Exception $e) {
            return 'Error retrieving answer: ' . $e->getMessage();
        }
    }
    
    /**
     * Render true/false student answer
     */
    private function render_truefalse_student_answer($question_data, $attempt_data) {
        $answer_value = $attempt_data->stepdata_value;
        
        switch ($answer_value) {
            case '1':
                return 'True';
            case '0':
                return 'False';
            default:
                return 'No answer selected';
        }
    }
    
    /**
     * Render text-based student answer (short answer, essay)
     */
    private function render_text_student_answer($question_data, $attempt_data) {
        $answer_text = $attempt_data->stepdata_value;
        
        if (empty($answer_text)) {
            return 'No answer provided';
        }
        
        // For essay questions, the text might contain HTML
        if (($question_data->qtype ?? '') === 'essay') {
            return format_text($answer_text, FORMAT_HTML, ['noclean' => true]);
        }
        
        // For short answer, treat as plain text
        return htmlspecialchars($answer_text);
    }
    
    /**
     * Generic student answer renderer
     */
    private function render_generic_student_answer($attempt_data) {
        $answer = $attempt_data->stepdata_value ?? '';
        return !empty($answer) ? htmlspecialchars($answer) : 'No answer provided';
    }
    
    /**
     * Check if student answer is correct
     * 
     * @param object $attempt_data Attempt data with fraction
     * @return bool True if correct
     */
    private function is_answer_correct($attempt_data) {
        return isset($attempt_data->fraction) && $attempt_data->fraction >= 0.99;
    }
    
    /**
     * Render a category summary with questions
     * 
     * @param string $category_name Category name
     * @param array $questions Array of question data
     * @param array $stats Category statistics
     * @return string HTML output
     */
    public function render_category_summary($category_name, $questions, $stats) {
        $output = '';
        
        // Category header
        $percentage_class = $this->get_percentage_class($stats['Percentage']);
        
        $output .= html_writer::start_div('category-summary');
        $output .= html_writer::start_div('category-header');
        $output .= html_writer::tag('h3', htmlspecialchars($category_name), ['class' => 'category-title']);
        $output .= html_writer::div("Questions: {$stats['Total Questions']} | Correct: {$stats['Right']} | Score: {$stats['Percentage']}%", 'category-stats ' . $percentage_class);
        $output .= html_writer::end_div();
        
        // Questions
        $output .= html_writer::start_div('category-questions');
        foreach ($questions as $question) {
            $output .= $this->render_question_report($question['data'], $question['attempt'], true);
        }
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Get CSS class for percentage score
     */
    private function get_percentage_class($percentage) {
        if ($percentage >= 90) return 'percentage-excellent';
        if ($percentage >= 75) return 'percentage-good';
        if ($percentage >= 60) return 'percentage-average';
        return 'percentage-poor';
    }
}