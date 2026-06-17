@php
    $fileId = trim((string) ($row['id'] ?? ''));
    $title = trim((string) ($row['title'] ?? ''));
    if ($title === '') {
        $title = trim((string) ($row['file_name'] ?? 'Dokumen'));
    }
    if ($title === '') {
        $title = 'Dokumen';
    }
    $path = sanitize_relative_upload_path((string) ($row['path'] ?? ''));
    $description = trim((string) ($row['description'] ?? ''));
    $sizeLabel = format_file_size(max(0, (int) ($row['size'] ?? 0)));
    $ext = secure_file_extension($path);
    $extLabel = strtoupper($ext !== '' ? $ext : 'FILE');
    $isPreviewable = is_public_material_previewable_path($path);
@endphp

@if ($fileId !== '')
  <article class="public-material-item">
    <div class="public-material-top">
      <div class="public-material-title">{{ $title }}</div>
      <span class="public-material-ext">{{ $extLabel }}</span>
    </div>
    @if ($description !== '')
      <div class="public-material-desc">{{ $description }}</div>
    @endif
    <div class="public-material-meta">{{ $sizeLabel }}</div>
    <div class="public-material-actions">
      @if ($isPreviewable)
        <a class="btn tiny ghost" href="{{ route('materials.preview', ['menu' => $menu, 'churchFile' => $fileId]) }}" target="_blank" rel="noopener">Lihat</a>
      @endif
      <a class="btn tiny secondary" href="{{ route('materials.download', ['menu' => $menu, 'churchFile' => $fileId]) }}">Unduh</a>
    </div>
    @if (! empty($canManageMaterials))
      <form class="public-material-rename-form" method="post" action="{{ route('materials.rename', ['menu' => $menu, 'churchFile' => $fileId]) }}">
        <input class="public-material-rename-input" type="text" name="title" value="{{ $title }}" maxlength="180" required>
        <button class="btn tiny" type="submit">Simpan nama</button>
      </form>
    @endif
  </article>
@endif
