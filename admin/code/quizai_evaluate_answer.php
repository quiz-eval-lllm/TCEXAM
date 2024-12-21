<?php
// Endpoint untuk evaluasi jawaban essay

// Baca request JSON dari client


$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

// Validasi input
if (!isset($data['question_id'], $data['user_answer'], $data['question_spring_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. Missing question_id or user_answer.']);
    exit;
}

// Ambil data pertanyaan berdasarkan question_id dari database
$db = new mysqli('localhost', 'root', '', 'tcexam'); // Update dengan kredensial database Anda
if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $db->connect_error]);
    exit;
}

$stmt = $db->prepare("SELECT question_description, answer_description AS model_answer FROM tce_questions 
                      JOIN tce_answers ON tce_questions.question_id = tce_answers.answer_question_id 
                      WHERE question_id = ? AND answer_isright = 1 LIMIT 1");
$stmt->bind_param("i", $data['question_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Question not found or no correct answer available.']);
    exit;
}
$question_data = $result->fetch_assoc();
$stmt->close();
$db->close();

// Data yang akan dikirimkan ke model AI
$ai_api_url = 'http://34.27.150.5:8080/api/v1/evaluation/essay'; // URL model AI
$payload = [
    "quizId" => "", // Placeholder for quiz ID, update if applicable
    "answerInfo" => [
        [
            "questionId" => $data['question_spring_id'], // Use the question_spring_id from the request
            "answer" => $data['user_answer'] // Use the user answer from the request
        ]
    ]
];

// Inisialisasi cURL untuk mengirim request ke model AI
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ai_api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Kirim request ke model AI
$response = curl_exec($ch);

// Periksa error dalam cURL
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Decode response dari model AI
$evaluation_result = json_decode($response, true);


if (!$evaluation_result || !isset($evaluation_result['content']['eval_data'][0]['score'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from AI model.']);
    exit;
}

// Extract the score from the AI response
$score = $evaluation_result['content']['eval_data'][0]['score'];

// Return hasil evaluasi ke client
echo json_encode([
    'question_id' => $data['question_id'],
    'user_answer' => $data['user_answer'],
    'score' => $score,
]);
