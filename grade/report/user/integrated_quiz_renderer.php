<?php
/**
 * Enhanced Quiz Question Renderer
 * 
 * This renderer properly extends Moodle's core quiz renderer to provide
 * custom formatting for quiz reports and question display while maintaining
 * compatibility with Moodle's question rendering system.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/rendererbase.php');
require_once($CFG->dirroot . '/mod/quiz/renderer.php');

class enhanced_quiz_question_renderer extends \mod_quiz\output\renderer {
    
    /**
     * Render a complete question with answers for reporting
     * Uses Moodle's core question rendering but with custom formatting
     * 
     * @param object $question_data Raw question data from database
     * @param object $attempt_data Student attempt data
     * @param bool $show_correct Whether to show correct answers
     * @return string HTML output
     */
    public function render_question_report($question_data, $attempt_data = null, $show_correct = true) {
        global $DB, $CFG;
        
        try {
            // Get the actual question attempt object for proper rendering
            $question_attempt = $this->get_question_attempt_object($question_data, $attempt_data);
            
            if (!$question_attempt) {
                return $this->render_fallback_question_display($question_data, $attempt_data, $show_correct);
            }
            
            // Use Moodle's core rendering system
            $question_html = $this->render_core_question($question_attempt, $question_data);
            
            // Add our custom wrapper and additional information
            return $this->wrap_question_with_custom_formatting($question_html, $question_data, $attempt_data, $show_correct);
            
        } catch (Exception $e) {
            // Fallback to basic rendering if core rendering fails
            error_log('Question rendering failed: ' . $e->getMessage());
            return $this->render_fallback_question_display($question_data, $attempt_data, $show_correct);
        }
    }
    
    /**
     * Get a proper question attempt object for core rendering
     */
    private function get_question_attempt_object($question_data, $attempt_data) {
        global $DB;
        
        try {
            // Load the question attempt
            $qa_record = $DB->get_record('question_attempts', [
                'questionusageid' => $question_data->question_usage_id,
                'slot' => $question_data->slot
            ]);
            
            if (!$qa_record) {
                return null;
            }
            
            // Load the question usage
            $quba = question_engine::load_questions_usage_by_activity($question_data->question_usage_id);
            
            if (!$quba) {
                return null;
            }
            
            return $quba->get_question_attempt($question_data->slot);
            
        } catch (Exception $e) {
            error_log('Failed to load question attempt: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Use Moodle's core question rendering system
     */
    private function render_core_question($question_attempt, $question_data) {
        // Set up display options for review mode
        $options = new question_display_options();
        $options->readonly = true;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->correctness = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->feedback = question_display_options::VISIBLE;
        $options->numpartscorrect = question_display_options::VISIBLE;
        $options->manualcomment = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->flags = question_display_options::HIDDEN;
        $options->history = question_display_options::HIDDEN;
        
        // Render the question using Moodle's core system
        return $question_attempt->render($options, $question_data->slot);
    }
    
    /**
     * Wrap the core rendered question with our custom formatting
     */
    private function wrap_question_with_custom_formatting($core_html, $question_data, $attempt_data, $show_correct) {
        $fraction = $attempt_data->fraction ?? 0;
        
        // Determine status based on 0.5 threshold
        if ($fraction >= 0.5) {
            $status_class = $fraction >= 0.99 ? 'correct' : 'partial-correct';
            $status_text = $fraction >= 0.99 ? 'Correct' : 'Partial Credit';
        } else {
            $status_class = 'wrong';
            $status_text = 'Wrong';
        }
        
        $output = '';
        $output .= html_writer::start_div('question-item enhanced-question ' . $status_class);
        
        // Add question metadata if needed
        $output .= $this->render_question_metadata($question_data, $attempt_data);
        
        // Core question content
        $output .= html_writer::div($core_html, 'core-question-content');
        
        // Add additional analysis if needed
        if ($show_correct) {
            $output .= $this->render_additional_analysis($question_data, $attempt_data);
        }
        
        // Status indicator
        $badge_class = $fraction >= 0.5 ? ($fraction >= 0.99 ? 'status-correct' : 'status-partial') : 'status-wrong';
        $output .= html_writer::span($status_text, 'status-badge ' . $badge_class);
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render question metadata (question type, category, source quiz, etc.)
     */
    private function render_question_metadata($question_data, $attempt_data) {
        $output = '';
        
        $metadata_items = [];
        
        // Question type
        if (!empty($question_data->qtype)) {
            $metadata_items[] = '<span class="question-type">Type: ' . ucfirst($question_data->qtype) . '</span>';
        }
        
        // Category
        if (!empty($question_data->category_name)) {
            $metadata_items[] = '<span class="question-category">Category: ' . htmlspecialchars($question_data->category_name) . '</span>';
        }
        
        // Source quiz (for past year papers)
        if (!empty($question_data->source_quiz_name)) {
            $metadata_items[] = '<span class="question-source">Source: <span class="question-source-badge">' . htmlspecialchars($question_data->source_quiz_name) . '</span></span>';
        }
        
        // Tags (if available)
        if (!empty($question_data->tag_combination) && $question_data->tag_combination !== 'No Tags') {
            $tags = explode(' ; ', $question_data->tag_combination);
            $tag_badges = [];
            foreach ($tags as $tag) {
                $tag_badges[] = '<span class="tag-badge-mini">' . htmlspecialchars(trim($tag)) . '</span>';
            }
            $metadata_items[] = '<span class="question-tags">Tags: ' . implode(' ', $tag_badges) . '</span>';
        }
        
        if (!empty($metadata_items)) {
            $output .= html_writer::start_div('question-metadata');
            $output .= implode(' | ', $metadata_items);
            $output .= html_writer::end_div();
        }
        
        return $output;
    }
    
    /**
     * Render additional analysis (score breakdown, etc.)
     */
    private function render_additional_analysis($question_data, $attempt_data) {
        $output = '';
        
        if (isset($attempt_data->fraction) && isset($question_data->maxmark)) {
            $score = $attempt_data->fraction * $question_data->maxmark;
            $percentage = $attempt_data->fraction * 100;
            
            // Determine credit type
            $credit_type = '';
            if ($attempt_data->fraction >= 0.5) {
                $credit_type = $attempt_data->fraction >= 0.99 ? 'Full Credit' : 'Partial Credit (Counted as Correct)';
            } else {
                $credit_type = $attempt_data->fraction > 0 ? 'Partial Credit (Counted as Wrong)' : 'No Credit';
            }
            
            $output .= html_writer::start_div('score-analysis');
            $output .= html_writer::span(
                sprintf('Score: %.2f / %.2f (%.1f%%) - %s', 
                    $score, 
                    $question_data->maxmark, 
                    $percentage,
                    $credit_type
                ), 
                'score-details'
            );
            $output .= html_writer::end_div();
        }
        
        return $output;
    }
    
    /**
     * Fallback question display when core rendering fails
     */
    private function render_fallback_question_display($question_data, $attempt_data, $show_correct) {
        $output = '';
        
        // Determine if answer is correct
        $is_correct = $this->is_answer_correct($attempt_data);
        $status_class = $is_correct ? 'correct' : 'wrong';
        
        $output .= html_writer::start_div('question-item enhanced-question fallback-question ' . $status_class);
        
        // Add question metadata
        $output .= $this->render_question_metadata($question_data, $attempt_data);
        
        // Question text
        $question_html = $this->render_question_text_safe($question_data);
        $output .= html_writer::div($question_html, 'question-text');
        
        // Student answer
        if ($attempt_data) {
            $student_answer = $this->render_student_answer_safe($question_data, $attempt_data);
            $output .= html_writer::start_div('answer-row');
            $output .= html_writer::span('Your Answer:', 'answer-label');
            $output .= html_writer::span($student_answer, 'answer-value student-answer');
            $output .= html_writer::end_div();
        }
        
        // Correct answer
        if ($show_correct) {
            $correct_answer = $this->render_correct_answer_safe($question_data);
            $output .= html_writer::start_div('answer-row');
            $output .= html_writer::span('Correct Answer:', 'answer-label');
            $output .= html_writer::span($correct_answer, 'answer-value correct-answer');
            $output .= html_writer::end_div();
        }
        
        // Additional analysis
        $output .= $this->render_additional_analysis($question_data, $attempt_data);

        // Status badge
        $status_text = $is_correct ? 'Correct' : 'Wrong';
        $output .= html_writer::span($status_text, 'status-badge status-' . $status_class);
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Safe question text rendering with proper URL rewriting
     */
    private function render_question_text_safe($question_data) {
        if (empty($question_data->questiontext)) {
            return 'Question text not available';
        }
        
        $contextid = !empty($question_data->contextid) ? (int)$question_data->contextid : 0;
        
        // Rewrite question URLs for file access
        $rewritten_text = question_rewrite_question_urls(
            $question_data->questiontext,
            'pluginfile.php',
            $contextid,
            'question',
            'questiontext',
            [$question_data->question_usage_id ?? 0, $question_data->slot ?? 0],
            $question_data->questionid
        );
        
        $format = !empty($question_data->questiontextformat) ? $question_data->questiontextformat : FORMAT_HTML;
        
        $format_options = [
            'noclean' => true,
            'para' => false,
            'overflowdiv' => true
        ];
        
        if ($contextid) {
            try {
                $format_options['context'] = context::instance_by_id($contextid);
            } catch (Exception $e) {
                // Context not found, continue without it
            }
        }
        
        return format_text($rewritten_text, $format, $format_options);
    }
    
    /**
     * Safe student answer rendering
     */
    private function render_student_answer_safe($question_data, $attempt_data) {
        if (empty($attempt_data->stepdata_value) && $attempt_data->stepdata_value !== 0 && $attempt_data->stepdata_value !== '0') {
            return 'No answer provided';
        }
        
        // Handle different question types
        switch ($question_data->qtype ?? 'unknown') {
            case 'multichoice':
                return $this->render_multichoice_student_answer_safe($question_data, $attempt_data);
            case 'truefalse':
                return $this->render_truefalse_student_answer_safe($question_data, $attempt_data);
            case 'shortanswer':
            case 'essay':
                return $this->render_text_student_answer_safe($question_data, $attempt_data);
            default:
                return htmlspecialchars($attempt_data->stepdata_value ?? 'No answer');
        }
    }
    
    /**
     * Safe multichoice answer rendering
     */
    private function render_multichoice_student_answer_safe($question_data, $attempt_data) {
        global $DB;
        
        try {
            $choice_index = (int)$attempt_data->stepdata_value;
            
            $all_answers = $DB->get_records('question_answers', 
                ['question' => $question_data->questionid], 
                'id ASC'
            );
            
            $answers_array = array_values($all_answers);
            
            if (isset($answers_array[$choice_index]) && !empty($answers_array[$choice_index]->answer)) {
                $answer = $answers_array[$choice_index];
                
                $rewritten_answer = question_rewrite_question_urls(
                    $answer->answer,
                    'pluginfile.php',
                    $question_data->contextid ?? 0,
                    'question',
                    'answer',
                    [$question_data->question_usage_id ?? 0, $question_data->slot ?? 0],
                    $answer->id
                );
                
                return format_text($rewritten_answer, $answer->answerformat ?? FORMAT_HTML, ['noclean' => true]);
            }
            
            return 'Choice ' . ($choice_index + 1);
            
        } catch (Exception $e) {
            return 'Answer choice: ' . ($attempt_data->stepdata_value ?? 'Unknown');
        }
    }
    
    /**
     * Safe true/false answer rendering
     */
    private function render_truefalse_student_answer_safe($question_data, $attempt_data) {
        switch ($attempt_data->stepdata_value) {
            case '1':
                return 'True';
            case '0':
                return 'False';
            default:
                return 'No answer selected';
        }
    }
    
    /**
     * Safe text answer rendering
     */
    private function render_text_student_answer_safe($question_data, $attempt_data) {
        $answer_text = $attempt_data->stepdata_value ?? '';
        
        if (empty($answer_text)) {
            return 'No answer provided';
        }
        
        if (($question_data->qtype ?? '') === 'essay') {
            return format_text($answer_text, FORMAT_HTML, ['noclean' => true]);
        }
        
        return htmlspecialchars($answer_text);
    }
    
    /**
     * Safe correct answer rendering
     */
    private function render_correct_answer_safe($question_data) {
        global $DB;
        
        try {
            $correct_answers = $DB->get_records_sql("
                SELECT qa.id, qa.answer, qa.fraction, qa.answerformat
                FROM {question_answers} qa
                WHERE qa.question = :question_id
                AND qa.fraction >= 0.99
                ORDER BY qa.fraction DESC, qa.id ASC
            ", ['question_id' => $question_data->questionid]);
            
            if (empty($correct_answers)) {
                return 'Correct answer not available';
            }
            
            $answers_html = [];
            foreach ($correct_answers as $answer) {
                if (!empty($answer->answer)) {
                    $rewritten_answer = question_rewrite_question_urls(
                        $answer->answer,
                        'pluginfile.php',
                        $question_data->contextid ?? 0,
                        'question',
                        'answer',
                        [$question_data->question_usage_id ?? 0, $question_data->slot ?? 0],
                        $answer->id
                    );
                    
                    $format = $answer->answerformat ?? FORMAT_HTML;
                    $answers_html[] = format_text($rewritten_answer, $format, ['noclean' => true]);
                }
            }
            
            return !empty($answers_html) ? implode('; ', $answers_html) : 'Correct answer not available';
            
        } catch (Exception $e) {
            return 'Correct answer not available';
        }
    }
    
    /**
     * Check if student answer is correct
     */
    private function is_answer_correct($attempt_data) {
        return isset($attempt_data->fraction) && $attempt_data->fraction >= 0.5;
    }
    
    /**
     * Enhanced category summary rendering
     */
    public function render_enhanced_category_summary($category_name, $questions, $stats) {
        $output = '';
        
        $percentage_class = $this->get_percentage_class($stats['percentage']);
        
        $output .= html_writer::start_div('enhanced-category-summary');
        $output .= html_writer::start_div('category-header');
        $output .= html_writer::tag('h3', htmlspecialchars($category_name), ['class' => 'category-title']);
        $output .= html_writer::div(
            "Questions: {$stats['total_questions']} | Correct: {$stats['correct_answers']} | Score: {$stats['percentage']}%", 
            'category-stats ' . $percentage_class
        );
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('category-questions');
        foreach ($questions as $question) {
            $output .= $this->render_question_report($question, null, true);
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

        
    /**
     * Enhanced tag group summary rendering for past year papers
     */
    public function render_enhanced_tag_group_summary($tag_combination, $questions, $stats) {
        $output = '';
        
        $percentage_class = $this->get_percentage_class($stats['percentage']);
        
        $output .= html_writer::start_div('enhanced-tag-group-summary');
        $output .= html_writer::start_div('tag-group-header');
        
        // Tag combination display
        $tag_display = '';
        if (empty($tag_combination) || $tag_combination === 'No Tags') {
            $tag_display = '<span class="no-tags">Untagged Questions</span>';
        } else {
            $tags = explode(' ; ', $tag_combination);
            $tag_badges = [];
            foreach ($tags as $tag) {
                $tag_badges[] = '<span class="tag-badge">' . htmlspecialchars(trim($tag)) . '</span>';
            }
            $tag_display = implode(' ', $tag_badges);
        }
        
        $output .= html_writer::tag('h3', $tag_display, ['class' => 'tag-group-title']);
        $output .= html_writer::div(
            "Questions: {$stats['total_questions']} | Correct: {$stats['correct_answers']} | Score: {$stats['percentage']}%", 
            'tag-group-stats ' . $percentage_class
        );
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('tag-group-questions');
        foreach ($questions as $question) {
            $output .= $this->render_question_report($question, null, true);
        }
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        
        return $output;
    }
}