@php
    use Illuminate\View\ComponentAttributeBag;

    $header = is_array($header ?? null) ? $header : [];
    $headerStats = is_array($header['stats'] ?? null) ? $header['stats'] : [];
    $headerTools = is_array($header['tools'] ?? null) ? $header['tools'] : [];
    $headerTitle = is_array($header['title_content'] ?? null) ? $header['title_content'] : [];
    $headerAfterCopy = is_array($header['after_copy'] ?? null) ? $header['after_copy'] : [];
    $headerAside = is_array($header['aside'] ?? null) ? $header['aside'] : [];
    $headerAttributes = new ComponentAttributeBag(
        is_array($header['attributes'] ?? null) ? $header['attributes'] : [],
    );
    $toolsPartial = trim((string) ($headerTools['partial'] ?? ''));
    $toolsData = is_array($headerTools['data'] ?? null) ? $headerTools['data'] : [];
    $titlePartial = trim((string) ($headerTitle['partial'] ?? ''));
    $titleData = is_array($headerTitle['data'] ?? null) ? $headerTitle['data'] : [];
    $afterCopyPartial = trim((string) ($headerAfterCopy['partial'] ?? ''));
    $afterCopyData = is_array($headerAfterCopy['data'] ?? null) ? $headerAfterCopy['data'] : [];
    $asidePartial = trim((string) ($headerAside['partial'] ?? ''));
    $asideData = is_array($headerAside['data'] ?? null) ? $headerAside['data'] : [];
    $toolsAttributes = new ComponentAttributeBag(
        is_array($headerTools['attributes'] ?? null) ? $headerTools['attributes'] : [],
    );
@endphp

<section {{ $headerAttributes->merge(['class' => 'card discipleship-page-header']) }}>
  <div class="discipleship-page-header__main">
    <div class="discipleship-page-header__copy">
      <div class="discipleship-page-header__kicker">{{ $header['kicker'] ?? '' }}</div>
      <h1>
        @if ($titlePartial !== '')
          @include($titlePartial, $titleData)
        @else
          {{ $header['title'] ?? '' }}
        @endif
      </h1>
      <p>{{ $header['description'] ?? '' }}</p>
      @if ($afterCopyPartial !== '')
        @include($afterCopyPartial, $afterCopyData)
      @endif
    </div>

    @if ($asidePartial !== '')
      @include($asidePartial, $asideData)
    @elseif (count($headerStats) > 0)
      <div class="discipleship-page-header__stats" aria-label="{{ $header['stats_aria_label'] ?? ('Ringkasan '.$header['title']) }}">
        @foreach ($headerStats as $stat)
          @php
              $stat = is_array($stat) ? $stat : [];
              $statType = ($stat['type'] ?? 'text') === 'button' ? 'button' : 'text';
              $statAttributes = new ComponentAttributeBag(
                  is_array($stat['attributes'] ?? null) ? $stat['attributes'] : [],
              );
              $valueAttributes = new ComponentAttributeBag(
                  is_array($stat['value_attributes'] ?? null) ? $stat['value_attributes'] : [],
              );
          @endphp

          @if ($statType === 'button')
            <button {{ $statAttributes->merge(['class' => 'discipleship-page-header__stat is-action', 'type' => 'button']) }}>
              <span class="discipleship-page-header__stat-label">{{ $stat['label'] ?? '' }}</span>
              <strong {{ $valueAttributes->merge(['class' => 'discipleship-page-header__stat-value']) }}>{{ $stat['value'] ?? '' }}</strong>
            </button>
          @else
            <div {{ $statAttributes->merge(['class' => 'discipleship-page-header__stat']) }}>
              <span class="discipleship-page-header__stat-label">{{ $stat['label'] ?? '' }}</span>
              <strong {{ $valueAttributes->merge(['class' => 'discipleship-page-header__stat-value']) }}>{{ $stat['value'] ?? '' }}</strong>
            </div>
          @endif
        @endforeach
      </div>
    @endif
  </div>

  @if ($toolsPartial !== '')
    @if (($headerTools['element'] ?? 'div') === 'form')
      <form {{ $toolsAttributes->merge([
          'class' => 'actions discipleship-page-header__tools',
          'method' => $headerTools['method'] ?? 'get',
          'action' => $headerTools['action'] ?? '',
      ]) }}>
        @include($toolsPartial, $toolsData)
      </form>
    @else
      <div {{ $toolsAttributes->merge(['class' => 'actions discipleship-page-header__tools']) }}>
        @include($toolsPartial, $toolsData)
      </div>
    @endif
  @endif
</section>
