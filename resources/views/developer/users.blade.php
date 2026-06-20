@extends('layouts.rec_app', [
    'title' => 'Developer User',
    'settings' => $settings,
    'currentPage' => 'developer_users',
    'bodyClass' => 'page-developer page-developer-users',
])

@section('content')
    @if ($statusCode === 'created')
      <div class="alert success">User dibuat.</div>
    @elseif ($statusCode === 'updated')
      <div class="alert success">User diperbarui.</div>
    @elseif ($statusCode === 'password_reset')
      <div class="alert success">Password user diperbarui.</div>
    @elseif ($errorCode !== '')
      <div class="alert danger">{{ $errorMessages[$errorCode] ?? 'Perubahan ditolak.' }}</div>
    @endif

    <section class="card developer-panel">
      <div class="card-row">
        <h2>Buat User</h2>
      </div>
      <form method="post" action="{{ route('developer.users.store') }}" class="developer-form-grid" data-role-aware-user-form>
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
          <button class="btn" type="submit">Buat User</button>
        </div>
      </form>
    </section>

    <section class="card developer-panel">
      <div class="card-row">
        <h2>Daftar User</h2>
      </div>
      <div class="developer-user-list">
        @foreach ($users as $user)
          @php
            $isSelf = current_username() === (string) $user->username;
            $active = (bool) ($user->is_active ?? true);
            $scope = normalize_auth_access_scope((string) ($user->access_scope ?? 'pemuridan_cabang'));
            $requiresBranch = $scope === 'pemuridan_cabang';
            $branchId = $user->branch_id !== null ? (int) $user->branch_id : null;
            $branchCode = branch_slug_from_id($branchId);
          @endphp
          <div class="developer-user-row">
            <div class="developer-user-meta">
              <strong>{{ $user->username }}</strong>
              <span>{{ auth_access_scope_label($scope) }} &middot; {{ $requiresBranch ? user_branch_label($branchCode) : 'Tanpa cabang' }} &middot; {{ $active ? 'Aktif' : 'Nonaktif' }}</span>
            </div>

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
              <button class="btn secondary" type="submit">Simpan</button>
            </form>

            <form method="post" action="{{ route('developer.users.password', $user) }}" class="developer-password-form">
              @csrf
              <label>
                <span>Password Baru</span>
                <input type="password" name="password" minlength="6" @if (! $isSelf) required @endif @if ($isSelf) disabled aria-disabled="true" @endif autocomplete="new-password">
              </label>
              <button class="btn ghost" type="submit" @if ($isSelf) disabled aria-disabled="true" @endif>Reset</button>
            </form>
          </div>
        @endforeach
      </div>
    </section>

    <script>
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
