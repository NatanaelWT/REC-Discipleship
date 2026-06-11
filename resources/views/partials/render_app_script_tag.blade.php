<?php

function render_app_script_tag(): void {
    $jsVersion = asset_version('assets/app.js');
    echo "<script src=\"/assets/app.js" . $jsVersion . "\"></script>\n";
}
