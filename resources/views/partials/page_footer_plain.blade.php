<?php

function page_footer_plain(): void {
    echo "</main>\n";
    render_app_script_tag();
    echo "</body>\n";
    echo "</html>\n";
}
