<?php

function page_footer(): void {
    echo "    </main>\n";
    echo "  </div>\n";
    echo "</div>\n";
    render_app_script_tag();
    echo "</body>\n";
    echo "</html>\n";
}
