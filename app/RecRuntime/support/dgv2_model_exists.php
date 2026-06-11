<?php

function dgv2_model_exists(string $branch): bool {
    foreach (dgv2_model_names() as $name) {
        if (discipleship_branch_json_exists(dgv2_branch_file_path($name, $branch))) {
            return true;
        }
    }
    return false;
}
