<?php
// Read the incoming JSON request
$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

// Prepare data to be sent to /ask_rag
$ask_rag_api_url = 'localhost:20000/ask_rag';

// Adjust the query_data structure to match the required format
$query_data = [
    [
        'key' => 'file',
        'description' => '',
        'type' => 'file',
        'enabled' => true,
        'value' => [$data['file_path']],
        'fileNotInWorkingDirectoryWarning' => "This file isn't in your working directory. Teammates you share this request with won't be able to use this file. To make collaboration easier you can setup your working directory in Settings.",
        'filesNotInWorkingDirectory' => [$data['file_path']]
    ],
    [
        'key' => 'question_type',
        'value' => $data['question_type'],
        'type' => 'text',
        'enabled' => true
    ],
    [
        'key' => 'subject',
        'value' => $data['subject'],
        'type' => 'text',
        'enabled' => true
    ],
    [
        'key' => 'language',
        'value' => $data['language'],
        'type' => 'text',
        'enabled' => true
    ]
];

// Initialize cURL session for /ask_rag request
$ch = curl_init();

// Set cURL options for /ask_rag request
curl_setopt($ch, CURLOPT_URL, $ask_rag_api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query_data)); // Convert data array to JSON
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response instead of outputting it
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); // Set content type to JSON

// Execute cURL request for /ask_rag
$response = curl_exec($ch);

// Check for errors in /ask_rag request
if (curl_errno($ch)) {
    echo json_encode(['error' => curl_error($ch)]);
    exit;
}

// Close cURL session for /ask_rag request
curl_close($ch);

// Process /ask_rag API response
if ($response) {
    // Decode JSON response
    $response_data = json_decode($response, true);
    echo json_encode($response_data);
} else {
    echo json_encode(['error' => 'No response from the API']);
}
?>
