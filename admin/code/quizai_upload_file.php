<?php
if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
    // API endpoint for /upload_file route
    $upload_api_url = 'http://localhost:19645/upload_file';

    // Get file details
    $file = $_FILES['file'];

    // Use CURLFile to prepare the file for upload
    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);

    // Initialize cURL session for file upload
    $upload_ch = curl_init();

    // Set cURL options for file upload
    curl_setopt($upload_ch, CURLOPT_URL, $upload_api_url);
    curl_setopt($upload_ch, CURLOPT_POST, true);
    curl_setopt($upload_ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);
    curl_setopt($upload_ch, CURLOPT_RETURNTRANSFER, true); // Return response instead of outputting it
    curl_setopt($upload_ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data')); // Set content type to multipart/form-data

    // Execute cURL request for file upload
    $upload_response = curl_exec($upload_ch);

    // Check for errors in file upload
    if (curl_errno($upload_ch)) {
        echo json_encode(['error' => curl_error($upload_ch)]);
        exit;
    }

    // Close cURL session for file upload
    curl_close($upload_ch);

    // Process file upload response
    if ($upload_response) {
        // Decode JSON response
        $upload_response_data = json_decode($upload_response, true);
        if (isset($upload_response_data['file_path'])) {
            echo json_encode(['file_path' => $upload_response_data['file_path']]);
        } elseif (isset($upload_response_data['error'])) {
            echo json_encode(['error' => $upload_response_data['error']]);
        } else {
            echo json_encode(['error' => 'Invalid response from the upload API']);
        }
    } else {
        echo json_encode(['error' => 'No response from the upload API']);
    }
} else {
    echo json_encode(['error' => 'No file uploaded or upload error']);
}
?>
