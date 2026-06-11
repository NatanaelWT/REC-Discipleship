<?php

function current_username(): string {
    return trim((string) ($_SESSION['user'] ?? ''));
}
