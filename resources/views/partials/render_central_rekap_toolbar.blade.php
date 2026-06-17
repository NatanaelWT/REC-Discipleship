<?php

function render_central_rekap_toolbar(string $currentPage): void {
    if (!is_effective_central_discipleship_readonly()) {
        return;
    }
    $selectedBranch = central_recap_selected_branch();
    $selectedBranchLabel = central_recap_branch_label($selectedBranch);
    $preservedQueryParams = [];
    foreach ($_GET as $paramKey => $paramValue) {
        if (!is_string($paramKey)) {
            continue;
        }
        if (in_array($paramKey, ['page', 'rekap_cabang'], true)) {
            continue;
        }
        $preservedQueryParams[$paramKey] = $paramValue;
    }
    $buildRekapHref = function (string $branchCode) use ($currentPage, $preservedQueryParams): string {
        $params = array_merge([
            'rekap_cabang' => $branchCode,
        ], $preservedQueryParams);

        if (class_exists(\App\Services\Routing\CompatibilityRouteMap::class) && \App\Services\Routing\CompatibilityRouteMap::hasPage($currentPage)) {
            return \App\Services\Routing\CompatibilityRouteMap::pageUrl($currentPage, $params);
        }

        $params['page'] = $currentPage;
        return '?' . http_build_query($params);
    };

    echo "  <section class=\"central-rekap-toolbar\" aria-label=\"Filter rekap cabang pusat\">\n";
    echo "    <div class=\"central-rekap-toolbar-body\">\n";
    echo "      <div class=\"central-rekap-head\">\n";
    echo "        <div class=\"central-rekap-title-row\">\n";
    echo "          <span class=\"badge warning\">Mode Pusat</span>\n";
    echo "          <span class=\"central-rekap-current\">Rekap aktif: <strong>" . h($selectedBranchLabel) . "</strong>. Klik nama cabang di bawah untuk ganti tampilan.</span>\n";
    echo "        </div>\n";
    echo "      </div>\n";
    echo "      <div class=\"central-rekap-quick\" aria-label=\"Pilih cabang rekap pusat\">\n";
    foreach (central_recap_branch_options() as $branchOption) {
        $branchCode = normalize_central_recap_branch((string) ($branchOption['code'] ?? 'all'));
        $branchLabel = trim((string) ($branchOption['label'] ?? $branchCode));
        if ($branchLabel === '') {
            $branchLabel = $branchCode;
        }
        $chipClass = $branchCode === $selectedBranch ? 'central-rekap-chip active' : 'central-rekap-chip';
        echo "        <a class=\"" . h($chipClass) . "\" href=\"" . h($buildRekapHref($branchCode)) . "\">" . h($branchLabel) . "</a>\n";
    }
    echo "      </div>\n";
    echo "    </div>\n";
    echo "  </section>\n";
}
