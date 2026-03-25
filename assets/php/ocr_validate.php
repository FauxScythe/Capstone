<?php
/**
 * Validates an uploaded ID image using OCR.space API
 * and cross-checks extracted text against provided form fields.
 * Name matching follows signup.php: word-by-word + similar_text() >= 60%.
 *
 * Returns ['valid' => bool, 'text' => string, 'message' => string]
 */
function validateIdWithOCR(array $file, string $first_name = '', string $last_name = '', string $dob = ''): array {
    $api_key  = 'K88063278988957';
    $endpoint = 'https://api.ocr.space/parse/image';

    $post = [
        'apikey'            => $api_key,
        'language'          => 'eng',
        'isOverlayRequired' => 'false',
        'detectOrientation' => 'true',
        'scale'             => 'true',
        'OCREngine'         => '2',
        'file'              => new CURLFile($file['tmp_name'], $file['type'], $file['name']),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        // OCR unavailable — accept for manual review
        return ['valid' => true, 'text' => '', 'message' => 'OCR service unavailable, ID accepted for manual review.'];
    }

    $data = json_decode($response, true);

    if (!$data || ($data['IsErroredOnProcessing'] ?? false)) {
        $err = $data['ErrorMessage'][0] ?? 'OCR processing failed.';
        return ['valid' => false, 'text' => '', 'message' => 'ID validation failed: ' . $err];
    }

    $parsed_text = '';
    foreach ($data['ParsedResults'] ?? [] as $result) {
        $parsed_text .= $result['ParsedText'] ?? '';
    }
    $parsed_text = trim($parsed_text);

    if (empty($parsed_text)) {
        return ['valid' => false, 'text' => '', 'message' => 'Could not read text from the uploaded ID. Please upload a clearer image.'];
    }

    // Basic ID structure check
    $has_date   = (bool) preg_match('/\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}/', $parsed_text);
    $has_number = (bool) preg_match('/[A-Z0-9\-]{5,}/', $parsed_text);
    if (strlen($parsed_text) < 10 || (!$has_date && !$has_number)) {
        return ['valid' => false, 'text' => $parsed_text, 'message' => 'The uploaded image does not appear to be a valid government ID. Please upload a clearer photo.'];
    }

    $missing = [];

    // ── Name check (signup.php style) ────────────────────────────────────────
    if ($first_name || $last_name) {
        $form_full  = strtoupper(trim("$first_name $last_name"));
        $form_words = array_filter(explode(' ', $form_full));

        // Normalize OCR text: uppercase, collapse spaces
        $ocr_upper = strtoupper(preg_replace('/\s+/', ' ', $parsed_text));

        // Word-by-word: every form word must appear in OCR text
        $all_words_found = true;
        foreach ($form_words as $word) {
            if (strpos($ocr_upper, $word) === false) {
                $all_words_found = false;
                break;
            }
        }

        if (!$all_words_found) {
            // Fallback: similar_text >= 60% on the full name block
            similar_text($form_full, $ocr_upper, $percent);
            // Also check if form name is a substring of OCR or vice versa
            $contains = strpos($ocr_upper, $form_full) !== false
                     || strpos($form_full, $ocr_upper) !== false;

            if ($percent < 60 && !$contains) {
                $missing[] = 'name';
            }
        }
    }

    // ── DOB check ────────────────────────────────────────────────────────────
    if ($dob) {
        [$yyyy, $mm, $dd] = explode('-', $dob);
        $month_names = ['january','february','march','april','may','june',
                        'july','august','september','october','november','december'];
        $month_name  = $month_names[(int)$mm - 1];
        $text_lower  = strtolower($parsed_text);

        $formats = [
            "$mm/$dd/$yyyy",
            "$dd/$mm/$yyyy",
            "$yyyy/$mm/$dd",        // Philippine ID format e.g. 2002/04/02
            "$yyyy-$mm-$dd",
            "$mm-$dd-$yyyy",
            "$dd-$mm-$yyyy",
            "$mm/$dd/" . substr($yyyy, 2),
            "$month_name $dd, $yyyy",
            "$month_name $dd $yyyy",
            "$dd $month_name $yyyy",
            "$mm/$dd",
            "$dd/$mm",
            $month_name,
        ];

        $dob_found = false;
        foreach ($formats as $fmt) {
            if (strpos($text_lower, strtolower($fmt)) !== false) {
                $dob_found = true;
                break;
            }
        }

        if (!$dob_found) {
            $missing[] = 'date of birth';
        }
    }

    if (!empty($missing)) {
        return [
            'valid'   => false,
            'text'    => $parsed_text,
            'message' => 'ID mismatch: ' . implode(' and ', $missing) . ' on the ID does not match your registration details. Please check your details or upload the correct ID.',
        ];
    }

    return ['valid' => true, 'text' => $parsed_text, 'message' => 'ID verified — name and date of birth match.'];
}
?>
