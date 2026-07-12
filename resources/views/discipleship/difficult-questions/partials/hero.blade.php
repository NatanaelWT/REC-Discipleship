@include('discipleship.partials.page-header', [
    'header' => [
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
