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

// Fetch the active user's ID from the session or authentication mechanism
if (isset($_SESSION['session_user_id'])) {
    $active_user_id = $_SESSION['session_user_id'];

    // Query to fetch user_spring_id based on active_user_id
    $sql = 'SELECT user_spring_id FROM ' . K_TABLE_USERS . ' WHERE user_id = ' . (int)$active_user_id . ' LIMIT 1';
    $result = F_db_query($sql, $db);
    if ($result) {
        $user_data = F_db_fetch_array($result);
        if ($user_data) {
            $user_spring_id = $user_data['user_spring_id'];
        } else {
            $user_spring_id = null;
        }
    } else {
        F_display_db_error();
        exit;
    }
} else {
    echo "<p>Error: No active user session found.</p>";
    exit;
}

if (isset($_POST['subject'])) {
    $selected_subject_id = $_POST['subject'];
} else {
    echo '<p>Error: Subject not selected.</p>';
    exit;
}

// Fetch modules from the database
$sql = F_select_modules_sql();
$r = F_db_query($sql, $db);
$modules = [];
while ($m = F_db_fetch_array($r)) {
    $modules[] = $m;
}

// Fetch subjects from the database based on selected module
$sql = F_select_subjects_sql('subject_module_id=' . $subject_module_id);
$r = F_db_query($sql, $db);
$subjects = [];
while ($m = F_db_fetch_array($r)) {
    $subjects[] = $m;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle Question Type 
    $answer_type = $_POST['answer_type'];
    if ($answer_type === '0') {
        $answer_type_display = 'Multiple Choice';
        $type = 0;
        $answer_type_final = 'single';
    } else if ($answer_type === '1') {
        $answer_type_display = 'Essay';
        $type = 1;
        $answer_type_final = 'text';
    }

    // Handle Question Prompt 
    $text = $_POST['text'];

    // Handle Question Subject 
    $selected_subject_id = $_POST['subject'];
    $subject_name = '';
    foreach ($subjects as $subject) {
        if ($subject['subject_id'] == $selected_subject_id) {
            $subject_name = $subject['subject_name'];
            $subject_description = $subject['subject_description'];
            break;
        }
    }

    // Handle Question Language 
    $language = $_POST['language'];
    if ($language === 'en') {
        $language_display = 'English';
    } else if ($language === 'id') {
        $language_display = 'Bahasa Indonesia';
    }

    // Handle Question Module 
    $selected_module_id = $_POST['subject_module_id'];
    $module_name = '';
    foreach ($modules as $module) {
        if ($module['module_id'] == $selected_module_id) {
            $module_name = $module['module_name'];
            break;
        }
    }


    echo "<p>Module: " . htmlspecialchars($module_name, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Subject: " . htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Answer Type: " . htmlspecialchars($answer_type_display, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Language: " . htmlspecialchars($language_display, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>Prompt: " . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</p>";

    if (isset($_FILES['file'])) {
        if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {


            // <======STORING PDF======>

            // File information
            $tmpPath = $_FILES['file']['tmp_name'];
            $fileName = isset($_FILES['file']['name']) ? $_FILES['file']['name'] : '';
            if (empty($fileName)) {
                F_print_error('ERROR', 'File name is empty. Please ensure a valid file is uploaded.', true);
            }

            // Deployment path
            $deploymentPath = '/var/www/html/uploads/';
            if (!is_dir($deploymentPath)) {
                if (!mkdir($deploymentPath, 0777, true)) {
                    F_print_error('ERROR', 'Failed to create directory: ' . $deploymentPath, true);
                }
            }

            $permanentPath = $deploymentPath . $fileName;

            if (!move_uploaded_file($tmpPath, $permanentPath)) {
                F_print_error('ERROR', 'Failed to move uploaded file to: ' . $permanentPath . '. Check directory permissions.', true);
            }

            $fileType = mime_content_type($permanentPath);
            if ($fileType === false) {
                F_print_error('ERROR', 'Could not determine MIME type for the file: ' . $permanentPath, true);
            }

            // Upload PDF endpoint
            $url = "http://34.27.150.5:8080/api/v1/upload_pdf";

            // Initialize cURL
            $ch = curl_init();

            // Prepare file for upload
            $cfile = new CURLFile($permanentPath, $fileType, $fileName);
            $postData = [
                'file' => $cfile
            ];

            // cURL options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $_SESSION['access_token'],
                'Content-Type: multipart/form-data'
            ]);

            // Execute cURL and get the response
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Handle response
            if ($httpCode == 200) {
                $pdf_url = trim($response);
                echo "<p>File Link: <a href=\"" . htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8') . "\" target=\"_blank\">" . htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8') . "</a></p>";
            } else {
                echo "<p>Failed to upload file.</p>";
            }


            // <======INVOKING GENERATE QUIZ API======>

            // Prepare the query data
            $queryData = [
                'userId' => $user_spring_id,
                'module' => $module_name,
                'subject' => $subject_name,
                'type' => $type,
                'prompt' => $text,
                'language' => $language,
                'contextUrl' => $pdf_url,
            ];

            // Initialize cURL session for generate quiz
            $ch = curl_init();
            if ($ch === false) {
                die('Failed to initialize cURL session');
            }
            curl_setopt($ch, CURLOPT_URL, 'http://34.27.150.5:8080/api/v1/quiz/package');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($queryData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $_SESSION['access_token'],
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 900);

            // Execute cURL request for generting quiz
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                echo '<div class="center-card"><div class="card" style="background-color:#F6F6F6"><div class="card-body">';
                echo '<p>Error: ' . $httpCode . '</p>';
                echo '</div></div></div>';
                curl_close($ch);
                exit;
            }

            curl_close($ch);


            // Decode JSON response
            $responseData = json_decode($response, true);

            // Ensure the response was decoded successfully
            if ($responseData['code'] == 200 && isset($responseData['content']['question_data'])) {
                $questions = $responseData['content']['question_data'];
                $package_id = $responseData['content']['package_id'];

                echo '<style>
                .container {
                    display: flex;
                    flex-wrap: wrap; /* Allows wrapping */
                    gap: 15px; /* Spacing between cards */
                    justify-content: flex-start; /* Align items tao the start horizontally */
                    margin-top: 20px;
                }
                .card {
                    background-color: #F6F6F6;
                    padding: 20px;
                    border: 1px solid #000000;
                    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                    width: calc(33.333% - 20px); /* Adjust card width for 3 columns with space */
                    min-width: 300px; /* Minimum card width */
                    box-sizing: border-box;
                    border-radius: 5px;
                }
                .card label {
                    font-weight: bold;
                    margin-bottom: 10px;
                    display: block;
                }
                .card p {
                    margin: 5px 0;
                }
                .highlight {
                    background-color: yellow;
                    font-weight: bold;
                }
                .difficulty-section {
                    margin-top: 10px;
                    display: flex;
                    align-items: center;
                }
                .difficulty-section label {
                    margin-right: 10px;
                }
                .difficulty-section select {
                    margin-right: 10px;
                }
                .submit-section {
                    text-align: center;
                    margin-top: 20px;
                }
            </style>';

                echo '<form method="POST" action="process.php">';
                foreach ($questions as $index => $question) {
                    echo '<div class="card">';
                    echo '<label for="question' . $index . '"><strong>' . htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') . '</strong></label>';

                    // Display answers (options or essay answer)
                    if ($answer_type === '0') { // Multiple Choice
                        echo '<div>';
                        foreach ($question['options'] as $optionIndex => $option) {
                            $is_correct = ($option === $question['answer']) ? 'true' : 'false';
                            $option_text = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
                            if ($is_correct === 'true') {
                                echo '<p class="highlight">' . $option_text . '</p>';
                            } else {
                                echo '<p>' . $option_text . '</p>';
                            }
                        }
                        echo '</div>';
                    } else { // Essay Answer
                        echo '<div>';
                        $answer_text = htmlspecialchars($question['answer'], ENT_QUOTES, 'UTF-8');
                        echo '<p><strong>Answer:</strong> ' . $answer_text . '</p>';
                        echo '</div>';
                    }

                    // Display explanation if available
                    if (isset($question['explanation']) && !empty($question['explanation'])) {
                        $explanation = htmlspecialchars($question['explanation'], ENT_QUOTES, 'UTF-8');
                        echo '<p><strong>Explanation:</strong> ' . $explanation . '</p>';
                    }

                    // Add difficulty dropdown
                    echo '<div class="difficulty-section">';
                    echo '<label for="difficulty' . $index . '">Difficulty:</label>';
                    echo '<select id="difficulty' . $index . '" name="difficulty[' . $index . ']">';
                    for ($i = 1; $i <= 10; $i++) {
                        echo '<option value="' . $i . '">' . $i . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';

                    // Checkbox for question selection
                    echo '<input type="checkbox" id="question' . $index . '" name="selected_questions[]" value="' . $index . '" style="margin-left: 10px;">';
                    echo '<label for="question' . $index . '" style="margin-left: 5px;">Import this question</label>';

                    // Include hidden inputs for full question details, but conditionally include only selected questions
                    echo '<div class="hidden-question-data">';
                    echo '<input type="hidden" name="questions[' . $index . '][question_spring_id]" value="' . htmlspecialchars($question['question_id'], ENT_QUOTES, 'UTF-8') . '">';
                    echo '<input type="hidden" name="questions[' . $index . '][question]" value="' . htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') . '">';
                    if ($answer_type === '0' && isset($question['options']) && is_array($question['options'])) {
                        foreach ($question['options'] as $optionIndex => $option) {
                            echo '<input type="hidden" name="questions[' . $index . '][answers][' . $optionIndex . '][description]" value="' . htmlspecialchars($option, ENT_QUOTES, 'UTF-8') . '">';
                            echo '<input type="hidden" name="questions[' . $index . '][answers][' . $optionIndex . '][isright]" value="' . ($option === $question['answer'] ? 'true' : 'false') . '">';
                        }
                    } else { // For essay or other types, use answer as description
                        echo '<input type="hidden" name="questions[' . $index . '][answers][0][description]" value="' . htmlspecialchars($question['answer'], ENT_QUOTES, 'UTF-8') . '">';
                        echo '<input type="hidden" name="questions[' . $index . '][answers][0][isright]" value="true">';
                    }
                    if (isset($question['explanation'])) {
                        echo '<input type="hidden" name="questions[' . $index . '][explanation]" value="' . htmlspecialchars($question['explanation'], ENT_QUOTES, 'UTF-8') . '">';
                    }
                    echo '</div>';

                    echo '</div>'; // End of card
                }

                echo '</div>'; // End of container
                echo '<input type="hidden" name="answer_type" value="' . htmlspecialchars($answer_type_final, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="text" value="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="module_id" value="' . htmlspecialchars($selected_module_id, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="subject_id" value="' . htmlspecialchars($selected_subject_id, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="module" value="' . htmlspecialchars($module_name, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="subject" value="' . htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="subject_desc" value="' . htmlspecialchars($subject_description, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="submit" value="Submit" style="margin-top: 20px;">';
                echo '</form>';
            } else {
                echo '<p>Error: ' . htmlspecialchars($responseData['message'], ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
    }
}


require_once('../code/tce_page_footer.php');
