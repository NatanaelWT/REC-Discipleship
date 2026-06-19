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
      <form method="post" action="{{ route('developer.users.store') }}" class="developer-form-grid">
        @csrf
        <label>
          <span>Username</span>
          <input type="text" name="username" required maxlength="120" autocomplete="off">
        </label>
        <label>
          <span>Nama</span>
          <input type="text" name="name" required maxlength="120" autocomplete="name">
        </label>
        <label>
          <span>Email</span>
          <input type="email" name="email" required maxlength="255" autocomplete="email">
        </label>
        <label>
          <span>Password Awal</span>
          <input type="password" name="password" required minlength="6" autocomplete="new-password">
        </label>
        <label>
          <span>Cabang</span>
          <select name="branch_code">
            @foreach ($branchOptions as $branch)
              <option value="{{ $branch['code'] }}">{{ $branch['label'] }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Scope</span>
          <select name="access_scope">
            @foreach ($scopeOptions as $scope => $label)
              <option value="{{ $scope }}">{{ $label }}</option>
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
            $scope = normalize_auth_access_scope((string) ($user->access_scope ?? 'branch'));
            $branchCode = normalize_user_branch((string) ($user->branch_code ?? 'kutisari'));
          @endphp
          <div class="developer-user-row">
            <div class="developer-user-meta">
              <strong>{{ $user->username }}</strong>
              <span>{{ auth_access_scope_label($scope) }} &middot; {{ $active ? 'Aktif' : 'Nonaktif' }}</span>
            </div>

            <form method="post" action="{{ route('developer.users.update', $user) }}" class="developer-user-edit-form">
              @csrf
              <label>
                <span>Nama</span>
                <input type="text" name="name" value="{{ $user->name }}" required maxlength="120">
              </label>
              <label>
                <span>Email</span>
                <input type="email" name="email" value="{{ $user->email }}" required maxlength="255">
              </label>
              <label>
                <span>Cabang</span>
                <select name="branch_code">
                  @foreach ($branchOptions as $branch)
                    <option value="{{ $branch['code'] }}" @selected($branch['code'] === $branchCode)>{{ $branch['label'] }}</option>
                  @endforeach
                </select>
              </label>
              <label>
                <span>Scope</span>
                <select name="access_scope">
                  @foreach ($scopeOptions as $scopeValue => $label)
                    <option value="{{ $scopeValue }}" @selected($scopeValue === $scope)>{{ $label }}</option>
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
@endsection
