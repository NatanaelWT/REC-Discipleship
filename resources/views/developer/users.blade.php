@extends('layouts.rec_app', [
    'title' => 'Developer User',
    'settings' => $settings,
    'currentPage' => 'developer_users',
    'bodyClass' => 'page-developer page-developer-users',
    'showTitle' => false,
])

@section('content')
    @php
      $activeUserCount = $users->filter(static fn ($user): bool => (bool) ($user->is_active ?? true))->count();
      $inactiveUserCount = max(0, $users->count() - $activeUserCount);
    @endphp

    @include('developer._header', [
      'title' => 'Manajemen User',
      'description' => 'Buat akun, atur role dan cabang, serta kelola akses pengguna aplikasi.',
      'eyebrow' => 'Access Management',
      'stats' => [
        ['label' => 'Akun Dikelola', 'value' => number_format($users->count(), 0, ',', '.')],
        ['label' => 'User Aktif', 'value' => number_format($activeUserCount, 0, ',', '.')],
        ['label' => 'User Nonaktif', 'value' => number_format($inactiveUserCount, 0, ',', '.')],
        ['label' => 'Pilihan Role', 'value' => number_format(count($roleOptions), 0, ',', '.')],
      ],
    ])

    @if ($statusCode === 'created')
      <div class="alert success">User dibuat.</div>
    @elseif ($statusCode === 'updated')
      <div class="alert success">User diperbarui.</div>
    @elseif ($statusCode === 'password_reset')
      <div class="alert success">Password user diperbarui.</div>
    @elseif ($statusCode === 'access_returned')
      <div class="alert success">Kembali ke akses developer.</div>
    @elseif ($errorCode !== '')
      <div class="alert danger">{{ $errorMessages[$errorCode] ?? 'Perubahan ditolak.' }}</div>
    @endif

    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon">@include('developer._icon', ['name' => 'users'])</span>
        <div><span class="developer-section-kicker">Akun baru</span><h2>Buat User</h2><p>Tambahkan akun dan tentukan cakupan akses awalnya.</p></div>
      </div>
      <form method="post" action="{{ route('developer.users.store') }}" class="developer-form-grid developer-create-user-form" data-role-aware-user-form>
        @csrf
        <label>
          <span>Username</span>
          <input type="text" name="username" required maxlength="120" autocomplete="off">
        </label>
        <label>
          <span>Password Awal</span>
          <input type="password" name="password" required minlength="6" autocomplete="new-password">
        </label>
        <label data-user-branch-field>
          <span>Cabang Pemuridan</span>
          <select name="branch_id" data-user-branch-select required>
            @foreach ($branchOptions as $branch)
              <option value="{{ $branch['id'] }}">{{ $branch['label'] }}</option>
            @endforeach
          </select>
          <span class="developer-muted" data-user-no-branch hidden>Tanpa cabang</span>
        </label>
        <label>
          <span>Role</span>
          <select name="access_scope" data-user-role-select>
            @foreach ($roleOptions as $roleValue => $label)
              <option value="{{ $roleValue }}">{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Status</span>
          <select name="is_active">
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </select>
        </label>
        <div class="developer-form-actions">
          <button class="btn developer-primary-action" type="submit">@include('developer._icon', ['name' => 'users'])<span>Buat User</span></button>
        </div>
      </form>
    </section>

    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon is-blue">@include('developer._icon', ['name' => 'users'])</span>
        <div><span class="developer-section-kicker">Akun terdaftar</span><h2>Daftar User</h2><p>Buka satu akun untuk mengubah role, cabang, status, atau password.</p></div>
        <span class="developer-count-pill">{{ number_format($users->count(), 0, ',', '.') }} user</span>
      </div>
      <div class="developer-user-list">
        @foreach ($users as $user)
          @php
            $isSelf = current_username() === (string) $user->username;
            $active = (bool) ($user->is_active ?? true);
            $scope = normalize_auth_access_scope((string) ($user->access_scope ?? 'pemuridan_cabang'));
            $requiresBranch = $scope === 'pemuridan_cabang';
            $branchId = $user->branch_id !== null ? (int) $user->branch_id : null;
            $isExpanded = $expandedUserId === (int) $user->getKey();
            $roleLabel = $roleOptions[$scope] ?? ucfirst(str_replace('_', ' ', $scope));
            $branchOption = collect($branchOptions)->firstWhere('id', $branchId);
            $branchLabel = is_array($branchOption) ? (string) ($branchOption['label'] ?? 'Tanpa cabang') : 'Tanpa cabang';
            $userInitial = strtoupper(mb_substr((string) $user->username, 0, 1));
          @endphp
          <details class="developer-user-item" data-developer-user{{ $isExpanded ? ' open' : '' }}>
            <summary class="developer-user-toggle">
              <span class="developer-user-avatar">{{ $userInitial }}</span>
              <span class="developer-user-identity"><strong>{{ $user->username }}</strong><small>{{ $roleLabel }} · {{ $requiresBranch ? $branchLabel : 'Tanpa cabang' }}</small></span>
              <span class="developer-user-status {{ $active ? 'is-active' : 'is-inactive' }}">{{ $active ? 'Aktif' : 'Nonaktif' }}</span>
              @if ($active)
                <form method="post" action="{{ route('developer.users.access', $user) }}" class="developer-access-form" data-developer-access-form>
                  @csrf
                  <button class="developer-access-button" type="submit">Akses</button>
                </form>
              @endif
              <span class="developer-user-chevron" aria-hidden="true"></span>
            </summary>
            <div class="developer-user-detail">
              <div class="developer-user-detail-head"><strong>Pengaturan akses</strong><span>Perubahan langsung berlaku pada login berikutnya.</span></div>
              <form method="post" action="{{ route('developer.users.update', $user) }}" class="developer-user-edit-form" data-role-aware-user-form>
                @csrf
                <label data-user-branch-field>
                  <span>Cabang Pemuridan</span>
                  <select name="branch_id" data-user-branch-select @if (! $requiresBranch) hidden disabled @else required @endif>
                    @foreach ($branchOptions as $branch)
                      <option value="{{ $branch['id'] }}" @selected($branch['id'] === $branchId)>{{ $branch['label'] }}</option>
                    @endforeach
                  </select>
                  <span class="developer-muted" data-user-no-branch @if ($requiresBranch) hidden @endif>Tanpa cabang</span>
                </label>
                <label>
                  <span>Role</span>
                  <select name="access_scope" data-user-role-select>
                    @foreach ($roleOptions as $roleValue => $label)
                      <option value="{{ $roleValue }}" @selected($roleValue === $scope)>{{ $label }}</option>
                    @endforeach
                  </select>
                </label>
                <label>
                  <span>Status</span>
                  <select name="is_active" @if ($isSelf) disabled aria-disabled="true" @endif>
                    <option value="1" @selected($active)>Aktif</option>
                    <option value="0" @selected(! $active)>Nonaktif</option>
                  </select>
                  @if ($isSelf)
                    <input type="hidden" name="is_active" value="1">
                  @endif
                </label>
                <button class="btn secondary" type="submit">Simpan Perubahan</button>
              </form>

              <form method="post" action="{{ route('developer.users.password', $user) }}" class="developer-password-form">
                @csrf
                <div class="developer-password-copy"><strong>Reset password</strong><small>Gunakan minimal 6 karakter.</small></div>
                <label>
                  <span>Password Baru</span>
                  <input type="password" name="password" minlength="6" @if (! $isSelf) required @endif @if ($isSelf) disabled aria-disabled="true" @endif autocomplete="new-password">
                </label>
                <button class="btn ghost" type="submit" @if ($isSelf) disabled aria-disabled="true" @endif>Reset</button>
              </form>
            </div>
          </details>
        @endforeach
      </div>
    </section>

    <script>
      var developerUserItems = document.querySelectorAll('[data-developer-user]');
      document.querySelectorAll('[data-developer-access-form]').forEach(function (form) {
        form.addEventListener('click', function (event) {
          event.stopPropagation();
        });
      });

      developerUserItems.forEach(function (item) {
        item.addEventListener('toggle', function () {
          if (!item.open) {
            return;
          }

          developerUserItems.forEach(function (otherItem) {
            if (otherItem !== item) {
              otherItem.open = false;
            }
          });
        });
      });

      document.querySelectorAll('[data-role-aware-user-form]').forEach(function (form) {
        var roleSelect = form.querySelector('[data-user-role-select]');
        var branchSelect = form.querySelector('[data-user-branch-select]');
        var noBranch = form.querySelector('[data-user-no-branch]');
        if (!roleSelect || !branchSelect || !noBranch) {
          return;
        }

        var syncBranchField = function () {
          var requiresBranch = roleSelect.value === 'pemuridan_cabang';
          branchSelect.hidden = !requiresBranch;
          branchSelect.disabled = !requiresBranch;
          branchSelect.required = requiresBranch;
          noBranch.hidden = requiresBranch;
        };

        roleSelect.addEventListener('change', syncBranchField);
        syncBranchField();
      });
    </script>
@endsection
