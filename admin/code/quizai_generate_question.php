<?php

// Include the configuration file
require_once('../config/tce_config.php');

// Define the required page level (assuming K_AUTH_ADMIN_IMPORT is a constant)
$pagelevel = K_AUTH_ADMIN_IMPORT;

// Include the authorization file
require_once('../../shared/code/tce_authorization.php');

// Set the page title
$thispage_title = 'Generate Question from PDF';

// Include necessary files
require_once('../code/tce_page_header.php');
require_once('../../shared/code/tce_functions_form.php');
require_once('../../shared/code/tce_functions_tcecode.php');
require_once('../../shared/code/tce_functions_auth_sql.php');
?>

<?php
// set default values
$subject_module_id = isset($_REQUEST['subject_module_id']) ? (int) $_REQUEST['subject_module_id'] : 0;

$question_id = isset($_REQUEST['question_id']) ? (int) $_REQUEST['question_id'] : 0;

if (! isset($_REQUEST['question_type']) || empty($_REQUEST['question_type'])) {
    $question_type = 1;
} else {
    $question_type = (int) $_REQUEST['question_type'];
}

$question_difficulty = isset($_REQUEST['question_difficulty']) ? (int) $_REQUEST['question_difficulty'] : 1;

if (! isset($_REQUEST['question_enabled']) || empty($_REQUEST['question_enabled'])) {
    $question_enabled = false;
} else {
    $question_enabled = F_getBoolean($_REQUEST['question_enabled']);
}

if (isset($_REQUEST['changemodule']) && $_REQUEST['changemodule'] > 0) {
    $changemodule = 1;
} elseif (isset($_REQUEST['selectmodule'])) {
    $changemodule = 1;
} else {
    $changemodule = 0;
}

if (isset($_REQUEST['changecategory']) && $_REQUEST['changecategory'] > 0) {
    $changecategory = 1;
} elseif (isset($_REQUEST['selectcategory'])) {
    $changecategory = 1;
} else {
    $changecategory = 0;
}

$subject_id = isset($_REQUEST['subject_id']) ? (int) $_REQUEST['subject_id'] : 0;

$question_subject_id = isset($_REQUEST['question_subject_id']) ? (int) $_REQUEST['question_subject_id'] : 0;

if (! isset($_REQUEST['max_position']) || empty($_REQUEST['max_position'])) {
    $max_position = 0;
} else {
    $max_position = (int) $_REQUEST['max_position'];
}

if (! isset($_REQUEST['question_position']) || empty($_REQUEST['question_position'])) {
    $question_position = 0;
} else {
    $question_position = (int) $_REQUEST['question_position'];
}

if (! isset($_REQUEST['question_timer']) || empty($_REQUEST['question_timer'])) {
    $question_timer = 0;
} else {
    $question_timer = (int) $_REQUEST['question_timer'];
}

if (! isset($_REQUEST['question_fullscreen']) || empty($_REQUEST['question_fullscreen'])) {
    $question_fullscreen = false;
} else {
    $question_fullscreen = F_getBoolean($_REQUEST['question_fullscreen']);
}

if (! isset($_REQUEST['question_inline_answers']) || empty($_REQUEST['question_inline_answers'])) {
    $question_inline_answers = false;
} else {
    $question_inline_answers = F_getBoolean($_REQUEST['question_inline_answers']);
}

if (! isset($_REQUEST['question_auto_next']) || empty($_REQUEST['question_auto_next'])) {
    $question_auto_next = false;
} else {
    $question_auto_next = F_getBoolean($_REQUEST['question_auto_next']);
}

if (isset($_REQUEST['question_description'])) {
    $question_description = utrim($_REQUEST['question_description']);
    if (function_exists('normalizer_normalize')) {
        // normalize UTF-8 string based on settings
        $question_description = F_utf8_normalizer($question_description, K_UTF8_NORMALIZATION_MODE);
    }
}

$question_explanation = isset($_REQUEST['question_explanation']) ? utrim($_REQUEST['question_explanation']) : '';

$qtype = ['S', 'M', 'T', 'O']; // question types

// comma separated list of required fields
$_REQUEST['ff_required'] = 'question_description';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_description'], ENT_COMPAT, $l['a_meta_charset']);

