<?php

namespace App\Services\PublicMaterials;

class PublicMaterialTextFormatter
{
    /**
     * @return array<int, array{type: string, text: string, lead: string, rest: string, class: string}>
     */
    public function blocks(string $text): array
    {
        $lines = preg_split('/\R/u', str_replace(["\r\n", "\r"], "\n", $text));
        if (! is_array($lines)) {
            return [];
        }

        $blocks = [];
        $currentLines = [];
        $currentLead = '';

        foreach ($lines as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') {
                $this->flushParagraph($blocks, $currentLines, $currentLead);

                continue;
            }

            if ($this->isHeadingLine($line)) {
                $this->flushParagraph($blocks, $currentLines, $currentLead);
                $blocks[] = $this->headingBlock($line);

                continue;
            }

            if ($this->startsNumberedItem($line) && $currentLines !== []) {
                $this->flushParagraph($blocks, $currentLines, $currentLead);
            }

            if ($this->startsOrdinalSubsection($line)) {
                $this->flushParagraph($blocks, $currentLines, $currentLead);
                $currentLead = $this->leadText($line);
            } elseif ($this->shouldSplitParagraph($currentLines, $line)) {
                $this->flushParagraph($blocks, $currentLines, $currentLead);
            }

            $currentLines[] = $line;
        }

        $this->flushParagraph($blocks, $currentLines, $currentLead);

        return $blocks;
    }

    /**
     * @param  array<int, array{type: string, text: string, lead: string, rest: string, class: string}>  $blocks
     * @param  array<int, string>  $lines
     */
    private function flushParagraph(array &$blocks, array &$lines, string &$lead): void
    {
        if ($lines === []) {
            $lead = '';

            return;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', implode(' ', $lines)));
        if ($text !== '') {
            $rest = '';
            if ($lead !== '' && str_starts_with($text, $lead)) {
                $rest = trim(substr($text, strlen($lead)));
            } else {
                $lead = '';
            }

            $blocks[] = [
                'type' => 'paragraph',
                'text' => $text,
                'lead' => $lead,
                'rest' => $rest,
                'class' => $this->startsNumberedItem($text) ? 'is-list-item' : '',
            ];
        }

        $lines = [];
        $lead = '';
    }

    /**
     * @return array{type: string, text: string, lead: string, rest: string, class: string}
     */
    private function headingBlock(string $line): array
    {
        return [
            'type' => 'heading',
            'text' => $line,
            'lead' => '',
            'rest' => '',
            'class' => preg_match('/^Sesi\s+\d+/i', $line) === 1 ? 'is-session' : '',
        ];
    }

    private function isHeadingLine(string $line): bool
    {
        $length = function_exists('mb_strlen') ? mb_strlen($line) : strlen($line);
        if ($length > 120) {
            return false;
        }

        if ($this->startsNumberedItem($line)) {
            return false;
        }

        if (preg_match('/^Sesi\s+\d+\b/i', $line) === 1) {
            return true;
        }

        if ($length <= 100 && str_ends_with($line, '?')) {
            return true;
        }

        $lettersOnly = preg_replace('/[^\p{L}]/u', '', $line) ?? '';
        if ($lettersOnly === '') {
            return false;
        }

        $uppercaseLine = function_exists('mb_strtoupper') ? mb_strtoupper($line, 'UTF-8') : strtoupper($line);

        return $line === $uppercaseLine && $length <= 100;
    }

    private function startsOrdinalSubsection(string $line): bool
    {
        return preg_match('/^(Pertama|Kedua|Ketiga|Keempat|Kelima|Keenam|Ketujuh|Kedelapan|Kesembilan|Kesepuluh|Terakhir),\s+/iu', $line) === 1;
    }

    private function startsNumberedItem(string $line): bool
    {
        return preg_match('/^\d+\.\s+/', $line) === 1;
    }

    /**
     * @param  array<int, string>  $currentLines
     */
    private function shouldSplitParagraph(array $currentLines, string $line): bool
    {
        if ($currentLines === []) {
            return false;
        }

        $currentText = implode(' ', $currentLines);
        $length = function_exists('mb_strlen') ? mb_strlen($currentText) : strlen($currentText);
        if ($length < 360) {
            return false;
        }

        $previous = end($currentLines);
        if (! is_string($previous) || preg_match('/[.!?:"”’)]$/u', $previous) !== 1) {
            return false;
        }

        return preg_match('/^[\p{Lu}0-9"]/u', $line) === 1;
    }

    private function leadText(string $line): string
    {
        if (preg_match('/^(.+?[.!?])(\s|$)/u', $line, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return $line;
    }
}
