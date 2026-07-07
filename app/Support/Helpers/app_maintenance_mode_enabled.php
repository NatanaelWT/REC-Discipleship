<?php

function app_maintenance_mode_enabled(): bool {
    return app_config_value('maintenance_mode', '0') === '1';
}
