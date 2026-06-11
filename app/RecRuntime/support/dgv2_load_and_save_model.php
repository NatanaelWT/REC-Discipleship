<?php

function dgv2_load_and_save_model(string $branch, callable $modifier): void {
    $model = dgv2_normalize_model(dgv2_read_model($branch));
    $modifier($model);
    dgv2_write_model($branch, $model);
}
