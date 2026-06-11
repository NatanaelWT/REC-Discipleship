<?php

function body_class_attr(array $bodyClasses): string {
    return ' class="' . h(implode(' ', array_values(array_unique($bodyClasses)))) . '"';
}
