<?php

function dgv2_normalize_model(array $model): array {
    if (isset($model['groups_v2']) && !isset($model['discipleship_groups'])) {
        $model['discipleship_groups'] = $model['groups_v2'];
    }
    foreach (dgv2_empty_model() as $name => $_unused) {
        if (!isset($model[$name]) || !is_array($model[$name])) {
            $model[$name] = [];
        } else {
            $model[$name] = array_values($model[$name]);
        }
    }
    return $model;
}
