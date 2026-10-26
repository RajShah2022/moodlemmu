<?php
/**
 * Quiz Attempt Data Processor
 * 
 * Helper class to process and structure quiz attempt data
 * for rendering and reporting purposes.
 */

defined('MOODLE_INTERNAL') || die();

class quiz_attempt_data_processor {
    
    private $db;
    
    public function __construct() {
        global $DB;
        $this->db = $DB;
    }
    
    /**
     * Get comprehensive question data for a quiz attempt with enhanced metadata
     * 
     * @param int $attempt_id Quiz attempt ID
     * @return array Processed question data grouped by question ID
     */
    public function get_attempt_questions($attempt_id) {
        // Get basic question info with enhanced metadata
        $questions_sql = "
            SELECT 
                qatt.slot,
                qatt.questionid,
                qatt.id as question_attempt_id,
                q.name as question_name,
                q.questiontext,
                q.questiontextformat,
                q.qtype,
                qatt.maxmark,
                qc.name as category_name,
                qc.contextid AS contextid,
                qatt.questionusageid as question_usage_id,
                q.defaultmark,
                qc.id as category_id,
                qc.info as category_info,
                qc.parent as parent_category,
                GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ' ; ') as tag_combination
            FROM {question_attempts} qatt
            JOIN {question_usages} qu ON qu.id = qatt.questionusageid
            JOIN {quiz_attempts} qa ON qa.uniqueid = qu.id
            JOIN {question} q ON q.id = qatt.questionid
            JOIN {question_versions} qv ON q.id = qv.questionid
            JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
            JOIN {question_categories} qc ON qbe.questioncategoryid = qc.id
            LEFT JOIN {tag_instance} ti ON ti.itemid = qatt.questionid AND ti.itemtype = 'question'
            LEFT JOIN {tag} t ON t.id = ti.tagid AND t.name NOT LIKE '%/%'
            WHERE qa.id = :attempt_id
            GROUP BY qatt.slot, qatt.questionid, qatt.id, q.name, q.questiontext, 
                     q.questiontextformat, q.qtype, qatt.maxmark, qc.name, 
                     qc.contextid, qatt.questionusageid, q.defaultmark, qc.id, 
                     qc.info, qc.parent
            ORDER BY qatt.slot
        ";
        
        $questions = $this->db->get_records_sql($questions_sql, ['attempt_id' => $attempt_id]);
        
        foreach ($questions as $question) {
            // Enhanced step information gathering
            $this->enhance_question_with_step_data($question);
            
            // Add question type specific metadata
            $this->add_question_type_metadata($question);
            
            // Add answer options for multichoice questions
            if ($question->qtype === 'multichoice') {
                $question->answer_options = $this->get_question_answer_options($question->questionid);
            }
        }
        
        // Convert to array format expected by your code
        $result = [];
        foreach ($questions as $question) {
            $result[$question->questionid] = $question;
        }
        
