<section
  class="discipleship-tab-panel discipleship-workspace__panel discipleship-journal-panel difficult-questions-panel"
  id="discipleship-tabpanel-questions"
  role="tabpanel"
  aria-labelledby="discipleship-tab-questions"
  tabindex="0"
  data-discipleship-tab-panel
  data-tab-key="questions"
  data-page-title="{{ $pageTitle ?? 'Pertanyaan Sulit' }}"
  data-body-class="page-difficult-questions-admin"
>
    @include('discipleship.difficult-questions.partials.alerts')
    @include('discipleship.difficult-questions.partials.hero')
    @include('discipleship.difficult-questions.partials.question-list')
</section>