// check user's authorization
if ($question_id > 0) {
    $sql = 'SELECT subject_module_id, question_subject_id
            FROM ' . K_TABLE_SUBJECTS . ', ' . K_TABLE_QUESTIONS . '
            WHERE subject_id=question_subject_id
                AND question_id=' . $question_id . '
            LIMIT 1';
    if ($r = F_db_query($sql, $db)) {
        if ($m = F_db_fetch_array($r)) {
            $subject_module_id = (int) $m['subject_module_id'];
            $question_subject_id = (int) $m['question_subject_id'];
            // check user's authorization for parent module
            if (! F_isAuthorizedUser(K_TABLE_MODULES, 'module_id', $subject_module_id, 'module_user_id') && ! F_isAuthorizedUser(K_TABLE_SUBJECTS, 'subject_id', $question_subject_id, 'subject_user_id')) {
                F_print_error('ERROR', $l['m_authorization_denied'], true);
            }
        }
    } else {
        F_display_db_error();
    }
}
?>

<?php
// select default module/subject (if not specified)
if ($subject_module_id <= 0) {
    $sql = F_select_modules_sql() . ' LIMIT 1';
    if ($r = F_db_query($sql, $db)) {
        $subject_module_id = ($m = F_db_fetch_array($r)) ? $m['module_id'] : 0;
    } else {
        F_display_db_error();
    }
}

// select subject
if ($changemodule > 0 || $question_subject_id <= 0) {
    $sql = F_select_subjects_sql('subject_module_id=' . $subject_module_id . '') . ' LIMIT 1';
    if ($r = F_db_query($sql, $db)) {
        $question_subject_id = ($m = F_db_fetch_array($r)) ? $m['subject_id'] : 0;
    } else {
        F_display_db_error();
    }
}
?>

<?php
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Question from PDF</title>
    <style>
        body {
            justify-content: center;
            align-items: center;
        }

        .card {
            background-color: #F6F6F6;
            padding: 20px;
            border: 1px solid #000000;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        .form-group.d-flex {
            display: flex;
        }

        /* Adjust margin-right for spacing between the dropdowns */
        .form-group.d-flex>div {
            margin-right: 20px;
            /* Adjust as needed */
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="card-body">
            <form id="uploadForm" enctype="multipart/form-data" method="POST" action="quizai_generate_result.php">
                <input type="hidden" name="form_action" id="form_action" value="generate">

                <div class="mb-3">
                    <label for="module" class="form-label">Module:</label>
                    <input type="hidden" name="changemodule" id="changemodule" value="" />
                    <select name="subject_module_id" id="subject_module_id" size="0" onchange="submitForm('change_module');" class="form-control" required>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?php echo $module['module_id']; ?>" <?php echo ($module['module_id'] == $subject_module_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($module['module_name'], ENT_NOQUOTES, $l['a_meta_charset']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject:</label>
                    <input type="hidden" name="changecategory" id="changecategory" value="" />
                    <select name="subject" id="question_subject_id" size="0" onchange="submitForm('change_subject');" class="form-control" required>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>" <?php echo ($subject['subject_id'] == $question_subject_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name'], ENT_NOQUOTES, $l['a_meta_charset']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <hr>
                <div class="mb-3">
                    <label for="file" class="form-label">Upload File:</label>
                    <input type="file" class="form-control" name="file" id="file" enctype="multipart/form-data" required>
                </div>
                <div class="mb-3">
                    <label for="answer_type" class="form-label">Type:</label><br>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="answer_type" id="multiple_choice" value="0" required>
                        <label class="form-check-label" for="multiple_choice">Multiple Choice</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="answer_type" id="essay" value="1" required>
                        <label class="form-check-label" for="essay">Essay</label>
                    </div>
                    <div class="form-group d-flex">
                        <div>
                            <label for="language" class="form-label">Language:</label><br>
                            <select name="language" id="language" class="form-control">
                                <option value="id">Bahasa Indonesia</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="text" class="form-label">Topic <em>(e.g., Ethical computing)</em>:</label>
                    <textarea class="form-control" name="text" id="text" rows="5" required></textarea>
                </div>
        </div>
        <br />
        <button type="submit" id="submitBtn" style="padding: 5px 10px; font-size: 1.25em;">Generate!</button>
        </form>
    </div>
    </div>

    <script>
        function submitForm(action) {
            document.getElementById('form_action').value = action;
            if (action === 'change_module' || action === 'change_subject') {
                document.getElementById('uploadForm').action = ''; // No specific action for module/subject change
            } else {
                document.getElementById('uploadForm').action = 'quizai_generate_result.php'; // Default action
            }
            document.getElementById('uploadForm').submit();
        }
    </script>
</body>

</html>


<?php
require_once('../code/tce_page_footer.php');
?>