<?php

function render_app_document_head(string $app): void {
    echo "<!doctype html>\n";
    echo "<html lang=\"id\">\n";
    echo "<head>\n";
    echo "  <meta charset=\"utf-8\">\n";
    echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "  <title>" . $app . "</title>\n";
    $logoVersion = asset_version('assets/logo.png');
    echo "  <link rel=\"icon\" type=\"image/png\" href=\"/assets/logo.png" . $logoVersion . "\">\n";
    echo "  <link rel=\"shortcut icon\" type=\"image/png\" href=\"/assets/logo.png" . $logoVersion . "\">\n";
    echo "  <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
    echo "  <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
    echo "  <link href=\"https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700&display=swap\" rel=\"stylesheet\">\n";
    $cssVersion = asset_version('assets/style.css');
    echo "  <link rel=\"stylesheet\" href=\"/assets/style.css" . $cssVersion . "\">\n";
    echo "</head>\n";
}
