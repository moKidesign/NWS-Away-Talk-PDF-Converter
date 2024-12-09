<?php
header('Content-Type: application/json');

// Configuration
$anthropicApiKey = getenv('ANTHROPIC_API_KEY');
if (!$anthropicApiKey) {
    http_response_code(500);
    die(json_encode(['error' => 'API key not configured']));
}

// Validate file upload
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => 'No file uploaded or upload error']));
}

$file = $_FILES['pdf'];

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid file type. Please upload a PDF.']));
}

// Read PDF content
$pdfContent = base64_encode(file_get_contents($file['tmp_name']));

// Prepare request to Anthropic API
$curl = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $anthropicApiKey,
        'anthropic-version: 2023-06-01'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'claude-3-opus-20240229',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => "Please convert this PDF content to CSV format, maintaining the structure and data integrity: $pdfContent"
                ]
            ]
        ],
        'max_tokens' => 4096
    ])
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($httpCode !== 200) {
    http_response_code(500);
    die(json_encode(['error' => 'Error processing PDF']));
}

$result = json_decode($response, true);
if (!$result || !isset($result['content'])) {
    http_response_code(500);
    die(json_encode(['error' => 'Invalid response from API']));
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="converted.csv"');

// Output CSV content
echo $result['content'];
?>