<?php

namespace App\Services\MemberFeedbackJournals;

class MemberFeedbackQuestionCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function sections(): array
    {
        if (! function_exists('public_member_feedback_questions')) {
            require_once app_path('Support/Helpers/public_member_feedback_questions.php');
        }

        return public_member_feedback_questions();
    }

    /**
     * @return array<int, array{section_key: string, key: string, scale: int}>
     */
    public function ratingQuestions(): array
    {
        $questions = [];
        foreach ($this->sections() as $sectionKey => $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach (($section['ratings'] ?? []) as $rating) {
                if (! is_array($rating)) {
                    continue;
                }

                $key = trim((string) ($rating['key'] ?? ''));
                if ($key === '') {
                    continue;
                }

                $questions[] = [
                    'section_key' => (string) $sectionKey,
                    'key' => $key,
                    'scale' => max(1, (int) ($rating['scale'] ?? 10)),
                ];
            }
        }

        return $questions;
    }

    /**
     * @return array<int, array{section_key: string, key: string}>
     */
    public function noteQuestions(): array
    {
        $notes = [];
        foreach ($this->sections() as $sectionKey => $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = trim((string) ($section['note_key'] ?? ''));
            if ($key !== '') {
                $notes[] = [
                    'section_key' => (string) $sectionKey,
                    'key' => $key,
                ];
            }
        }

        return $notes;
    }
}
