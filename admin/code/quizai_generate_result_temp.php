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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answer_type = $_POST['answer_type'];
    $text = $_POST['text'];
    $selected_module_id = $_POST['subject_module_id'];
    $selected_subject_id = $_POST['subject'];
    // $difficulty = $_POST['difficulty'];

    $module_name = '';
    foreach ($modules as $module) {
        if ($module['module_id'] == $selected_module_id) {
            $module_name = $module['module_name'];
            break;
        }
    }

    $subject_name = '';
    foreach ($subjects as $subject) {
        if ($subject['subject_id'] == $selected_subject_id) {
            $subject_name = $subject['subject_name'];
            break;
        }
    }

    $subject_description = '';
    foreach ($subjects as $subject) {
        if ($subject['subject_id'] == $selected_subject_id) {
            $subject_description = $subject['subject_description'];
            break;
        }
    }

    echo "<p>Answer Type: " . htmlspecialchars($answer_type, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Text: " . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Module: " . htmlspecialchars($module_name, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Subject: " . htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Subject: " . htmlspecialchars($subject_description, ENT_QUOTES, 'UTF-8') . "</p>";
    // echo "<p>Difficulty: " . htmlspecialchars($difficulty, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Language: " . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . "</p>";

    // Define the dummy data using POST data
    // if  ($difficulty == 'hard') {
    //     $dummyData = [
    //         'module_name' => $module_name,
    //         'subject_name' => $subject_name,
    //         'subject_description' => 'This is the description of the exam',
    //         'questions' => [
    //             [
    //                 'question' => 'A 67-year-old woman with congenital bicuspid aortic valve is admitted to the hospital because of a 2-day history of fever and chills. Current medication is lisinopril. Temperature is 38.0°C (100.4°F), pulse is 90/min, respirations are 20/min, and blood pressure is 110/70 mm Hg. Cardiac examination shows a grade 3/6 systolic murmur that is best heard over the second right intercostal space. Blood culture grows viridans streptococci susceptible to penicillin. In addition to penicillin, an antibiotic synergistic to penicillin is administered that may help shorten the duration of this patient‘s drug treatment. Which of the following is the most likely mechanism of action of this additional antibiotic on bacteria?',
    //                 'answers' => [
    //                     ['isright' => 'false', 'description' => 'Binding to DNA-dependent RNA polymerase', 'explanation' => ''],
    //                     ['isright' => 'true', 'description' => 'Binding to the 30S ribosomal protein', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Competition with p-aminobenzoic acid', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Inhibition of dihydrofolate reductase', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Inhibition of DNA gyrase', 'explanation' => '']
    //                 ]
    //             ],
    //             [
    //                 'question' => 'A 12-year-old girl is brought to the physician because of a 2-month history of intermittent yellowing of the eyes and skin. Physical examination shows no abnormalities except for jaundice. Her serum total bilirubin concentration is 3 mg/dL, with a direct component of 1 mg/dL. Serum studies show a haptoglobin concentration and AST and ALT activities that are within the reference ranges. There is no evidence of injury or exposure to toxins. Which of the following additional findings is most likely in this patient?',
    //                 'answers' => [
    //                     ['isright' => 'true', 'description' => 'Decreased activity of UDP glucuronosyltransferase', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Gallstones', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Increased hemolysis', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Increased serum alkaline phosphatase activity', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Ineffective erythropoiesis', 'explanation' => '']
    //                 ]
    //             ],
    //             [
    //                 'question' => 'A randomized controlled trial is conducted to assess the risk for development of gastrointestinal adverse effects using azithromycin compared with erythromycin in the treatment of pertussis in children. Of the 100 children with pertussis enrolled, 50 receive azithromycin, and 50 receive erythromycin. Results show vomiting among 5 patients in the azithromycin group, compared with 15 patients in the erythromycin group. Which of the following best represents the absolute risk reduction for vomiting among patients in the azithromycin group?',
    //                 'answers' => [
    //                     ['isright' => 'false', 'description' => '0.1', 'explanation' => ''],
    //                     ['isright' => 'true', 'description' => '0.2', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => '0.33', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => '0.67', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => '0.', 'explanation' => '']
    //                 ]
    //             ],
    //             [
    //                 'question' => 'A 30-year-old woman, gravida 2, para 0, aborta 1, at 28 weeks\' gestation comes to the office for a prenatal visit. She has had one previous pregnancy resulting in a spontaneous abortion at 12 weeks\' gestation. Today, her vital signs are within normal limits. Physical examination shows a uterus consistent in size with a 28-week gestation. Fetal ultrasonography shows a male fetus with no abnormalities. Her blood group is O, Rh-negative. The father\'s blood group is B, Rh-positive. The physician recommends administration of Rho(D) immune globulin to the patient. This treatment is most likely to prevent which of the following in this mother?',
    //                 'answers' => [
    //                     ['isright' => 'false', 'description' => 'Development of natural killer cells', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Development of polycythemia', 'explanation' => ''],
    //                     ['isright' => 'true', 'description' => 'Formation of antibodies to RhD', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Generation of IgM antibodies from fixing complement in the fetus', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Immunosuppression caused by RhD on erythrocytes from the fetus', 'explanation' => '']
    //                 ]
    //             ]
    //         ],
    //         'difficulty' => $difficulty
    //     ];
    // }
    // elseif ($difficulty == 'medium'){
    //     $dummyData = [
    //         'module_name' => $module_name,
    //         'subject_name' => $subject_name,
    //         'subject_description' => 'This is the description of the exam',
    //         'questions' => [
    //             [
    //                 'question' => 'The heart lies in the ________ cavity.',
    //                 'answers' => [
    //                     ['isright' => 'false', 'description' => 'dorsal mediastinum', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'ventral pleural', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'dorsal pericardial', 'explanation' => ''],
    //                     ['isright' => 'true', 'description' => 'ventral pericardial', 'explanation' => 'The heart is located in the ventral (front) part of the pericardial cavity, which houses the heart.']
    //                 ]
    //             ],
    //             [
    //                 'question' => 'Choose the anatomical topic and definition that is **not** correctly matched.',
    //                 'answers' => [
    //                     ['isright' => 'false', 'description' => 'Gross anatomy: study of structures visible to the eye.', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'Microscopic anatomy: study of structures too small to be seen by the naked eye.', 'explanation' => ''],
    //                     ['isright' => 'true', 'description' => 'Developmental anatomy: study of the changes in an individual from birth through old age.', 'explanation' => 'Developmental anatomy actually studies the changes in an individual from conception to adulthood, not just from birth through old age.'],
    //                     ['isright' => 'false', 'description' => 'Embryology: study of the changes in an individual from conception to birth.', 'explanation' => '']
    //                 ]
    //             ],
    //             [
    //                 'question' => 'A structure that is composed of two or more tissues would be ________.',
    //                 'answers' => [
    //                     ['isright' => 'false', 'description' => 'a complex tissue', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'an organ system', 'explanation' => ''],
    //                     ['isright' => 'true', 'description' => 'an organ', 'explanation' => 'An organ is composed of multiple types of tissues that work together to perform specific functions.'],
    //                     ['isright' => 'false', 'description' => 'a complex cell', 'explanation' => '']
    //                 ]
    //             ],
    //             [
    //                 'question' => 'The cavities between bones are called ________ cavities.',
    //                 'answers' => [
    //                     ['isright' => 'false', 'description' => 'parietal', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'pericardial', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'vertebral', 'explanation' => ''],
    //                     ['isright' => 'true', 'description' => 'synovial', 'explanation' => 'Synovial cavities are spaces between bones that are filled with synovial fluid, which helps reduce friction during movement.']
    //                 ]
    //             ],
    //             [
    //                 'question' => 'Which of the following would not be functional characteristics of life?',
    //                 'answers' => [
    //                     ['isright' => 'false', 'description' => 'movement', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'responsiveness to external stimuli', 'explanation' => ''],
    //                     ['isright' => 'false', 'description' => 'maintenance of boundaries', 'explanation' => ''],
    //                     ['isright' => 'true', 'description' => 'decay', 'explanation' => 'Decay is not a functional characteristic of life. Functional characteristics of life include movement, responsiveness to external stimuli, and maintenance of boundaries.']
    //                 ]
    //             ]
    //         ],
    //         'difficulty' => $difficulty
    //     ];
    // } elseif ($difficulty == 'easy') {
        $dummyData = [
            'module_name' => $module_name,
            'subject_name' => $subject_name,
            'subject_description' => $subject_description,
            'questions' => [
                [
                    'question' => 'Which of the following is the primary function of the respiratory system?',
                    'answers' => [
                        ['isright' => 'true', 'description' => 'Gas exchange', 'explanation' => 'The primary function of the respiratory system is to facilitate the exchange of oxygen and carbon dioxide between the body and the environment.'],
                        ['isright' => 'false', 'description' => 'Nutrient absorption', 'explanation' => ''],
                        ['isright' => 'false', 'description' => 'Waste elimination', 'explanation' => ''],
                        ['isright' => 'false', 'description' => 'Temperature regulation', 'explanation' => '']
                    ]
                ],
                [
                    'question' => 'Which of the following is a component of the central nervous system?',
                    'answers' => [
                        ['isright' => 'true', 'description' => 'Brain', 'explanation' => 'The brain is a major component of the central nervous system, responsible for processing information, controlling body functions, and coordinating movement.'],
                        ['isright' => 'false', 'description' => 'Spinal cord', 'explanation' => ''],
                        ['isright' => 'false', 'description' => 'Peripheral nerves', 'explanation' => ''],
                        ['isright' => 'false', 'description' => 'Autonomic ganglia', 'explanation' => '']
                    ]
                ],
                [
                    'question' => 'Which of the following is an example of an involuntary muscle?',
                    'answers' => [
                        ['isright' => 'false', 'description' => 'Biceps brachii', 'explanation' => ''],
                        ['isright' => 'false', 'description' => 'Quadriceps femoris', 'explanation' => ''],
                        ['isright' => 'true', 'description' => 'Cardiac muscle', 'explanation' => 'Cardiac muscle is an involuntary muscle found in the heart, responsible for pumping blood throughout the body.'],
                        ['isright' => 'false', 'description' => 'Hamstring muscles', 'explanation' => '']
                    ]
                ],
                [
                    'question' => 'Which of the following is a function of the skeletal system?',
                    'answers' => [
                        ['isright' => 'true', 'description' => 'Support', 'explanation' => 'The skeletal system provides structural support for the body, anchoring muscles and providing a framework for movement.'],
                        ['isright' => 'false', 'description' => 'Digestion', 'explanation' => ''],
                        ['isright' => 'false', 'description' => 'Hormone production', 'explanation' => ''],
                        ['isright' => 'false', 'description' => 'Blood circulation', 'explanation' => '']
                    ]
                ],
                [
                    'question' => 'Which of the following is a component of the integumentary system?',
                    'answers' => [
                        ['isright' => 'false', 'description' => 'Liver', 'explanation' => ''],
                        ['isright' => 'false', 'description' => 'Pancreas', 'explanation' => ''],
                        ['isright' => 'true', 'description' => 'Skin', 'explanation' => 'The skin is the largest organ of the integumentary system, providing protection, sensation, and regulation of body temperature.'],
                        ['isright' => 'false', 'description' => 'Spleen', 'explanation' => '']
                    ]
                ]
            ],
        ];
    // }

    // echo '<div class="center-card"><div class="card" style="background-color:#F6F6F6"><div class="card-body">';
    // echo '<pre>' . json_encode($dummyData, JSON_PRETTY_PRINT) . '</pre>';
    // echo '</div></div></div>';

    
    // Display the questions with checkboxes and difficulty adjustment
    echo '<style>
.container {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
}
.card {
    background-color: #F6F6F6;
    padding: 20px;
    border: 1px solid #000000;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    width: 30%;
    margin-bottom: 20px;
    box-sizing: border-box;
}
</style>';

echo '<form method="POST" action="process.php">';
echo '<div class="container">';
foreach ($dummyData['questions'] as $qIndex => $question) {
    echo '<div class="card">';
    echo '<label for="question' . $qIndex . '"><strong>' . htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') . '</strong></label>';
    echo '<div style="padding-left: 20px;">';
    foreach ($question['answers'] as $aIndex => $answer) {
        $answerText = htmlspecialchars($answer['description'], ENT_QUOTES, 'UTF-8');
        $isCorrect = htmlspecialchars($answer['isright'], ENT_QUOTES, 'UTF-8');
        $label = chr(97 + $aIndex); // 'a' is ASCII 97
        if ($isCorrect == 'true') {
            echo '<p><b>' . $label . '. ' . $answerText . '</b> (True)</p>';
        } else {
            echo '<p>' . $label . '. ' . $answerText . '</p>';
        }
    }
    // Add a difficulty selector for each question
    echo '<label for="difficulty' . $qIndex . '">Difficulty: </label>';
    echo '<select id="difficulty' . $qIndex . '" name="difficulty[' . $qIndex . ']">';
    for ($i = 1; $i <= 10; $i++) {
        echo '<option value="' . $i . '">' . $i . '</option>';
    }
    echo '</select>';
    echo '<input type="checkbox" id="question' . $qIndex . '" name="selected_questions[]" value="' . $qIndex . '" style="margin-left: 10px;">';
    echo '<label for="question' . $qIndex . '" style="margin-left: 5px;">Import this question</label>';
    
    // Include hidden inputs for each question and answer details
    echo '<input type="hidden" name="questions[' . $qIndex . '][question]" value="' . htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') . '">';
    foreach ($question['answers'] as $aIndex => $answer) {
        echo '<input type="hidden" name="questions[' . $qIndex . '][answers][' . $aIndex . '][description]" value="' . htmlspecialchars($answer['description'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="questions[' . $qIndex . '][answers][' . $aIndex . '][isright]" value="' . htmlspecialchars($answer['isright'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="questions[' . $qIndex . '][answers][' . $aIndex . '][explanation]" value="' . htmlspecialchars($answer['explanation'], ENT_QUOTES, 'UTF-8') . '">';
    }
    echo '</div>';
    echo '</div>';
}
echo '</div>'; // Close container
echo '<input type="hidden" name="answer_type" value="' . htmlspecialchars($answer_type, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="text" value="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="module" value="' . htmlspecialchars($module_name, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="subject" value="' . htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="subject_desc" value="' . htmlspecialchars($subject_description, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="submit" value="Submit">';
echo '</form>';
}
require_once('../code/tce_page_footer.php');
?>
