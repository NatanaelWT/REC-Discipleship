<?php

function render_condition_alerts(array $alerts): void {
    foreach ($alerts as $alert) {
        if (empty($alert['when'])) {
            continue;
        }
        $message = trim((string) ($alert['message'] ?? ''));
        if ($message === '') {
            continue;
        }
        $tone = trim((string) ($alert['tone'] ?? 'success'));
        render_alert($tone !== '' ? $tone : 'success', $message);
    }
}
