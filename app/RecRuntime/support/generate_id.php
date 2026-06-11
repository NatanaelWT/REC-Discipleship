<?php

function generate_id(string $prefix): string {
    return $prefix . '_' . bin2hex(random_bytes(4));
}
