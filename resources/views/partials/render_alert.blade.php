<?php

function render_alert(string $tone, string $message): void {
    echo "<div class=\"alert " . h($tone) . "\">" . h($message) . "</div>\n";
}
