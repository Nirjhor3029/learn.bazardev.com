<?php

return [
    // Extensible like course.courses: a future "set-2" is one new entry here plus its
    // own resources/content/mock-exams/{slug}/ files — no code changes.
    'sets' => [
        'set-1' => [
            'title' => 'IELTS Mock Test — Set 1',
            'status' => 'available',
        ],
    ],

    // Where to send readers for the real Listening audio + official questions — we never
    // host or re-create IELTS/British Council audio ourselves. See App\Services\MockExam.
    'listening_practice_url' => 'https://takeielts.britishcouncil.org/take-ielts/prepare/free-ielts-english-practice-tests/listening',
];
