@include('discipleship.partials.page-header', [
    'header' => [
        'kicker' => 'Admin Pemuridan',
        'title' => 'Pertanyaan Sulit',
        'description' => 'Pantau pertanyaan dari halaman publik, lalu isi jawaban agar pengirim bisa membukanya dengan password yang mereka buat.',
        'stats' => [
            ['label' => 'Menunggu', 'value' => (string) $pendingQuestionCount],
            ['label' => 'Dijawab', 'value' => (string) $answeredQuestionCount],
            ['label' => 'Dengan WA', 'value' => (string) $whatsappQuestionCount],
            ['label' => 'Total', 'value' => (string) $totalQuestionCount],
        ],
        'tools' => [
            'element' => 'form',
            'method' => 'get',
            'action' => route('discipleship.difficult-questions'),
            'attributes' => ['data-auto-submit-search-form' => true],
            'partial' => 'discipleship.partials.page-header-controls.difficult-questions',
            'data' => compact('questionMonthFilter', 'questionSearch'),
        ],
    ],
])
