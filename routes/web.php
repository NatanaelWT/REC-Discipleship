<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Developer\DeveloperConfigController;
use App\Http\Controllers\Developer\DeveloperController;
use App\Http\Controllers\Developer\DeveloperUserController;
use App\Http\Controllers\Discipleship\DashboardController as DiscipleshipDashboardController;
use App\Http\Controllers\Discipleship\DifficultQuestionController as DiscipleshipDifficultQuestionController;
use App\Http\Controllers\Discipleship\GroupController as DiscipleshipGroupController;
use App\Http\Controllers\Discipleship\MeetingReportRecapController as DiscipleshipMeetingReportRecapController;
use App\Http\Controllers\Discipleship\MskParticipantController as DiscipleshipMskParticipantController;
use App\Http\Controllers\Discipleship\PeopleListController as DiscipleshipPeopleListController;
use App\Http\Controllers\Discipleship\PeopleTreeController as DiscipleshipPeopleTreeController;
use App\Http\Controllers\Discipleship\SpiritualJourneyController as DiscipleshipSpiritualJourneyController;
use App\Http\Controllers\Discipleship\TargetController as DiscipleshipTargetController;
use App\Http\Controllers\Files\SecureFileController;
use App\Http\Controllers\PublicPortal\DgMeetingReportController as PublicDgMeetingReportController;
use App\Http\Controllers\PublicPortal\DgReportBranchController as PublicDgReportBranchController;
use App\Http\Controllers\PublicPortal\DifficultAnswerController as PublicDifficultAnswerController;
use App\Http\Controllers\PublicPortal\DifficultQuestionController as PublicDifficultQuestionController;
use App\Http\Controllers\PublicPortal\HomeController as PublicHomeController;
use App\Http\Controllers\PublicPortal\MaterialController as PublicMaterialController;
use App\Http\Controllers\PublicPortal\MaterialDownloadController as PublicMaterialDownloadController;
use App\Http\Controllers\PublicPortal\MaterialPreviewController as PublicMaterialPreviewController;
use App\Http\Controllers\PublicPortal\MemberFeedbackJournalController as PublicMemberFeedbackJournalController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Worship\ServiceScheduleController as WorshipServiceScheduleController;
use App\Http\Controllers\Worship\ServiceScheduleImageController as WorshipServiceScheduleImageController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group([], function (): void {
    Route::get('/', [PublicHomeController::class, 'index'])->name('home');
    Route::get('/index.php', static fn (): RedirectResponse => redirect()->route('home'))->name('index.redirect');

    Route::get('/login', [LoginController::class, 'show'])->name('auth.login');
    Route::post('/login', [LoginController::class, 'store'])->name('auth.login.store');
    Route::post('/logout', [LogoutController::class, 'destroy'])->middleware('rec.auth')->name('auth.logout');
    Route::get('/pengaturan', [SettingsController::class, 'index'])->middleware('rec.page:settings')->name('settings');
    Route::post('/pengaturan', [SettingsController::class, 'update'])->middleware('rec.page:settings')->name('settings.update');

    Route::prefix('developer')->name('developer.')->middleware('rec.page:developer_dashboard')->group(function (): void {
        Route::get('/', [DeveloperController::class, 'index'])->name('dashboard');
        Route::get('/users', [DeveloperUserController::class, 'index'])->name('users');
        Route::post('/users', [DeveloperUserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}', [DeveloperUserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/password', [DeveloperUserController::class, 'resetPassword'])->name('users.password');
        Route::get('/config', [DeveloperConfigController::class, 'index'])->name('config');
        Route::post('/config', [DeveloperConfigController::class, 'update'])->name('config.update');
    });

    Route::prefix('publik')->name('public.')->group(function (): void {
        Route::get('/jurnal-dg', [PublicDgReportBranchController::class, 'index'])->name('dg.branch');
        Route::get('/jurnal-dg/laporan', [PublicDgMeetingReportController::class, 'redirectToBranchReport'])->name('dg.report.redirect');
        Route::post('/jurnal-dg/laporan', [PublicDgMeetingReportController::class, 'store'])->name('dg.report.redirect-store');
        Route::get('/jurnal-dg/{branch}/laporan', [PublicDgMeetingReportController::class, 'create'])
            ->where('branch', '[A-Za-z0-9_-]+')
            ->name('dg.report');
        Route::post('/jurnal-dg/{branch}/laporan', [PublicDgMeetingReportController::class, 'store'])
            ->where('branch', '[A-Za-z0-9_-]+')
            ->name('dg.report.store');
        Route::get('/umpan-balik-anggota', [PublicMemberFeedbackJournalController::class, 'selectBranch'])->name('member-feedback.branch');
        Route::get('/umpan-balik-anggota/form', [PublicMemberFeedbackJournalController::class, 'create'])->name('member-feedback.form');
        Route::post('/umpan-balik-anggota/form', [PublicMemberFeedbackJournalController::class, 'store'])->name('member-feedback.store');
        Route::get('/pertanyaan-sulit/kirim', [PublicDifficultQuestionController::class, 'create'])->name('difficult-question.submit');
        Route::post('/pertanyaan-sulit/kirim', [PublicDifficultQuestionController::class, 'store'])->name('difficult-question.store');
        Route::get('/pertanyaan-sulit/jawaban', [PublicDifficultAnswerController::class, 'show'])->name('difficult-question.answer');
        Route::post('/pertanyaan-sulit/jawaban', [PublicDifficultAnswerController::class, 'lookup'])->name('difficult-question.lookup');
        Route::get('/menu-kosong', [PublicHomeController::class, 'emptyMenu'])->name('menu-empty');
    });

    Route::prefix('materi')->name('materials.')->group(function (): void {
        Route::get('/', [PublicMaterialController::class, 'redirectToMenu'])->name('index');
        Route::get('/preview', [PublicMaterialPreviewController::class, 'redirectToPreview'])->name('preview.redirect');
        Route::get('/download', [PublicMaterialDownloadController::class, 'redirectToDownload'])->name('download.redirect');
        Route::get('/{menu}', [PublicMaterialController::class, 'show'])->name('show');
        Route::post('/{menu}/upload', [PublicMaterialController::class, 'upload'])->name('upload');
        Route::post('/{menu}/{churchFile}/rename', [PublicMaterialController::class, 'rename'])->name('rename');
        Route::get('/{menu}/{churchFile}/preview', [PublicMaterialPreviewController::class, 'show'])->name('preview');
        Route::get('/{menu}/{churchFile}/download', [PublicMaterialDownloadController::class, 'download'])->name('download');
    });

    Route::prefix('pemuridan')->name('discipleship.')->middleware('rec.auth')->group(function (): void {
        Route::get('/dashboard', [DiscipleshipDashboardController::class, 'index'])->middleware('rec.page:discipleship_dashboard')->name('dashboard');
        Route::get('/dashboard/sections/{section}', [DiscipleshipDashboardController::class, 'section'])
            ->whereIn('section', ['incomplete-msk', 'overdue-groups', 'branch-breakdown'])
            ->middleware('rec.page:discipleship_dashboard')
            ->name('dashboard.section');
        Route::post('/dashboard/msk-sessions', [DiscipleshipDashboardController::class, 'updateMskSessions'])->middleware('rec.page:discipleship_dashboard')->name('dashboard.msk-sessions');
        Route::get('/kelompok', [DiscipleshipGroupController::class, 'index'])->middleware('rec.page:groups_list')->name('groups');
        Route::get('/orang', static function (Request $request): RedirectResponse {
            return redirect()->route('discipleship.tree', $request->query());
        })->name('people');
        Route::get('/anggota', [DiscipleshipPeopleListController::class, 'index'])->middleware('rec.page:people_list')->name('people-list');
        Route::get('/pohon', [DiscipleshipPeopleTreeController::class, 'index'])->middleware('rec.page:people_tree')->name('tree');
        Route::post('/pohon', [DiscipleshipPeopleTreeController::class, 'handleFormAction'])->middleware('rec.page:people_tree')->name('tree.action');
        Route::get('/pohon-v2', [DiscipleshipPeopleTreeController::class, 'treeV2'])->middleware('rec.page:people_tree')->name('tree-v2');
        Route::post('/pohon/orang', [DiscipleshipPeopleTreeController::class, 'savePerson'])->middleware('rec.page:people_tree')->name('tree.people.save');
        Route::post('/pohon/orang/hapus', [DiscipleshipPeopleTreeController::class, 'deletePerson'])->middleware('rec.page:people_tree')->name('tree.people.delete');
        Route::post('/pohon/kelompok', [DiscipleshipPeopleTreeController::class, 'saveGroup'])->middleware('rec.page:people_tree')->name('tree.groups.save');
        Route::post('/pohon/kelompok/keluar', [DiscipleshipPeopleTreeController::class, 'leavePersonGroup'])->middleware('rec.page:people_tree')->name('tree.groups.leave');
        Route::post('/pohon/kelompok/selesai', [DiscipleshipPeopleTreeController::class, 'completeGroup'])->middleware('rec.page:people_tree')->name('tree.groups.complete');
        Route::post('/pohon/kelompok/aktifkan', [DiscipleshipPeopleTreeController::class, 'reactivateGroup'])->middleware('rec.page:people_tree')->name('tree.groups.reactivate');
        Route::post('/pohon/export-dot', [DiscipleshipPeopleTreeController::class, 'exportDot'])->middleware('rec.page:people_tree')->name('tree.export-dot');
        Route::get('/spiritual-journey', [DiscipleshipSpiritualJourneyController::class, 'index'])->middleware('rec.page:spiritual_journey')->name('spiritual-journey');
        Route::post('/spiritual-journey', [DiscipleshipSpiritualJourneyController::class, 'updateBridgeStatusFromForm'])->middleware('rec.page:spiritual_journey')->name('spiritual-journey.bridge-status-form');
        Route::post('/spiritual-journey/{participant}/bridge-status', [DiscipleshipSpiritualJourneyController::class, 'updateBridgeStatus'])->middleware('rec.page:spiritual_journey')->name('spiritual-journey.bridge-status');
        Route::get('/laporan-dg', [DiscipleshipMeetingReportRecapController::class, 'index'])->middleware('rec.page:dg_reports_recap')->name('reports-recap');
        Route::get('/msk', [DiscipleshipMskParticipantController::class, 'index'])->middleware('rec.page:msk_classes')->name('msk-classes');
        Route::post('/msk/peserta', [DiscipleshipMskParticipantController::class, 'store'])->middleware('rec.page:msk_classes')->name('msk-classes.store');
        Route::post('/msk/impor', [DiscipleshipMskParticipantController::class, 'import'])->middleware('rec.page:msk_classes')->name('msk-classes.import');
        Route::post('/msk/ekspor', [DiscipleshipMskParticipantController::class, 'export'])->middleware('rec.page:msk_classes')->name('msk-classes.export');
        Route::post('/msk/{participant}/sesi', [DiscipleshipMskParticipantController::class, 'updateSessions'])->middleware('rec.page:msk_classes')->name('msk-classes.sessions');
        Route::post('/msk/{participant}/nonaktif', [DiscipleshipMskParticipantController::class, 'deactivate'])->middleware('rec.page:msk_classes')->name('msk-classes.deactivate');
        Route::post('/msk/{participant}/aktif', [DiscipleshipMskParticipantController::class, 'reactivate'])->middleware('rec.page:msk_classes')->name('msk-classes.reactivate');
        Route::get('/target', [DiscipleshipTargetController::class, 'index'])->middleware('rec.page:discipleship_targets')->name('targets');
        Route::post('/target', [DiscipleshipTargetController::class, 'update'])->middleware('rec.page:discipleship_targets')->name('targets.update');
        Route::get('/pertanyaan-sulit', [DiscipleshipDifficultQuestionController::class, 'index'])->middleware('rec.page:difficult_questions_admin')->name('difficult-questions');
        Route::post('/pertanyaan-sulit', [DiscipleshipDifficultQuestionController::class, 'answerFromForm'])->middleware('rec.page:difficult_questions_admin')->name('difficult-questions.answer-form');
        Route::post('/pertanyaan-sulit/{difficultQuestion}/jawaban', [DiscipleshipDifficultQuestionController::class, 'answer'])->middleware('rec.page:difficult_questions_admin')->name('difficult-questions.answer');
    });

    Route::prefix('ibadah')->name('worship.')->middleware('rec.page:worship_penatalayan')->group(function (): void {
        Route::get('/penatalayan', [WorshipServiceScheduleController::class, 'index'])->name('penatalayan');
        Route::post('/penatalayan', [WorshipServiceScheduleController::class, 'store'])->name('penatalayan.store');
        Route::get('/penatalayan/gambar', [WorshipServiceScheduleImageController::class, 'download'])->name('penatalayan.image');
        Route::delete('/penatalayan/{month}', [WorshipServiceScheduleController::class, 'destroy'])
            ->where('month', '[0-9]{4}-[0-9]{2}')
            ->name('penatalayan.destroy');
    });

    Route::get('/file-aman', [SecureFileController::class, 'show'])->name('secure-file.show');
});
