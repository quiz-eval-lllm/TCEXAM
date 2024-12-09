<?php
// Include the configuration file
require_once('../config/tce_config.php');

// Define the required page level (assuming K_AUTH_ADMIN_IMPORT is a constant)
$pagelevel = K_AUTH_ADMIN_IMPORT;

// Include the authorization file
require_once('../../shared/code/tce_authorization.php');

// Set the page title
$thispage_title = 'Question Generation Result';

// Include necessary files
require_once('../code/tce_page_header.php');
require_once('../../shared/code/tce_functions_form.php');
require_once('../../shared/code/tce_functions_tcecode.php');
require_once('../../shared/code/tce_functions_auth_sql.php');

// Fetch modules from the database
$sql = F_select_modules_sql();
$r = F_db_query($sql, $db);
$modules = [];
while ($m = F_db_fetch_array($r)) {
    $modules[] = $m;
}

// Initialize subject_module_id to avoid undefined variable notice
$subject_module_id = isset($_POST['subject_module_id']) ? $_POST['subject_module_id'] : 0;

// Fetch subjects from the database based on selected module
$sql = F_select_subjects_sql('subject_module_id=' . $subject_module_id);
$r = F_db_query($sql, $db);
$subjects = [];
while ($m = F_db_fetch_array($r)) {
    $subjects[] = $m;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_questions'])) {
    $answer_type = $_POST['answer_type'];
    $text = $_POST['text'];
    $selected_module_id = $_POST['module_id'];
    $selected_subject_id = $_POST['subject_id'];
    $selected_module_name = $_POST['module'];
    $selected_subject_name = $_POST['subject'];
    $selected_subject_desc = $_POST['subject_desc'];
    $difficulties = $_POST['difficulty'];
    $questions = $_POST['questions'];

    // Get the selected questions from the form
    $selectedQuestions = $_POST['selected_questions'];

    // Create a new DOMDocument
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;


    // Create the root element
    $root = $doc->createElement('tcexamquestions');
    $root->setAttribute('version', '13.1.1');
    $doc->appendChild($root);

    // Create the header element
    $header = $doc->createElement('header');
    $header->setAttribute('lang', 'en');
    $header->setAttribute('date', date('Y-m-d H:i:s'));
    $root->appendChild($header);

    // Create the body element
    $body = $doc->createElement('body');
    $root->appendChild($body);

    // Create the module element
    $module = $doc->createElement('module');
    $body->appendChild($module);

    // Create and append the module name element
    $moduleName = $doc->createElement('name', htmlspecialchars($selected_module_name, ENT_QUOTES, 'UTF-8'));
    $module->appendChild($moduleName);

    // Create and append the enabled element for module
    $moduleEnabled = $doc->createElement('enabled', 'true');
    $module->appendChild($moduleEnabled);

    // Create the subject element
    $subject = $doc->createElement('subject');
    $module->appendChild($subject);

    // Create and append the subject name element
    $subjectName = $doc->createElement('name', htmlspecialchars($selected_subject_name, ENT_QUOTES, 'UTF-8'));
    $subject->appendChild($subjectName);

    // Create and append the description element for subject
    $subjectDescription = $doc->createElement('description', htmlspecialchars($selected_subject_desc, ENT_QUOTES, 'UTF-8'));
    $subject->appendChild($subjectDescription);

    // Create and append the enabled element for subject
    $subjectEnabled = $doc->createElement('enabled', 'true');
    $subject->appendChild($subjectEnabled);


    // Iterate over the selected questions
    foreach ($selectedQuestions as $qIndex) {
        $question = $questions[$qIndex];
        $difficulty = $difficulties[$qIndex];

        // Create the question element
        $questionElement = $doc->createElement('question');
        $subject->appendChild($questionElement);

        // Create and append the enabled element for question
        $questionEnabled = $doc->createElement('enabled', 'true');
        $questionElement->appendChild($questionEnabled);

        // Create and append the type element for question
        $questionType = $doc->createElement('type', htmlspecialchars($answer_type, ENT_QUOTES, 'UTF-8'));
        $questionElement->appendChild($questionType);

        // Create and append the difficulty element for question
        $questionDifficulty = $doc->createElement('difficulty', htmlspecialchars($difficulty, ENT_QUOTES, 'UTF-8'));
        $questionElement->appendChild($questionDifficulty);

        // Create and append the position element for question
        $position = isset($question['position']) ? $question['position'] : '';
        $questionPosition = $doc->createElement('position', htmlspecialchars($position, ENT_QUOTES, 'UTF-8'));
        $questionElement->appendChild($questionPosition);

        // Add question_spring_id if it exists
        $questionSpringIdElement = $doc->createElement('question_spring_id', htmlspecialchars($question['question_spring_id'], ENT_QUOTES, 'UTF-8'));
        $questionElement->appendChild($questionSpringIdElement);

        // Create and append the timer element for question
        $timer = isset($question['timer']) ? $question['timer'] : 0;
        $questionTimer = $doc->createElement('timer', htmlspecialchars($timer, ENT_QUOTES, 'UTF-8'));
        $questionElement->appendChild($questionTimer);

        // Create and append the description element for question
        $questionDescription = $doc->createElement('description', htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8'));
        $questionElement->appendChild($questionDescription);

        // Create and append the explanation element for question
        $explanation = isset($question['explanation']) ? $question['explanation'] : '';
        $questionExplanation = $doc->createElement('explanation', htmlspecialchars($explanation, ENT_QUOTES, 'UTF-8'));
        $questionElement->appendChild($questionExplanation);


        // Check if answers exist and iterate over them
        if (isset($question['answers']) && is_array($question['answers'])) {
            foreach ($question['answers'] as $answer) {
                // Create the answer element
                $answerElement = $doc->createElement('answer');
                $questionElement->appendChild($answerElement);

                // Create and append the enabled element for answer
                $answerEnabled = $doc->createElement('enabled', 'true');
                $answerElement->appendChild($answerEnabled);

                // Check if 'isright' and 'description' keys exist
                if (isset($answer['isright']) && isset($answer['description'])) {
                    // Create and append the isright element for answer
                    $answerIsRight = $doc->createElement('isright', htmlspecialchars($answer['isright'], ENT_QUOTES, 'UTF-8'));
                    $answerElement->appendChild($answerIsRight);

                    // Create and append the description element for answer
                    $answerDescription = $doc->createElement('description', htmlspecialchars($answer['description'], ENT_QUOTES, 'UTF-8'));
                    $answerElement->appendChild($answerDescription);
                }

                // Check if 'position' key exists
                if (isset($answer['position'])) {
                    // Create and append the position element for answer
                    $answerPosition = $doc->createElement('position', htmlspecialchars($answer['position'], ENT_QUOTES, 'UTF-8'));
                    $answerElement->appendChild($answerPosition);
                }

                // Check if 'explanation' key exists
                if (isset($answer['explanation'])) {
                    // Create and append the explanation element for answer
                    $answerExplanation = $doc->createElement('explanation', htmlspecialchars($answer['explanation'], ENT_QUOTES, 'UTF-8'));
                    $answerElement->appendChild($answerExplanation);
                }
            }
        }
    }

    // echo '<pre>';
    // print_r($questions);
    // echo '</pre>';


    // Output the XML string
    $xmlString = $doc->saveXML();


    $xmlString = mb_convert_encoding($xmlString, 'UTF-8', 'UTF-8');
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->loadXML($xmlString);

    // echo '<pre>' . htmlspecialchars($xmlString, ENT_QUOTES, 'UTF-8') . '</pre>';


    // Get the formatted XML string
    // $formattedXml = $doc->saveXML();

    // Display the formatted XML content
    // echo '<pre>' . htmlspecialchars($formattedXml) . '</pre>';


    require_once('../code/tce_class_import_xml.php');

    // Create an instance of XMLQuestionImporter and pass the XML file path
    $xmlImporter = new XMLQuestionImporter($xmlString);


    if ($xmlImporter) {
        F_print_error('MESSAGE', $l['m_importing_complete']);
        echo '<br/>';
        echo '<p>To manage the generated questions, go to "Question Management"</p>';
        echo '<a href="tce_edit_question.php?subject_module_id=' . $selected_module_id . '&amp;question_subject_id=' . $selected_subject_id . '" title="' . $l['t_questions_editor'] . '" class="xmlbutton">' . $l['t_questions_editor'] . ' &gt;</a>';
        echo '<p>To make test with the generated questions, go to click the "Make Test"</p>';
        echo '<a href="tce_edit_test.php" title="Make test" class="xmlbutton"> Make Test &gt;</a>';
    }
} else {
    echo 'No questions were selected.';
}

require_once('../code/tce_page_footer.php');
