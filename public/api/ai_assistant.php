<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';
require_once __DIR__ . '/../../app/helpers/ai_assistant_helper.php';

api_require_method('POST');

$auth = api_require_auth(['superadmin', 'admin', 'bos']);
$body = api_json_body();
$today = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime($today . ' -6 days'));
$startDate = ai_assistant_normalize_date($body['start_date'] ?? null, $defaultStart);
$endDate = ai_assistant_normalize_date($body['end_date'] ?? null, $today);
$question = trim((string) ($body['question'] ?? ''));

if ($question === '') {
    api_error('Pertanyaan AI wajib diisi.', 422, 'question_required');
}

$status = ai_assistant_status();
if (!$status['ready']) {
    api_error('AI Assistant belum siap.', 503, 'ai_not_ready', ['reasons' => $status['reasons']]);
}

try {
    $result = ai_assistant_answer(db(), $question, $auth['user'], $startDate, $endDate);
    api_ok([
        'answer' => $result['answer'],
        'model' => $result['model'],
        'usage' => $result['usage'],
        'request_id' => $result['request_id'],
        'snapshot' => $result['snapshot'],
    ]);
} catch (InvalidArgumentException $e) {
    api_error($e->getMessage(), 422, 'invalid_ai_request');
} catch (Throwable $e) {
    api_error($e->getMessage(), 502, 'ai_request_failed');
}
