<?php

function worship_penatalayan_svg_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
