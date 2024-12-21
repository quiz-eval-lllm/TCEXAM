<?php
//============================================================+
// File name   : tce_functions_utils.php
// Description : Utility functions for TCExam.
//============================================================+

/**
 * Fetch a new UUID from the API.
 *
 * @return array An associative array containing either 'success' => true and 'uuid', or 'success' => false and 'error'.
 */
function fetchUUID()
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.uuidtools.com/api/generate/v4');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout to avoid hanging indefinitely

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'error' => "cURL error: " . $error_msg,
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP error code: " . $httpCode,
        ];
    }

    $uuidList = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'JSON decode error: ' . json_last_error_msg(),
        ];
    }

    if (is_array($uuidList) && count($uuidList) > 0) {
        // Return the first UUID as a string
        return [
            'success' => true,
            'uuid' => (string) $uuidList[0], // Ensure it is explicitly cast to a string
        ];
    }

    return [
        'success' => false,
        'error' => 'Invalid response from UUID API',
    ];
}
