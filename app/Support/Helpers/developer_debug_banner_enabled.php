<?php

function developer_debug_banner_enabled(): bool {
    return app_config_value('developer_debug_banner', '0') === '1';
}
