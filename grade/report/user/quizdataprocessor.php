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
     * Get comprehensive question data for a quiz attempt
     * 
     * @param int $attempt_id Quiz attempt ID
     * @return array Processed question data grouped by question ID
     */
    public function get_attempt_questions($attempt_id) {
        // Get basic question info
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
                qatt.questionusageid as question_usage_id
            FROM {question_attempts} qatt
            JOIN {question_usages} qu ON qu.id = qatt.questionusageid
            JOIN {quiz_attempts} qa ON qa.uniqueid = qu.id
            JOIN {question} q ON q.id = qatt.questionid
            JOIN {question_versions} qv ON q.id = qv.questionid
            JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
            JOIN {question_categories} qc ON qbe.questioncategoryid = qc.id
            WHERE qa.id = :attempt_id
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
        }
        
        // Convert to array format expected by your code
        $result = [];
        foreach ($questions as $question) {
            $result[$question->questionid] = $question;
        }
        
        return $result;
    }
    
    /**
     * Process raw question results into structured data
     * 
     * @param array $results Raw database results
     * @return array Structured question data
     */
    private function process_question_results_with_all_steps($results) {
        $questions = [];
        
        foreach ($results as $row) {
            $question_id = $row->questionid;
            
            // Initialize question if not exists
            if (!isset($questions[$question_id])) {
                $questions[$question_id] = (object)[
                    'questionid' => $row->questionid,
                    'slot' => $row->slot,
                    'question_name' => $row->question_name,
                    'questiontext' => $row->questiontext,
                    'questiontextformat' => $row->questiontextformat,
                    'qtype' => $row->qtype,
                    'maxmark' => $row->maxmark,
                    'fraction' => null, // Will be set from the latest step
                    'score' => null,
                    'state' => null,
                    'category_name' => $row->category_name,
                    'category_info' => $row->category_info,
                    'contextid' => $row->contextid,
                    'parent_category' => $row->parent_category,
                    'defaultmark' => $row->defaultmark,
                    'question_attempt_id' => $row->question_attempt_id,
                    'step_id' => $row->step_id,
                    'question_usage_id' => $row->question_usage_id,
                    'student_answers' => [],
                    'step_data' => [],
                    'latest_sequence' => -1
                ];
            }
            
            // Update with latest step info (for fraction/score)
            if ($row->sequencenumber > $questions[$question_id]->latest_sequence) {
                $questions[$question_id]->latest_sequence = $row->sequencenumber;
                $questions[$question_id]->fraction = $row->fraction;
                $questions[$question_id]->score = $row->score;
                $questions[$question_id]->state = $row->state;
            }
            
            // Collect ALL step data from ALL steps
            if (!empty($row->stepdata_name) && !empty($row->stepdata_value)) {
                // Skip the -finish markers, we want actual answers
                if ($row->stepdata_name !== '-finish' && $row->stepdata_name !== '_order') {
                    $questions[$question_id]->student_answers[$row->stepdata_name] = $row->stepdata_value;
                    $questions[$question_id]->step_data[] = (object)[
                        'name' => $row->stepdata_name,
                        'value' => $row->stepdata_value,
                        'sequence' => $row->sequencenumber
                    ];
                }
            }
        }
        
        return $questions;
    }
    
    /**
     * Extract student's primary answer based on question type
     * 
     * @param object $question_data Processed question data
     * @return string Primary student answer
     */
    public function extract_primary_answer($question_data) {
        if (empty($question_data->student_answers)) {
            return null;
        }
        
        $answers = $question_data->student_answers;
        
        // Check if this uses the 'answer' format (choice index stored directly)
        if (isset($answers['answer'])) {
            return (int)$answers['answer']; // This is a choice index, not answer ID
        }
        
        // Check if this uses the 'choice' format (choice0, choice1, etc.)
        foreach ($answers as $key => $value) {
            if (strpos($key, 'choice') === 0 && $value == '1') {
                // Extract the choice index (0, 1, 2, etc.)
                return (int)substr($key, 6);
            }
        }
        
        return null;
    }
    
    /**
     * Get correct answers for a question
     * 
     * @param int $question_id Question ID
     * @return array Array of correct answers
     */
    public function get_correct_answers($question_id) {
        $sql = "
            SELECT qa.id, qa.answer, qa.fraction, qa.answerformat, qa.feedback
            FROM {question_answers} qa
            WHERE qa.question = :question_id
            AND qa.fraction >= 0.99
            ORDER BY qa.fraction DESC, qa.id ASC
        ";
        
        return $this->db->get_records_sql($sql, ['question_id' => $question_id]);
    }
    
    /**
     * Group questions by category for reporting
     * 
     * @param array $questions Processed question data
     * @return array Questions grouped by category with statistics
     */
    public function group_by_category($questions) {
        $categories = [];
        
        foreach ($questions as $question) {
            $category = $question->category_name;
            
            // Initialize category if not exists
            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'name' => $category,
                    'total_questions' => 0,
                    'correct_answers' => 0,
                    'wrong_answers' => 0,
                    'percentage' => 0,
                    'questions' => []
                ];
            }
            
            // Add question to category
            $categories[$category]['questions'][] = $question;
            $categories[$category]['total_questions']++;
            
            // Check if answer is correct
            $is_correct = isset($question->fraction) && $question->fraction >= 0.99;
            if ($is_correct) {
                $categories[$category]['correct_answers']++;
            } else {
                $categories[$category]['wrong_answers']++;
            }
        }
        
        // Calculate percentages
        foreach ($categories as &$category) {
            if ($category['total_questions'] > 0) {
                $category['percentage'] = round(
                    ($category['correct_answers'] / $category['total_questions']) * 100, 
                    1
                );
            }
        }
        
        return $categories;
    }
    
    /**
     * Calculate overall attempt statistics
     * 
     * @param array $categories Category data with statistics
     * @return array Overall statistics
     */
    public function calculate_overall_stats($categories) {
        $total_questions = 0;
        $total_correct = 0;
        $total_wrong = 0;
        
        foreach ($categories as $category) {
            $total_questions += $category['total_questions'];
            $total_correct += $category['correct_answers'];
            $total_wrong += $category['wrong_answers'];
        }
        
        $overall_percentage = $total_questions > 0 ? 
            round(($total_correct / $total_questions) * 100, 1) : 0;
        
        return [
            'total_questions' => $total_questions,
            'total_correct' => $total_correct,
            'total_wrong' => $total_wrong,
            'percentage' => $overall_percentage
        ];
    }
    
    /**
     * Get quiz attempt information
     * 
     * @param int $attempt_id Attempt ID
     * @return object|false Attempt information
     */
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
     * Get comprehensive question data for a quiz attempt with tags
     * 
     * @param int $attempt_id Quiz attempt ID
     * @return array Processed question data with tags
     */
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
                GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ' - ') as tag_combination
            FROM {question_attempts} qatt
            JOIN {question_usages} qu ON qu.id = qatt.questionusageid
            JOIN {quiz_attempts} qa ON qa.uniqueid = qu.id
            JOIN {question} q ON q.id = qatt.questionid
            JOIN {question_versions} qv ON q.id = qv.questionid
            JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
            JOIN {question_categories} qc ON qbe.questioncategoryid = qc.id
            LEFT JOIN {tag_instance} ti ON ti.itemid = qatt.questionid AND ti.itemtype = 'question'
            LEFT JOIN {tag} t ON t.id = ti.tagid
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
            if (empty($question->tag_combination)) {
                $question->tag_combination = 'No Tags';
            }
        }
        
        // Convert to array for processing
        $result = [];
        foreach ($questions as $question) {
            $result[] = $question;
        }
        
        return $result;
    }

    // Add this method to your existing quiz_attempt_data_processor class
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
            if ($question->fraction >= 1.0) {
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

    // Add this method to your existing quiz_attempt_data_processor class
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