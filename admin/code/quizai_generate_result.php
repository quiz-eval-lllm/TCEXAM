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



            // // Deployment path
            // $deploymentPath = '/var/www/html/uploads/';
            // if (!is_dir($deploymentPath)) {
            //     if (!mkdir($deploymentPath, 0777, true)) {
            //         F_print_error('ERROR', 'Failed to create directory: ' . $deploymentPath, true);
            //     }
            // }


            // $permanentPath = $deploymentPath . $fileName;

            // if (!move_uploaded_file($tmpPath, $permanentPath)) {
            //     F_print_error('ERROR', 'Failed to move uploaded file to: ' . $permanentPath . '. Check directory permissions.', true);
            // }

            // $fileType = mime_content_type($permanentPath);
            // if ($fileType === false) {
            //     F_print_error('ERROR', 'Could not determine MIME type for the file: ' . $permanentPath, true);
            // }

            $permanentPath = 'C:/xampp/tmp/' . $fileName;

            // Move the file to a permanent location
            if (!move_uploaded_file($tmpPath, $permanentPath)) {
                die("Failed to move uploaded file to: " . $permanentPath);
            }

            $fileType = mime_content_type($permanentPath);
            if ($fileType === false) {
                die("Could not determine MIME type for: $permanentPath");
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
        } else {
            // Handle file upload error
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    F_print_error('ERROR', 'The uploaded file exceeds the maximum allowed size of 100MB.', true);
                    break;
                case UPLOAD_ERR_NO_FILE:
                    F_print_error('ERROR', 'No file was uploaded. Please select a file to upload.', true);
                    break;
                default:
                    F_print_error('ERROR', 'An error occurred during file upload. Please try again.', true);
                    break;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 20px;
        }

        .card {
            background-color: #F6F6F6;
            padding: 20px;
            border: 1px solid #CCCCCC;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: calc(33.333% - 20px);
            min-width: 300px;
            box-sizing: border-box;
            border-radius: 8px;
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
            background-color: #FFF9C4;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 3px;
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

        .submit-button {
            display: none;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #999;
            color: white;
            border: 1px solid #666;
            cursor: pointer;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .submit-button:hover {
            background-color: #777;
        }

        #loading-message {
            display: none;
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            font-size: 16px;
            color: #666;
            background-color: #f2f2f2;
            border: 1px solid #ccc;
        }

        #loading-message p {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div id="loading-message" style="display: none;">
        <p>Please wait... The system is fetching questions from the server. Kindly remain on this page as the process may take a moment.</p>
    </div>
    <form id="question-form" method="POST" action="process.php">
        <!-- Hidden inputs to pass the global data -->
        <input type="hidden" name="answer_type" value="<?php echo htmlspecialchars($answer_type_final, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="text" value="<?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="module_id" value="<?php echo htmlspecialchars($selected_module_id, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selected_subject_id, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="module" value="<?php echo htmlspecialchars($module_name, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="subject_desc" value="<?php echo htmlspecialchars($subject_description, ENT_QUOTES, 'UTF-8'); ?>">

        <div id="questions-container" class="container"></div>
        <button type="submit" id="submit-button" class="submit-button">Submit</button>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const apiUrl = "http://34.27.150.5:8080/api/v1/quiz/package";
            const loadingMessage = document.getElementById('loading-message');
            const submitButton = document.getElementById('submit-button');
            const questionsContainer = document.getElementById('questions-container');

            // Utility function to toggle the loading message
            function toggleLoading(show) {
                if (show) {
                    loadingMessage.style.display = 'flex';
                    questionsContainer.style.display = 'none';
                    submitButton.style.display = 'none';
                } else {
                    loadingMessage.style.display = 'none';
                    questionsContainer.style.display = 'flex';
                    submitButton.style.display = 'block'; // Show submit button after fetching
                }
            }

            // Fetch questions from API
            async function fetchQuestions() {
                try {
                    toggleLoading(true); // Show "Processing..." message

                    const requestBody = {
                        userId: "<?php echo htmlspecialchars($user_spring_id, ENT_QUOTES, 'UTF-8'); ?>",
                        module: "<?php echo htmlspecialchars($module_name, ENT_QUOTES, 'UTF-8'); ?>",
                        subject: "<?php echo htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8'); ?>",
                        type: <?php echo (int)$type; ?>,
                        prompt: "<?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?>",
                        language: "<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>",
                        contextUrl: "<?php echo htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8'); ?>"
                    };

                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer <?php echo $_SESSION['access_token']; ?>`
                        },
                        body: JSON.stringify(requestBody)
                    });

                    if (!response.ok) {
                        throw new Error(`Error: ${response.status} - ${response.statusText}`);
                    }

                    const data = await response.json();

                    if (data.code === 200 && data.content.question_data) {
                        renderQuestions(data.content.question_data);
                    } else {
                        throw new Error(`Error: ${data.message || 'Unknown error'}`);
                    }
                } catch (error) {
                    console.error("Error fetching questions:", error);
                    alert(`Failed to load questions. ${error.message}`);
                } finally {
                    toggleLoading(false); // Hide "Processing..." message
                }
            }

            // Render questions dynamically
            function renderQuestions(questions) {
                const container = document.getElementById('questions-container');
                container.innerHTML = '';

                questions.forEach((question, index) => {
                    const card = document.createElement('div');
                    card.className = 'card';

                    // Question text
                    const questionLabel = document.createElement('label');
                    questionLabel.innerHTML = `<strong>${question.question}</strong>`;
                    card.appendChild(questionLabel);

                    // Answer options or Essay Answer
                    if (question.options) { // Multiple Choice
                        const optionsDiv = document.createElement('div');
                        question.options.forEach(option => {
                            const optionText = document.createElement('p');
                            optionText.textContent = option;
                            if (option === question.answer) {
                                optionText.classList.add('highlight');
                            }
                            optionsDiv.appendChild(optionText);
                        });
                        card.appendChild(optionsDiv);
                    } else { // Essay
                        const essayAnswer = document.createElement('p');
                        essayAnswer.innerHTML = `<strong>Answer:</strong> ${question.answer}`;
                        card.appendChild(essayAnswer);
                    }

                    // Explanation (if any)
                    if (question.explanation) {
                        const explanation = document.createElement('p');
                        explanation.innerHTML = `<strong>Explanation:</strong> ${question.explanation}`;
                        card.appendChild(explanation);
                    }

                    // Difficulty dropdown
                    const difficultySection = document.createElement('div');
                    difficultySection.className = 'difficulty-section';
                    difficultySection.innerHTML = `
                        <label for="difficulty${index}">Difficulty:</label>
                        <select id="difficulty${index}" name="difficulty[${index}]">
                            ${[...Array(10).keys()].map(i => `<option value="${i + 1}">${i + 1}</option>`).join('')}
                        </select>
                    `;
                    card.appendChild(difficultySection);

                    // Checkbox for question selection
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'selected_questions[]';
                    checkbox.value = index;
                    card.appendChild(checkbox);

                    const checkboxLabel = document.createElement('label');
                    checkboxLabel.textContent = ' Import this question';
                    card.appendChild(checkboxLabel);

                    // Hidden question details
                    card.innerHTML += `
                        <input type="hidden" name="questions[${index}][question]" value="${question.question}">
                        <input type="hidden" name="questions[${index}][question_spring_id]" value="${question.question_id}">
                        ${question.options
                            ? question.options.map((opt, i) => `
                                <input type="hidden" name="questions[${index}][answers][${i}][description]" value="${opt}">
                                <input type="hidden" name="questions[${index}][answers][${i}][isright]" value="${opt === question.answer}">
                            `).join('')
                            : `<input type="hidden" name="questions[${index}][answers][0][description]" value="${question.answer}">
                               <input type="hidden" name="questions[${index}][answers][0][isright]" value="true">`}
                    `;

                    container.appendChild(card);
                });
            }

            // Initial fetch
            fetchQuestions();
        });
    </script>
</body>


</html>


<?php
require_once('../code/tce_page_footer.php');
?>