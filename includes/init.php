<?php
/**
 * Initialization functions for WPLMS CLEVERCOURSE MIGRATION
 * @author      VibeThemes
 * @category    Admin
 * @package     Initialization
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPLMS_CLEVERCOURSE_INIT{

    public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new WPLMS_CLEVERCOURSE_INIT();

        return self::$instance;
    }

    private function __construct(){
    	$theme = wp_get_theme(); // gets the current theme
        if ('Clever Course' == $theme->name || 'Clever Course' == $theme->parent_theme){
            add_action( 'admin_notices',array($this,'migration_notice' ));
            add_action('wp_ajax_migration_cc_courses',array($this,'migration_cc_courses'));

            add_action('wp_ajax_migration_cc_course_to_wplms',array($this,'migration_cc_course_to_wplms'));
        }
    }

    function migration_notice(){
        $this->migration_status = get_option('wplms_clevercourse_migration');  
        
        if(empty($this->migration_status)){
            ?>
            <div id="migration_clevercourse_courses" class="error notice ">
               <p id="cc_message"><?php printf( __('Migrate clevercourse coruses to WPLMS %s Begin Migration Now %s', 'wplms-cc' ),'<a id="begin_wplms_clevercourse_migration" class="button primary">','</a>'); ?>
                
               </p>
           <?php wp_nonce_field('security','security'); ?>
                <style>.wplms_cc_progress .bar{-webkit-transition: width 0.5s ease-in-out;
    -moz-transition: width 1s ease-in-out;-o-transition: width 1s ease-in-out;transition: width 1s ease-in-out;}</style>
                <script>
                    jQuery(document).ready(function($){
                        $('#begin_wplms_clevercourse_migration').on('click',function(){
                            $.ajax({
                                type: "POST",
                                dataType: 'json',
                                url: ajaxurl,
                                data: { action: 'migration_cc_courses', 
                                          security: $('#security').val(),
                                        },
                                cache: false,
                                success: function (json) {
                                    $('#migration_clevercourse_courses').append('<div class="wplms_cc_progress" style="width:100%;margin-bottom:20px;height:10px;background:#fafafa;border-radius:10px;overflow:hidden;"><div class="bar" style="padding:0 1px;background:#37cc0f;height:100%;width:0;"></div></div>');

                                    var x = 0;
                                    var width = 100*1/json.length;
                                    var number = width;
                                    var loopArray = function(arr) {
                                        wpcc_ajaxcall(arr[x],function(){
                                            x++;
                                            if(x < arr.length) {
                                                loopArray(arr);   
                                            }
                                        }); 
                                    }
                                    
                                    // start 'loop'
                                    loopArray(json);

                                    function wpcc_ajaxcall(obj,callback) {
                                        
                                        $.ajax({
                                            type: "POST",
                                            dataType: 'json',
                                            url: ajaxurl,
                                            data: {
                                                action:'migration_cc_course_to_wplms', 
                                                security: $('#security').val(),
                                                id:obj.id,
                                            },
                                            cache: false,
                                            success: function (html) {
                                                number = number + width;
                                                $('.wplms_cc_progress .bar').css('width',number+'%');
                                                if(number >= 100){
                                                    $('#migration_clevercourse_courses').removeClass('error');
                                                    $('#migration_clevercourse_courses').addClass('updated');
                                                    $('#cc_message').html('<strong>'+x+' '+'<?php _e('Courses successfully migrated from Clevercourse to WPLMS','wplms-cc'); ?>'+'</strong>');
                                                }
                                            }
                                        });
                                        // do callback when ready
                                        callback();
                                    } 
                                }
                            });
                        });
                    });
                </script>
            </div>
            <?php
        }
    }

    function migration_cc_courses(){
        if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'security') || !is_user_logged_in()){
            _e('Security check Failed. Contact Administrator.','vibe');
            die();
        }

        global $wpdb;
        $courses = $wpdb->get_results("SELECT id,post_title FROM {$wpdb->posts} where post_type='course'");
        $json=array();
        foreach($courses as $course){
            $json[]=array('id'=>$course->id,'title'=>$course->post_title);
        }

        update_option('wplms_clevercourse_migration',1);

        $this->migrate_quiz_settings();

        print_r(json_encode($json));
        die();
    }

    function migration_cc_course_to_wplms(){
        if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'security') || !is_user_logged_in()){
            _e('Security check Failed. Contact Administrator.','vibe');
            die();
        }

        global $wpdb;
        $this->migrate_course_settings($_POST['id']);
        $this->build_course_curriculum($_POST['id']);
    }

    function migrate_course_settings($course_id){
        $settings = get_post_meta($course_id,'gdlr-lms-course-settings',true);
        if(!empty($settings)){
            if(!empty($settings['prerequisite-course'])){
                update_post_meta($course_id,'vibe_pre_course',$settings['prerequisite-course']);
            }

            if(!empty($settings['online-course']) && $settings['online-course'] == 'enable'){
                update_post_meta($course_id,'vibe_course_offline','S');
            }else{
                update_post_meta($course_id,'vibe_course_offline','H');
            }

            if(!empty($settings['start-date'])){
                update_post_meta($course_id,'vibe_start_date',$settings['start-date']);
            }

            if(!empty($settings['course-time'])){
                update_post_meta($course_id,'vibe_duration',$settings['course-time']);
            }else{
                update_post_meta($course_id,'vibe_duration',9999);
            }

            if(!empty($settings['max-seat'])){
                update_post_meta($course_id,'vibe_max_students',$settings['max-seat']);
            }

            if(!empty($settings['enable-badge']) && $settings['enable-badge'] == 'enable'){
                update_post_meta($course_id,'vibe_badge','S');

                if(!empty($settings['badge-percent'])){
                    update_post_meta($course_id,'vibe_course_badge_percentage',$settings['badge-percent']);
                }
                if(!empty($settings['badge-title'])){
                    update_post_meta($course_id,'vibe_course_badge_title',$settings['badge-title']);
                }
                if(!empty($settings['badge-file'])){
                    
                }
            }

            if(!empty($settings['enable-certificate']) && $settings['enable-certificate'] == 'enable'){
                update_post_meta($course_id,'vibe_course_certificate','S');

                if(!empty($settings['certificate-percent'])){
                    update_post_meta($course_id,'vibe_course_passing_percentage',$settings['certificate-percent']);
                }
                if(!empty($settings['certificate-template'])){
                    update_post_meta($course_id,'vibe_certificate_template',$settings['certificate-template']);
                }            
            }
        }
    }

    function build_course_curriculum($course_id){
        global $post;
        $author_id = $post->post_author;
        $this->curriculum = array();

        $content_settings = get_post_meta($course_id,'gdlr-lms-content-settings',true);
        if(!empty($content_settings)){
            if(function_exists('gdlr_fw2_decode_preventslashes')){
                $data_val = gdlr_fw2_decode_preventslashes($content_settings);
                $data_array = json_decode($data_val, true);
                $data_array = (array) $data_array;
                foreach($data_array as $data){
                    if(!empty($data['section-name'])){
                        $this->curriculum[] = $data['section-name'];
                    }

                    if(!empty($data['lecture-section'])){
                        $lectures = $data['lecture-section'];
                        $units = json_decode($lectures, true);
                        foreach ($units as $unit) {
                            $insert_unit = array(
                                    'post_title' => $unit['lecture-name'],
                                    'post_content' => $unit['lecture-content'],
                                    'post_author' => $author_id,
                                    'post_status' => 'publish',
                                    'comment_status' => 'open',
                                    'post_type' => 'unit'
                                );

                            $unit_id = wp_insert_post( $insert_unit, true);
                            $this->curriculum[] = $unit_id;
                        }
                    }

                    if(!empty($data['section-quiz'])){
                        $this->curriculum[] = $data['section-quiz'];
                    }
                }
            }
        }

        update_post_meta($course_id,'vibe_course_curriculum',$this->curriculum);
    }

    function migrate_quiz_settings(){
        global $wpdb;
        $quizzes = $wpdb->get_results("SELECT id FROM {$wpdb->posts} where post_type='quiz'");
        if(!empty($quizzes)){
            foreach($quizzes as $quiz){
                $quiz_settings = get_post_meta($quiz->id,'gdlr-lms-quiz-settings',true);
                if(!empty($quiz_settings)){
                    if(!empty($quiz_settings['retake-times'])){
                        update_post_meta($quiz->id,'vibe_quiz_retakes',$quiz_settings['retake-times']);
                    }
                }
                $this->migrate_quiz_questions($quiz->id);
            }
        }
    }

    function migrate_quiz_questions($quiz_id){
        global $post;
        $author_id = $post->post_author;
        $quiz_questions = array('ques'=>array(),'marks'=>array());
        $duration = 0;

        $content_settings = get_post_meta($quiz_id,'gdlr-lms-content-settings',true);
        if(!empty($content_settings)){
            if(function_exists('gdlr_fw2_decode_preventslashes')){
                $data_val = gdlr_fw2_decode_preventslashes($content_settings);
                $data_array = json_decode($data_val, true);
                $data_array = (array) $data_array;

                foreach($data_array as $data){
                    if(!empty($data['section-name'])){
                        $section_name = $data['section-name'];
                    }

                    if(!empty($data['question-type'])){
                        $question_type = $data['question-type'];
                        if(!empty($data['question'])){
                            $total_questions = $data['question'];
                            $questions = json_decode($total_questions, true);
                            foreach($questions as $question){
                                $title = substr($question['question'],0,50);
                                $title = $section_name.$title;
                                $insert_question = array(
                                        'post_title' => $title,
                                        'post_content' => $question['question'],
                                        'post_author' => $author_id,
                                        'post_status' => 'publish',
                                        'comment_status' => 'open',
                                        'post_type' => 'question'
                                    );

                                $question_id = wp_insert_post( $insert_question, true);
                                $quiz_questions['ques'][] = $question_id;
                                $quiz_questions['marks'][] = $question['score'];

                                switch ($question_type) {
                                    case 'single':
                                        if(!empty($question['quiz-choice'])){
                                            $options = $question['quiz-choice'];
                                            update_post_meta($question_id,'vibe_question_options',$options);
                                        }
                                        if(!empty($question['quiz-answer'])){
                                            update_post_meta($question_id,'vibe_question_answer',$question['quiz-answer']);
                                        }
                                        break;

                                    case 'multiple':
                                        if(!empty($question['quiz-choice'])){
                                            $options = $question['quiz-choice'];
                                            update_post_meta($question_id,'vibe_question_options',$options);
                                        }
                                        if(!empty($question['quiz-answer'])){
                                            update_post_meta($question_id,'vibe_question_answer',$question['quiz-answer']);
                                        }
                                        break;

                                    case 'small':
                                        if(!empty($question['quiz-answer'])){
                                            update_post_meta($question_id,'vibe_question_answer',$question['quiz-answer']);
                                        }
                                        $question_type = 'smalltext';
                                        break;

                                    case 'large':
                                        if(!empty($question['quiz-answer'])){
                                            update_post_meta($question_id,'vibe_question_answer',$question['quiz-answer']);
                                        }
                                        $question_type = 'largetext';
                                        break;
                                }
                                    
                                update_post_meta($question_id,'vibe_question_type',$question_type);
                            }
                        }
                    }

                    if(!empty($data['time-period']){
                        $duration = $duration + $data['time-period'];
                    }
                }
            }
        }

        if($duration > 0){
            update_post_meta($quiz_id,'vibe_duration',$duration);
        }else{
            update_post_meta($quiz_id,'vibe_duration',9999);
        }
        update_post_meta($quiz_id,'vibe_quiz_duration_parameter',60);
        update_post_meta($quiz_id,'vibe_quiz_questions',$quiz_questions);
    }
}

WPLMS_CLEVERCOURSE_INIT::init();