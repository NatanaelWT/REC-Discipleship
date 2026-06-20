<?php

function temporary_model_id(string $type): string
{
    return 'new_'.$type.'_'.bin2hex(random_bytes(4));
}