        return $result;
    }
     
    // Include tag-related methods from your original processor
    public function get_attempt_questions_with_tags($attempt_id) {
        // Get basic question info with tags - following your existing pattern
        $questions_sql = "
            SELECT 
                qatt.slot,
                qatt.questionid,
                qatt.id as question_attempt_id,
                q.name as question_name,
                q.questiontext,
                q.questiontextformat,
                q.qtype,
                qatt.maxmark,
                qc.name as category_name,
                qc.contextid AS contextid,
                qatt.questionusageid as question_usage_id,
                GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ' ; ') as tag_combination
            FROM {question_attempts} qatt
            JOIN {question_usages} qu ON qu.id = qatt.questionusageid
            JOIN {quiz_attempts} qa ON qa.uniqueid = qu.id
            JOIN {question} q ON q.id = qatt.questionid
            JOIN {question_versions} qv ON q.id = qv.questionid
            JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
            JOIN {question_categories} qc ON qbe.questioncategoryid = qc.id
            LEFT JOIN {tag_instance} ti ON ti.itemid = qatt.questionid AND ti.itemtype = 'question'
            LEFT JOIN {tag} t ON t.id = ti.tagid AND t.name NOT LIKE '%/%'
            WHERE qa.id = :attempt_id
            GROUP BY qatt.slot, qatt.questionid, qatt.id, q.name, q.questiontext, 
                    q.questiontextformat, q.qtype, qatt.maxmark, qc.name, 
                    qc.contextid, qatt.questionusageid
            ORDER BY qatt.slot
        ";
        
        $questions = $this->db->get_records_sql($questions_sql, ['attempt_id' => $attempt_id]);
        
        foreach ($questions as $question) {
            // Get the latest step info (for fraction/state)
            $latest_step_sql = "
                SELECT fraction, state, sequencenumber
                FROM {question_attempt_steps}
                WHERE questionattemptid = :qa_id
                ORDER BY sequencenumber DESC
                LIMIT 1
            ";
            
            $latest_step = $this->db->get_record_sql($latest_step_sql, ['qa_id' => $question->question_attempt_id]);
            
            if ($latest_step) {
                $question->fraction = $latest_step->fraction;
                $question->state = $latest_step->state;
                $question->score = $latest_step->fraction * $question->maxmark;
            }
            
            // Get ALL step data (INCLUDING choice and answer data)
            $step_data_sql = "
                SELECT qasd.name, qasd.value, qattstep.sequencenumber
                FROM {question_attempt_steps} qattstep
                JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qattstep.id
                WHERE qattstep.questionattemptid = :qa_id
                AND qasd.name IN ('choice0', 'choice1', 'choice2', 'choice3', 'choice4', 'choice5', 'answer')
                ORDER BY qattstep.sequencenumber
            ";
            
            $step_data = $this->db->get_records_sql($step_data_sql, ['qa_id' => $question->question_attempt_id]);
            
            $question->student_answers = [];
            $question->step_data = [];
            
            foreach ($step_data as $data) {
                $question->student_answers[$data->name] = $data->value;
                $question->step_data[] = (object)[
                    'name' => $data->name,
                    'value' => $data->value
                ];
            }
            
            // Handle tags - if no tags found, set as 'No Tags'
            // Handle tags - if no tags found, set as 'No Tags'
            if (empty($question->tag_combination)) {
                $question->tag_combination = 'No Tags';
            } else {
                // Clean tag names to remove PP suffixes
                $question->tag_combination = $this->clean_tag_names($question->tag_combination);
            }
        }
        
        // Convert to array for processing
        $result = [];
        foreach ($questions as $question) {
            $result[] = $question;
        }
        
        return $result;
    }
    
    /**
     * Enhanced step data gathering with better answer extraction
     */
    private function enhance_question_with_step_data($question) {
        // Get the latest step info (for fraction/state)
        $latest_step_sql = "
            SELECT fraction, state, sequencenumber, timecreated
            FROM {question_attempt_steps}
            WHERE questionattemptid = :qa_id
            ORDER BY sequencenumber DESC
            LIMIT 1
        ";
        
        $latest_step = $this->db->get_record_sql($latest_step_sql, ['qa_id' => $question->question_attempt_id]);
        
        if ($latest_step) {
            $question->fraction = $latest_step->fraction;
            $question->state = $latest_step->state;
            $question->score = $latest_step->fraction * $question->maxmark;
            $question->time_completed = $latest_step->timecreated;
        }
        
        // Get ALL step data with enhanced filtering
        $step_data_sql = "
            SELECT 
                qasd.name, 
                qasd.value, 
                qattstep.sequencenumber,
                qattstep.timecreated,
                qattstep.userid
            FROM {question_attempt_steps} qattstep
            JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qattstep.id
            WHERE qattstep.questionattemptid = :qa_id
            AND qasd.name IN ('choice0', 'choice1', 'choice2', 'choice3', 'choice4', 'choice5', 'answer', 'answertext', 'attachments')
            ORDER BY qattstep.sequencenumber DESC
        ";
        
        $step_data = $this->db->get_records_sql($step_data_sql, ['qa_id' => $question->question_attempt_id]);
        
        $question->student_answers = [];
        $question->step_data = [];
        $question->answer_history = [];
        
        foreach ($step_data as $data) {
            // Keep most recent answer for each type
            if (!isset($question->student_answers[$data->name])) {
                $question->student_answers[$data->name] = $data->value;
            }
            
            // Keep complete step history
            $question->step_data[] = (object)[
                'name' => $data->name,
                'value' => $data->value,
                'sequence' => $data->sequencenumber,
                'time' => $data->timecreated
            ];
            
            // Track answer changes over time
            $question->answer_history[] = (object)[
                'step' => $data->sequencenumber,
                'answer' => $data->value,
                'type' => $data->name,
                'time' => $data->timecreated
            ];
        }
        
        // Sort history by sequence
        usort($question->answer_history, function($a, $b) {
            return $b->step - $a->step; // Most recent first
        });
    }
    
    /**
     * Add question type specific metadata
     */
    private function add_question_type_metadata($question) {
        switch ($question->qtype) {
            case 'multichoice':
                $question->type_display = 'Multiple Choice';
                $question->allows_partial_credit = true;
                break;
            case 'truefalse':
                $question->type_display = 'True/False';
                $question->allows_partial_credit = false;
                break;
            case 'shortanswer':
                $question->type_display = 'Short Answer';
                $question->allows_partial_credit = true;
                break;
            case 'essay':
                $question->type_display = 'Essay';
                $question->allows_partial_credit = true;
                break;
            case 'numerical':
                $question->type_display = 'Numerical';
                $question->allows_partial_credit = true;
                break;
            case 'match':
                $question->type_display = 'Matching';
                $question->allows_partial_credit = true;
                break;
            default:
                $question->type_display = ucfirst($question->qtype ?? 'Unknown');
                $question->allows_partial_credit = true;
        }
    }
    
    /**
     * Get answer options for multichoice questions
     */
    private function get_question_answer_options($question_id) {
        return $this->db->get_records_sql("
            SELECT 
                qa.id,
                qa.answer,
                qa.fraction,
                qa.feedback,
                qa.answerformat,
                qa.feedbackformat
            FROM {question_answers} qa
            WHERE qa.question = :question_id
            ORDER BY qa.id ASC
        ", ['question_id' => $question_id]);
    }
    
    /**
     * Enhanced primary answer extraction with better handling
     */
    public function extract_primary_answer($question_data) {
        if (empty($question_data->student_answers)) {
            return null;
        }
        
        $answers = $question_data->student_answers;
        
        // Check for different answer formats based on question type
        switch ($question_data->qtype ?? 'unknown') {
            case 'multichoice':
                return $this->extract_multichoice_answer($answers);
            case 'truefalse':
                return $this->extract_truefalse_answer($answers);
            case 'shortanswer':
            case 'essay':
                return $this->extract_text_answer($answers);
            default:
                return $this->extract_generic_answer($answers);
        }
    }
    
    /**
     * Extract multichoice answer with better logic
     */
    private function extract_multichoice_answer($answers) {
        // Try 'answer' format first (choice index)
        if (isset($answers['answer']) && $answers['answer'] !== '') {
            return (int)$answers['answer'];
        }
        
        // Try 'choice' format (choice0, choice1, etc.)
        foreach ($answers as $key => $value) {
            if (strpos($key, 'choice') === 0 && $value == '1') {
                return (int)substr($key, 6); // Extract index
            }
        }
        
        return null;
    }
    
    /**
     * Extract true/false answer
     */
    private function extract_truefalse_answer($answers) {
        if (isset($answers['answer'])) {
            return (int)$answers['answer'];
        }
        
        return null;
    }
    
    /**
     * Extract text-based answer
     */
    private function extract_text_answer($answers) {
        if (isset($answers['answer'])) {
            return $answers['answer'];
        }
        
        if (isset($answers['answertext'])) {
            return $answers['answertext'];
        }
        
        return null;
    }
    
    /**
     * Generic answer extraction
     */
    private function extract_generic_answer($answers) {
        // Return the first non-empty answer found
        foreach ($answers as $key => $value) {
            if (!empty($value) && $key !== '_order' && $key !== '-finish') {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Get comprehensive correct answers for a question with metadata
     */
    public function get_correct_answers_enhanced($question_id) {
        $sql = "
            SELECT 
                qa.id, 
                qa.answer, 
                qa.fraction, 
                qa.answerformat, 
                qa.feedback,
                qa.feedbackformat
            FROM {question_answers} qa
            WHERE qa.question = :question_id
            AND qa.fraction >= 0.99
            ORDER BY qa.fraction DESC, qa.id ASC
        ";
        
        $correct_answers = $this->db->get_records_sql($sql, ['question_id' => $question_id]);
        
        // Add additional metadata
        foreach ($correct_answers as $answer) {
            $answer->is_correct = true;
            $answer->percentage = round($answer->fraction * 100, 1);
        }
        
        return $correct_answers;
    }
    
    /**
     * Enhanced category grouping with additional statistics
     */
    public function group_by_category($questions) {
        $categories = [];
        
        foreach ($questions as $question) {
            $category = $question->category_name ?? 'Default Category';
            
            // Initialize category if not exists
            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'name' => $category,
                    'total_questions' => 0,
                    'correct_answers' => 0,
                    'wrong_answers' => 0,
                    'partial_credit' => 0,
                    'total_marks' => 0,
                    'earned_marks' => 0,
                    'percentage' => 0,
                    'average_time' => 0,
                    'questions' => [],
                    'question_types' => []
                ];
            }
            
            // Add question to category
            $categories[$category]['questions'][] = $question;
            $categories[$category]['total_questions']++;
            $categories[$category]['total_marks'] += $question->maxmark ?? 1;
            
            // Track question types in this category
            $qtype = $question->qtype ?? 'unknown';
            if (!in_array($qtype, $categories[$category]['question_types'])) {
                $categories[$category]['question_types'][] = $qtype;
            }
            
            // Calculate performance metrics
            $fraction = $question->fraction ?? 0;
            $earned = $fraction * ($question->maxmark ?? 1);
            $categories[$category]['earned_marks'] += $earned;
            
            // Use 0.5 threshold for correct/wrong classification
            if ($fraction >= 0.5) {
                $categories[$category]['correct_answers']++;
            } else {
                $categories[$category]['wrong_answers']++;
            }

            // Still track partial credit separately for detailed analysis
            if ($fraction >= 0.99) {
                // Full credit - already counted as correct above
            } elseif ($fraction > 0) {
                $categories[$category]['partial_credit']++;
            }
            
            // Add time tracking if available
            if (isset($question->time_completed)) {
                // This would need additional logic to calculate time spent
            }
        }
        
        // Calculate final statistics
        foreach ($categories as &$category) {
            if ($category['total_questions'] > 0) {
                $category['percentage'] = round(
                    ($category['correct_answers'] / $category['total_questions']) * 100, 
                    1
                );
            }
            if ($category['total_marks'] > 0) {
                $category['marks_percentage'] = round(
                    ($category['earned_marks'] / $category['total_marks']) * 100,
                    1
                );
            }
        }
        
        return $categories;
    }

    /**
     * Group all questions directly by tag combinations (no category layer)
     */
    public function group_all_by_tags($questions) {
        $tag_groups = [];

        foreach ($questions as $question) {
            $tag_key = !empty($question->tag_combination) ? $question->tag_combination : 'No Tags';

            if (!isset($tag_groups[$tag_key])) {
                $tag_groups[$tag_key] = [
                    'name' => $tag_key,
                    'total_questions' => 0,
                    'correct_answers' => 0,
                    'wrong_answers' => 0,
                    'total_marks' => 0,
                    'earned_marks' => 0,
                    'percentage' => 0,
                    'questions' => [],
                ];
            }

            $tag_groups[$tag_key]['questions'][] = $question;
            $tag_groups[$tag_key]['total_questions']++;
            $tag_groups[$tag_key]['total_marks'] += $question->maxmark ?? 1;

            $fraction = $question->fraction ?? 0;
            $tag_groups[$tag_key]['earned_marks'] += $fraction * ($question->maxmark ?? 1);

            if ($fraction >= 0.5) {
                $tag_groups[$tag_key]['correct_answers']++;
            } else {
                $tag_groups[$tag_key]['wrong_answers']++;
            }
        }

        // Calculate percentages
        foreach ($tag_groups as &$group) {
            if ($group['total_questions'] > 0) {
                $group['percentage'] = round(
                    ($group['correct_answers'] / $group['total_questions']) * 100,
                    1
                );
            }
        }
        unset($group);

        return $tag_groups;
    }

    /**
     * Calculate overall stats from tag groups (instead of categories)
     */
    public function calculate_overall_stats_from_tags($tag_groups) {
        $total_questions = 0;
        $total_correct = 0;
        $total_wrong = 0;
        $total_marks = 0;
        $earned_marks = 0;

        foreach ($tag_groups as $group) {
            $total_questions += $group['total_questions'];
            $total_correct += $group['correct_answers'];
            $total_wrong += $group['wrong_answers'];
            $total_marks += $group['total_marks'] ?? 0;
            $earned_marks += $group['earned_marks'] ?? 0;
        }

        $overall_percentage = $total_questions > 0
            ? round(($total_correct / $total_questions) * 100, 1)
            : 0;

        return [
            'total_questions' => $total_questions,
            'total_correct' => $total_correct,
            'total_wrong' => $total_wrong,
            'percentage' => $overall_percentage,
            'total_marks' => $total_marks,
            'earned_marks' => round($earned_marks, 2),
        ];
    }

    /**
     * Enhanced overall statistics calculation
     */
    public function calculate_overall_stats($categories) {
        $total_questions = 0;
        $total_correct = 0;
        $total_wrong = 0;
        $total_partial = 0;
        $total_marks = 0;
        $earned_marks = 0;
        
        foreach ($categories as $category) {
            $total_questions += $category['total_questions'];
            $total_correct += $category['correct_answers'];
            $total_wrong += $category['wrong_answers'];
            $total_partial += $category['partial_credit'] ?? 0;
            $total_marks += $category['total_marks'] ?? 0;
            $earned_marks += $category['earned_marks'] ?? 0;
        }
        
        $overall_percentage = $total_questions > 0 ? 
            round(($total_correct / $total_questions) * 100, 1) : 0;
        
        $marks_percentage = $total_marks > 0 ?
            round(($earned_marks / $total_marks) * 100, 1) : 0;
        
        return [
            'total_questions' => $total_questions,
            'total_correct' => $total_correct,
            'total_wrong' => $total_wrong,
            'total_partial' => $total_partial,
            'percentage' => $overall_percentage,
            'total_marks' => $total_marks,
            'earned_marks' => round($earned_marks, 2),
            'marks_percentage' => $marks_percentage,
            'grade' => $this->calculate_letter_grade($marks_percentage)
        ];
    }
    
    /**
     * Calculate letter grade based on percentage
     */
    private function calculate_letter_grade($percentage) {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 60) return 'D';
        return 'F';
    }
    
    /**
     * Get detailed question performance analysis
     */
    public function get_question_analysis($question_data) {
        $analysis = [
            'difficulty_level' => $this->calculate_difficulty_level($question_data),
            'time_spent' => $this->calculate_time_spent($question_data),
            'attempt_count' => $this->count_answer_attempts($question_data),
            'confidence_indicator' => $this->assess_confidence($question_data)
        ];
        
        return $analysis;
    }
    
    /**
     * Calculate difficulty level based on class performance (would need additional data)
     */
    private function calculate_difficulty_level($question_data) {
        // This would require class-wide statistics
        // For now, return based on question type complexity
        switch ($question_data->qtype ?? 'unknown') {
            case 'truefalse':
                return 'Easy';
            case 'multichoice':
                return 'Medium';
            case 'essay':
                return 'Hard';
            default:
                return 'Medium';
        }
    }
    
    /**
     * Calculate time spent on question (if step timing data available)
     */
    private function calculate_time_spent($question_data) {
        if (empty($question_data->answer_history)) {
            return null;
        }
        
        $history = $question_data->answer_history;
        if (count($history) < 2) {
            return null;
        }
        
        $start_time = end($history)->time;
        $end_time = reset($history)->time;
        
        return $end_time - $start_time; // Time in seconds
    }
    
    /**
     * Count how many times student changed their answer
     */
    private function count_answer_attempts($question_data) {
        return count($question_data->answer_history ?? []);
    }
    
    /**
     * Assess confidence based on answer pattern
     */
    private function assess_confidence($question_data) {
        $attempt_count = $this->count_answer_attempts($question_data);
        
        if ($attempt_count <= 1) {
            return 'High'; // Answered immediately
        } elseif ($attempt_count <= 3) {
            return 'Medium'; // Some revision
        } else {
            return 'Low'; // Many changes
        }
    }
    
    // Keep all existing methods for backward compatibility
    public function get_attempt_info($attempt_id) {
        $sql = "
            SELECT 
                qa.id as attempt_id,
                qa.attempt,
                qa.state,
                qa.timestart,
                qa.timefinish,
                qa.sumgrades,
                qa.preview,
                q.id as quiz_id,
                q.name as quiz_name,
                q.intro,
                q.grade as max_grade,
                q.sumgrades as quiz_sumgrades
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE qa.id = :attempt_id
        ";
        
        return $this->db->get_record_sql($sql, ['attempt_id' => $attempt_id]);
    }


    /**
     * Clean tag names by removing PP suffixes
     */
    public function clean_tag_names($tag_combination) {
        if (empty($tag_combination) || $tag_combination === 'No Tags') {
            return $tag_combination;
        }
        
        // Split tags by separator
        $tags = explode(' ; ', $tag_combination);
        $cleaned_tags = [];

        // All PP suffix patterns to remove (case-insensitive)
        $pp_patterns = [
            ' – pp',    // em dash with space
            ' –– pp',   // double em dash with space
            ' -- pp',   // double hyphen with space
            ' - pp',    // single hyphen with space
            ' –pp',     // em dash without space before pp
            ' -pp',     // hyphen without space before pp
        ];

        foreach ($tags as $tag) {
            $cleaned_tag = trim($tag);
            // Remove all PP suffix patterns (case-insensitive)
            foreach ($pp_patterns as $pattern) {
                $cleaned_tag = str_ireplace($pattern, '', $cleaned_tag);
            }
            if (!empty($cleaned_tag)) {
                $cleaned_tags[] = $cleaned_tag;
            }
        }
        
        return empty($cleaned_tags) ? 'No Tags' : implode(' ; ', $cleaned_tags);
    }

    public function group_by_tag_combinations($questions) {
        $tag_groups = [];
        
        foreach ($questions as $question) {
            $tag_combination = $question->tag_combination ?? 'No Tags';
            
            if (!isset($tag_groups[$tag_combination])) {
                $tag_groups[$tag_combination] = [
                    'questions' => [],
                    'total_questions' => 0,
                    'correct_answers' => 0,
                    'wrong_answers' => 0,
                    'percentage' => 0
                ];
            }
            
            $tag_groups[$tag_combination]['questions'][] = $question;
            $tag_groups[$tag_combination]['total_questions']++;
            
            // Determine if answer is correct based on fraction
            if ($question->fraction >= 0.5) {
                $tag_groups[$tag_combination]['correct_answers']++;
            } else {
                $tag_groups[$tag_combination]['wrong_answers']++;
            }
        }
        
        // Calculate percentages for each tag group
        foreach ($tag_groups as $tag_combination => &$data) {
            if ($data['total_questions'] > 0) {
                $data['percentage'] = round(
                    ($data['correct_answers'] / $data['total_questions']) * 100, 
                    1
                );
            }
        }
        
        // Sort by tag combination name for consistent display
        ksort($tag_groups);
        
        return $tag_groups;
    }

    public function calculate_overall_stats_from_tag_groups($tag_groups) {
        $total_questions = 0;
        $total_correct = 0;
        $total_wrong = 0;
        
        foreach ($tag_groups as $tag_data) {
            $total_questions += $tag_data['total_questions'];
            $total_correct += $tag_data['correct_answers'];
            $total_wrong += $tag_data['wrong_answers'];
        }
        
        $percentage = $total_questions > 0 ? round(($total_correct / $total_questions) * 100, 1) : 0;
        
        return [
            'total_questions' => $total_questions,
            'total_correct' => $total_correct,
            'total_wrong' => $total_wrong,
            'percentage' => $percentage
        ];
    }
}
