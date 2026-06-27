@php
    $renderAttributes = static function (array $attributes): string {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            if (is_int($name)) {
                $html .= ' '.e((string) $value);
                continue;
            }

            $html .= ' '.e((string) $name);

            if ($value === true) {
                continue;
            }

            $html .= '="'.e((string) $value).'"';
        }

        return $html;
    };

    $allowedSizes = ['standard', 'wide', 'media', 'compact'];
    $modalSize = in_array((string) ($size ?? 'standard'), $allowedSizes, true) ? (string) ($size ?? 'standard') : 'standard';

    $modalAttrs = is_array($modalAttrs ?? null) ? $modalAttrs : [];
    if (isset($id) && (string) $id !== '' && ! array_key_exists('id', $modalAttrs)) {
        $modalAttrs = ['id' => (string) $id] + $modalAttrs;
    }
    $modalAttrs['aria-hidden'] = $modalAttrs['aria-hidden'] ?? 'true';
    $modalAttrs['role'] = $modalAttrs['role'] ?? 'dialog';
    $modalAttrs['aria-modal'] = $modalAttrs['aria-modal'] ?? 'true';
    $modalClass = trim('modal '.(string) ($modalAttrs['class'] ?? ''));
    unset($modalAttrs['class']);
    $modalAttrs = ['class' => $modalClass] + $modalAttrs;

    $cardClasses = trim('modal-card modal-card--'.$modalSize.' '.(string) ($cardClass ?? ''));

    $titleAttrs = is_array($titleAttrs ?? null) ? $titleAttrs : [];
    $titleClass = trim('modal-title '.(string) ($titleAttrs['class'] ?? ''));
    unset($titleAttrs['class']);
    $titleAttrs = ['class' => $titleClass] + $titleAttrs;

    $closeAttrs = is_array($closeAttrs ?? null) ? $closeAttrs : [];
    $closeAttrs['aria-label'] = $closeAttrs['aria-label'] ?? 'Tutup';
    $closeClass = trim('btn tiny ghost '.(string) ($closeAttrs['class'] ?? ''));
    $closeType = $closeAttrs['type'] ?? 'button';
    unset($closeAttrs['class'], $closeAttrs['type']);
    $closeAttrs = ['class' => $closeClass, 'type' => $closeType] + $closeAttrs;

    $bodyAttrs = is_array($bodyAttrs ?? null) ? $bodyAttrs : [];
    $bodyClassValue = trim('modal-body '.(string) ($bodyClass ?? '').' '.(string) ($bodyAttrs['class'] ?? ''));
    unset($bodyAttrs['class']);
    $bodyAttrs = ['class' => $bodyClassValue] + $bodyAttrs;

    $modalTitle = (string) ($title ?? '');
    $modalCloseLabel = (string) ($closeLabel ?? '&times;');
    $modalSubtitleHtml = (string) ($subtitleHtml ?? '');
    $modalBodyHtml = (string) ($bodyHtml ?? '');
    $modalFooterHtml = (string) ($footerHtml ?? '');
@endphp

<div{!! $renderAttributes($modalAttrs) !!}>
  <div class="{{ $cardClasses }}">
    <div class="modal-head">
      <div class="modal-heading">
        <div{!! $renderAttributes($titleAttrs) !!}>{{ $modalTitle }}</div>
        @if ($modalSubtitleHtml !== '')
          {!! $modalSubtitleHtml !!}
        @endif
      </div>
      <button{!! $renderAttributes($closeAttrs) !!}>{!! $modalCloseLabel !!}</button>
    </div>
    <div{!! $renderAttributes($bodyAttrs) !!}>
      {!! $modalBodyHtml !!}
    </div>
    @if ($modalFooterHtml !== '')
      <div class="modal-actions modal-footer">
        {!! $modalFooterHtml !!}
      </div>
    @endif
  </div>
</div>
