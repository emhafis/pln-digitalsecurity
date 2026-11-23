<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AparController;
use App\Http\Controllers\Admin\AparInspectionController;
use App\Http\Controllers\Admin\AparDocumentController;
use App\Http\Controllers\Admin\GuestBookController as AdminGuestBookController;
use App\Http\Controllers\Admin\GuestBookQrController;
use App\Http\Controllers\Admin\AttendanceCorrectionController;
use App\Http\Controllers\Admin\AttendanceGenerationController;
use App\Http\Controllers\Admin\AttendanceReadinessController;
use App\Http\Controllers\Admin\AttendanceRecordController;
use App\Http\Controllers\Admin\AttendanceSettingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HydrantController;
use App\Http\Controllers\Admin\HydrantDocumentController;
use App\Http\Controllers\Admin\HydrantInspectionController;
use App\Http\Controllers\Admin\PatrolPointController;
use App\Http\Controllers\Admin\PatrolReportController;
use App\Http\Controllers\Admin\PatrolSessionController;
use App\Http\Controllers\Admin\ScheduleCalendarController;


use App\Http\Controllers\Admin\SchedulePatternController;
use App\Http\Controllers\Admin\SchedulePeriodController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SecurityPostController;
use App\Http\Controllers\Admin\SecurityTeamController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\App\AnnouncementController as AppAnnouncementController;
use App\Http\Controllers\App\AparController as AppAparController;
use App\Http\Controllers\App\AparInspectionController as AppAparInspectionController;
use App\Http\Controllers\App\AparDocumentController as AppAparDocumentController;
use App\Http\Controllers\App\AttendanceController as AppAttendanceController;
use App\Http\Controllers\App\DashboardController as AppDashboardController;
use App\Http\Controllers\App\HydrantController as AppHydrantController;
use App\Http\Controllers\App\HydrantDocumentController as AppHydrantDocumentController;
use App\Http\Controllers\App\HydrantInspectionController as AppHydrantInspectionController;
use App\Http\Controllers\App\PatrolController as AppPatrolController;
use App\Http\Controllers\App\ProfileController as AppProfileController;
use App\Http\Controllers\App\QrScanController as AppQrScanController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Guest\GuestBookController;
use App\Http\Controllers\Guest\GuestCheckOutController;
use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/storage/{path}', [StorageController::class, 'show'])
    ->where('path', '.*')
    ->middleware(['web', 'auth'])
    ->name('storage.show');

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();

        if ($user->role === 'superadmin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'security') {
            return redirect()->route('app.dashboard');
        }

        abort(403);
    }

    return Inertia::render('Auth/Login');
})->name('home');

Route::middleware('throttle:guest-checkin-form')->group(function () {
    Route::get('/guest-book/success', [GuestBookController::class, 'success'])->name('guest-book.success');
});

Route::middleware('throttle:guest-checkout-form')->group(function () {
    Route::get('/guest-book/checkout/success', [GuestCheckOutController::class, 'success'])->name('guest-book.checkout.success');
    Route::get('/guest-book/checkout/{uuid}', [GuestCheckOutController::class, 'show'])->name('guest-book.checkout.show');
});
Route::middleware('throttle:guest-checkout-submit')->group(function () {
    Route::post('/guest-book/checkout/{uuid}/lookup', [GuestCheckOutController::class, 'lookup'])->name('guest-book.checkout.lookup');
    Route::post('/guest-book/checkout/{uuid}/confirm', [GuestCheckOutController::class, 'confirm'])->name('guest-book.checkout.confirm');
});

Route::middleware('throttle:guest-checkin-form')->group(function () {
    Route::get('/guest-book/{uuid}', [GuestBookController::class, 'show'])->name('guest-book.show');
});
Route::post('/guest-book/{uuid}', [GuestBookController::class, 'store'])
    ->middleware('throttle:guest-checkin-submit')
    ->name('guest-book.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('dashboard', function () {
        $user = request()->user();

        if ($user->role === 'superadmin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'security') {
            return redirect()->route('app.dashboard');
        }

        abort(403);
    })->name('dashboard');

    Route::middleware(['role:admin,superadmin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/security-post', [SecurityPostController::class, 'index'])->name('security-post.index');
        Route::post('/security-post', [SecurityPostController::class, 'store'])->name('security-post.store');
        Route::get('/security-post/create', [SecurityPostController::class, 'create'])->name('security-post.create');
        Route::get('/security-post/{id}/edit', [SecurityPostController::class, 'edit'])->whereNumber('id')->name('security-post.edit');
        Route::put('/security-post/{id}', [SecurityPostController::class, 'update'])->whereNumber('id')->name('security-post.update');
        Route::delete('/security-post/{id}', [SecurityPostController::class, 'destroy'])->whereNumber('id')->name('security-post.destroy');
        Route::get('/security-post/{id}', [SecurityPostController::class, 'show'])->whereNumber('id')->name('security-post.show');

        Route::get('/security-team', [SecurityTeamController::class, 'index'])->name('security-team.index');
        Route::post('/security-team', [SecurityTeamController::class, 'store'])->name('security-team.store');
        Route::get('/security-team/create', [SecurityTeamController::class, 'create'])->name('security-team.create');
        Route::get('/security-team/{id}/edit', [SecurityTeamController::class, 'edit'])->whereNumber('id')->name('security-team.edit');
        Route::put('/security-team/{id}', [SecurityTeamController::class, 'update'])->whereNumber('id')->name('security-team.update');
        Route::patch('/security-team/{id}/toggle', [SecurityTeamController::class, 'toggle'])->whereNumber('id')->name('security-team.toggle');
        Route::post('/security-team/{id}/member', [SecurityTeamController::class, 'storeMember'])->whereNumber('id')->name('security-team.member.store');
        Route::put('/security-team/{id}/member/{memberId}/end', [SecurityTeamController::class, 'endMember'])->whereNumber('id')->whereNumber('memberId')->name('security-team.member.end');
        Route::delete('/security-team/{id}', [SecurityTeamController::class, 'destroy'])->whereNumber('id')->name('security-team.destroy');
        Route::get('/security-team/{id}', [SecurityTeamController::class, 'show'])->whereNumber('id')->name('security-team.show');

        Route::get('/shift', [ShiftController::class, 'index'])->name('shift.index');
        Route::post('/shift', [ShiftController::class, 'store'])->name('shift.store');
        Route::get('/shift/create', [ShiftController::class, 'create'])->name('shift.create');
        Route::get('/shift/{id}/edit', [ShiftController::class, 'edit'])->whereNumber('id')->name('shift.edit');
        Route::put('/shift/{id}', [ShiftController::class, 'update'])->whereNumber('id')->name('shift.update');
        Route::delete('/shift/{id}', [ShiftController::class, 'destroy'])->whereNumber('id')->name('shift.destroy');
        Route::get('/shift/{id}', [ShiftController::class, 'show'])->whereNumber('id')->name('shift.show');

        Route::get('/schedule-pattern', [SchedulePatternController::class, 'index'])->name('schedule-pattern.index');
        Route::post('/schedule-pattern', [SchedulePatternController::class, 'store'])->name('schedule-pattern.store');
        Route::get('/schedule-pattern/create', [SchedulePatternController::class, 'create'])->name('schedule-pattern.create');
        Route::get('/schedule-pattern/{id}/edit', [SchedulePatternController::class, 'edit'])->whereNumber('id')->name('schedule-pattern.edit');
        Route::put('/schedule-pattern/{id}', [SchedulePatternController::class, 'update'])->whereNumber('id')->name('schedule-pattern.update');
        Route::delete('/schedule-pattern/{id}', [SchedulePatternController::class, 'destroy'])->whereNumber('id')->name('schedule-pattern.destroy');
        Route::get('/schedule-pattern/{id}', [SchedulePatternController::class, 'show'])->whereNumber('id')->name('schedule-pattern.show');

        Route::get('/guest-book', [AdminGuestBookController::class, 'index'])->name('guest-book.index');
        Route::get('/guest-book/{id}', [AdminGuestBookController::class, 'show'])->whereNumber('id')->name('guest-book.show');
        Route::post('/guest-book/{id}/checkout', [AdminGuestBookController::class, 'checkout'])->whereNumber('id')->name('guest-book.checkout');
        Route::get('/guest-book/{id}/file/{type}', [AdminGuestBookController::class, 'file'])->whereNumber('id')->name('guest-book.file');

        Route::get('/schedule-period', [SchedulePeriodController::class, 'index'])->name('schedule-period.index');
        Route::post('/schedule-period', [SchedulePeriodController::class, 'store'])->name('schedule-period.store');
        Route::get('/schedule-period/create', [SchedulePeriodController::class, 'create'])->name('schedule-period.create');
        Route::get('/schedule-period/bulk-create', [SchedulePeriodController::class, 'bulkCreate'])->name('schedule-period.bulk-create');
        Route::post('/schedule-period/bulk', [SchedulePeriodController::class, 'bulkStore'])->name('schedule-period.bulk-store');
        Route::post('/schedule-period/{id}/activate', [SchedulePeriodController::class, 'activate'])->whereNumber('id')->name('schedule-period.activate');
        Route::get('/schedule-period/{id}/generate', [SchedulePeriodController::class, 'generatePreview'])->whereNumber('id')->name('schedule-period.generate.preview');
        Route::post('/schedule-period/{id}/generate', [SchedulePeriodController::class, 'generate'])->whereNumber('id')->name('schedule-period.generate');
        Route::get('/schedule-period/{id}/edit', [SchedulePeriodController::class, 'edit'])->whereNumber('id')->name('schedule-period.edit');
        Route::put('/schedule-period/{id}', [SchedulePeriodController::class, 'update'])->whereNumber('id')->name('schedule-period.update');
        Route::patch('/schedule-period/{id}/status', [SchedulePeriodController::class, 'updateStatus'])->whereNumber('id')->name('schedule-period.status');
        Route::get('/schedule-period/{id}/delete-summary', [SchedulePeriodController::class, 'deleteSummary'])->whereNumber('id')->name('schedule-period.delete-summary');
        Route::delete('/schedule-period/{id}', [SchedulePeriodController::class, 'destroy'])->whereNumber('id')->name('schedule-period.destroy');
        Route::get('/schedule-period/{id}', [SchedulePeriodController::class, 'show'])->whereNumber('id')->name('schedule-period.show');


        Route::get('/schedule-calendar', [ScheduleCalendarController::class, 'index'])->name('schedule-calendar.index');

        Route::get('/patrol-session', [PatrolSessionController::class, 'index'])->name('patrol-session.index');
        Route::get('/patrol-session/{patrolSession}', [PatrolSessionController::class, 'show'])->name('patrol-session.show');
        Route::get('/patrol-session/{patrolSession}/scans/{scan}/photo', [PatrolSessionController::class, 'scanPhoto'])->name('patrol-session.scan.photo');
        Route::get('/patrol-session/{patrolSession}/scans/{scan}/photos/{photo}', [PatrolSessionController::class, 'scanPhotoItem'])->name('patrol-session.scan.photo-item');

        Route::get('/patrol-report', [PatrolReportController::class, 'index'])->name('patrol-report.index');
        Route::get('/patrol-report/export', [PatrolReportController::class, 'export'])->name('patrol-report.export');

        Route::get('/attendance/readiness', [AttendanceReadinessController::class, 'index'])
            ->name('attendance.readiness');
        Route::post('/attendance/generate', [AttendanceGenerationController::class, 'store'])
            ->name('attendance.generate');

        Route::get('/attendance-record', [AttendanceRecordController::class, 'index'])
            ->name('attendance-record.index');
        Route::get('/attendance-record/{id}', [AttendanceRecordController::class, 'show'])
            ->whereNumber('id')
            ->name('attendance-record.show');

        Route::get('/attendance-correction', [AttendanceCorrectionController::class, 'index'])
            ->name('attendance-correction.index');
        Route::post('/attendance-correction/{id}/approve', [AttendanceCorrectionController::class, 'approve'])
            ->whereNumber('id')
            ->name('attendance-correction.approve');
        Route::post('/attendance-correction/{id}/reject', [AttendanceCorrectionController::class, 'reject'])
            ->whereNumber('id')
            ->name('attendance-correction.reject');

        Route::middleware('role:superadmin')->group(function () {
            Route::get('/attendance-setting', [AttendanceSettingController::class, 'edit'])
                ->name('attendance-setting.edit');
            Route::put('/attendance-setting', [AttendanceSettingController::class, 'update'])
                ->name('attendance-setting.update');

            Route::post('/guest-book/qr/{unit}/regenerate-checkin', [GuestBookQrController::class, 'regenerateCheckIn'])->whereNumber('unit')->name('guest-book.qr.regenerate-checkin');
            Route::post('/guest-book/qr/{unit}/regenerate-checkout', [GuestBookQrController::class, 'regenerateCheckOut'])->whereNumber('unit')->name('guest-book.qr.regenerate-checkout');
            Route::post('/guest-book/qr/backfill', [GuestBookQrController::class, 'backfill'])->name('guest-book.qr.backfill');

            Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
            Route::get('/announcements/create', [AdminAnnouncementController::class, 'create'])->name('announcements.create');
            Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
            Route::get('/announcements/{announcement}/edit', [AdminAnnouncementController::class, 'edit'])->whereNumber('announcement')->name('announcements.edit');
            Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update'])->whereNumber('announcement')->name('announcements.update');
            Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->whereNumber('announcement')->name('announcements.destroy');

            Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
            Route::get('/activity-log/{activityLog}', [ActivityLogController::class, 'show'])->whereNumber('activityLog')->name('activity-log.show');
        });

        Route::get('/guest-book/qr', [GuestBookQrController::class, 'index'])->name('guest-book.qr.index');
        Route::get('/guest-book/qr/{unit}', [GuestBookQrController::class, 'show'])->whereNumber('unit')->name('guest-book.qr.show');
        Route::get('/guest-book/qr/{unit}/download/{type}', [GuestBookQrController::class, 'download'])->whereNumber('unit')->name('guest-book.qr.download');


        Route::get('/monitoring/apar', [AparInspectionController::class, 'monitoring'])->name('monitoring.apar.index');
        Route::get('/monitoring/apar/{inspection}', [AparInspectionController::class, 'monitoringShow'])->whereNumber('inspection')->name('monitoring.apar.show');
        Route::get('/monitoring/hydrant', [HydrantInspectionController::class, 'monitoring'])->name('monitoring.hydrant.index');
        Route::get('/monitoring/hydrant/{inspection}', [HydrantInspectionController::class, 'monitoringShow'])->whereNumber('inspection')->name('monitoring.hydrant.show');

        Route::get('/apar', [AparController::class, 'index'])->name('apar.index');
        Route::post('/apar', [AparController::class, 'store'])->name('apar.store');
        Route::get('/apar/create', [AparController::class, 'create'])->name('apar.create');
        Route::get('/apar/{id}/edit', [AparController::class, 'edit'])->whereNumber('id')->name('apar.edit');
        Route::put('/apar/{id}', [AparController::class, 'update'])->whereNumber('id')->name('apar.update');
        Route::post('/apar/{id}/toggle-active', [AparController::class, 'toggleActive'])->whereNumber('id')->name('apar.toggle-active');
        Route::get('/apar/{id}/inspections', [AparInspectionController::class, 'index'])->whereNumber('id')->name('apar.inspections.index');
        Route::get('/apar/{id}/qr-pdf', [AparDocumentController::class, 'qrPdf'])->whereNumber('id')->name('apar.qr-pdf');
        Route::get('/apar/{id}/annual-card', [AparDocumentController::class, 'annualCard'])->whereNumber('id')->name('apar.annual-card');
        Route::get('/apar/inspections/export', [AparDocumentController::class, 'export'])->name('apar.inspections.export');
        Route::get('/apar/inspections/{inspection}', [AparInspectionController::class, 'show'])->whereNumber('inspection')->name('apar-inspections.show');
        Route::get('/apar/inspection-photos/{photo}', [AparInspectionController::class, 'photo'])->whereNumber('photo')->name('apar-inspections.photo');
        Route::get('/apar/{id}', [AparController::class, 'show'])->whereNumber('id')->name('apar.show');

        Route::get('/hydrant', [HydrantController::class, 'index'])->name('hydrant.index');
        Route::post('/hydrant', [HydrantController::class, 'store'])->name('hydrant.store');
        Route::get('/hydrant/create', [HydrantController::class, 'create'])->name('hydrant.create');
        Route::get('/hydrant/{id}/edit', [HydrantController::class, 'edit'])->whereNumber('id')->name('hydrant.edit');
        Route::put('/hydrant/{id}', [HydrantController::class, 'update'])->whereNumber('id')->name('hydrant.update');
        Route::post('/hydrant/{id}/toggle-active', [HydrantController::class, 'toggleActive'])->whereNumber('id')->name('hydrant.toggle-active');
        Route::get('/hydrant/{id}/inspections', [HydrantInspectionController::class, 'index'])->whereNumber('id')->name('hydrant.inspections.index');
        Route::get('/hydrant/{id}/qr-pdf', [HydrantDocumentController::class, 'qrPdf'])->whereNumber('id')->name('hydrant.qr-pdf');
        Route::get('/hydrant/inspections/export', [HydrantDocumentController::class, 'export'])->name('hydrant.inspections.export');
        Route::get('/hydrant/inspections/{inspection}', [HydrantInspectionController::class, 'show'])->whereNumber('inspection')->name('hydrant-inspections.show');
        Route::get('/hydrant/inspection-photos/{photo}', [HydrantInspectionController::class, 'photo'])->whereNumber('photo')->name('hydrant-inspections.photo');
        Route::get('/hydrant/{id}', [HydrantController::class, 'show'])->whereNumber('id')->name('hydrant.show');

        Route::get('/patrol-point', [PatrolPointController::class, 'index'])->name('patrol-point.index');
        Route::post('/patrol-point', [PatrolPointController::class, 'store'])->name('patrol-point.store');
        Route::get('/patrol-point/create', [PatrolPointController::class, 'create'])->name('patrol-point.create');
        Route::get('/patrol-point/post/{id}/qr.pdf', [PatrolPointController::class, 'qrPdf'])->whereNumber('id')->name('patrol-point.post.qr-pdf');
        Route::get('/patrol-point/post/{id}', [PatrolPointController::class, 'showPost'])->whereNumber('id')->name('patrol-point.post.show');
        Route::get('/patrol-point/{id}/edit', [PatrolPointController::class, 'edit'])->whereNumber('id')->name('patrol-point.edit');
        Route::put('/patrol-point/{id}', [PatrolPointController::class, 'update'])->whereNumber('id')->name('patrol-point.update');
        Route::delete('/patrol-point/{id}', [PatrolPointController::class, 'destroy'])->whereNumber('id')->name('patrol-point.destroy');
        Route::get('/patrol-point/{id}', [PatrolPointController::class, 'show'])->whereNumber('id')->name('patrol-point.show');

        Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
        Route::post('/security', [SecurityController::class, 'store'])->name('security.store');
        Route::get('/security/create', [SecurityController::class, 'create'])->name('security.create');
        Route::get('/security/{id}/edit', [SecurityController::class, 'edit'])->whereNumber('id')->name('security.edit');
        Route::put('/security/{id}', [SecurityController::class, 'update'])->whereNumber('id')->name('security.update');
        Route::delete('/security/{id}', [SecurityController::class, 'destroy'])->whereNumber('id')->name('security.destroy');
        Route::get('/security/{id}', [SecurityController::class, 'show'])->whereNumber('id')->name('security.show');

        Route::get('/unit', [UnitController::class, 'index'])->name('unit.index');
        Route::post('/unit', [UnitController::class, 'store'])->name('unit.store');
        Route::get('/unit/create', [UnitController::class, 'create'])->name('unit.create');
        Route::get('/unit/{id}/edit', [UnitController::class, 'edit'])->whereNumber('id')->name('unit.edit');
        Route::put('/unit/{id}', [UnitController::class, 'update'])->whereNumber('id')->name('unit.update');
        Route::delete('/unit/{id}', [UnitController::class, 'destroy'])->whereNumber('id')->name('unit.destroy');
        Route::get('/unit/{id}', [UnitController::class, 'show'])->whereNumber('id')->name('unit.show');

        Route::get('/user', [UserController::class, 'index'])->name('user.index');
        Route::post('/user', [UserController::class, 'store'])->name('user.store');
        Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
        Route::get('/user/{id}/edit', [UserController::class, 'edit'])->whereNumber('id')->name('user.edit');
        Route::put('/user/{id}', [UserController::class, 'update'])->whereNumber('id')->name('user.update');
        Route::delete('/user/{id}', [UserController::class, 'destroy'])->whereNumber('id')->name('user.destroy');
    });

    Route::middleware(['role:security', 'security.profile'])->prefix('app')->name('app.')->group(function () {
        Route::get('/', [AppDashboardController::class, 'index'])->name('dashboard');
        Route::get('/announcements', [AppAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/{id}', [AppAnnouncementController::class, 'show'])->whereNumber('id')->name('announcements.show');
        Route::get('/profile', [AppProfileController::class, 'show'])->name('profile');
        Route::get('/profile/password', [AppProfileController::class, 'password'])->name('profile.password');
        Route::post('/profile/password', [AppProfileController::class, 'updatePassword'])->name('profile.password.update');

        Route::get('/attendance', [AppAttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/history', [AppAttendanceController::class, 'history'])
            ->name('attendance.history');
        Route::get('/attendance/correction', [AppAttendanceController::class, 'correction'])
            ->name('attendance.correction.index');
        Route::post('/attendance/{id}/check-in', [AppAttendanceController::class, 'checkIn'])
            ->whereNumber('id')
            ->name('attendance.check-in');
        Route::post('/attendance/{id}/check-out', [AppAttendanceController::class, 'checkOut'])
            ->whereNumber('id')
            ->name('attendance.check-out');
        Route::post('/attendance/{id}/correction', [AppAttendanceController::class, 'submitCorrection'])
            ->whereNumber('id')
            ->name('attendance.correction');


        Route::get('/scan', [AppQrScanController::class, 'index'])->name('scan');
        Route::post('/qr/resolve', [AppQrScanController::class, 'resolve'])->name('qr.resolve');
        Route::get('/apar', [AppAparController::class, 'index'])->name('apar.index');
        Route::get('/apar/{id}', [AppAparController::class, 'show'])->whereNumber('id')->name('apar.show');
        Route::get('/apar/{id}/inspect', [AppAparController::class, 'createInspection'])->whereNumber('id')->name('apar.inspect.create');
        Route::post('/apar/{id}/inspect', [AppAparController::class, 'storeInspection'])->whereNumber('id')->name('apar.inspect.store');
        Route::get('/apar/{id}/inspections', [AppAparInspectionController::class, 'index'])->whereNumber('id')->name('apar.inspections.index');
        Route::get('/apar/{id}/qr-pdf', [AppAparDocumentController::class, 'qrPdf'])->whereNumber('id')->name('apar.qr-pdf');
        Route::get('/apar/{id}/annual-card', [AppAparDocumentController::class, 'annualCard'])->whereNumber('id')->name('apar.annual-card');
        Route::get('/apar/inspections/{inspection}', [AppAparInspectionController::class, 'show'])->whereNumber('inspection')->name('apar.inspections.show');
        Route::get('/apar/inspection-photos/{photo}', [AppAparInspectionController::class, 'photo'])->whereNumber('photo')->name('apar.inspections.photo');
        Route::get('/hydrant', [AppHydrantController::class, 'index'])->name('hydrant.index');
        Route::get('/hydrant/{id}', [AppHydrantController::class, 'show'])->whereNumber('id')->name('hydrant.show');
        Route::get('/hydrant/{id}/inspect', [AppHydrantController::class, 'createInspection'])->whereNumber('id')->name('hydrant.inspect.create');
        Route::post('/hydrant/{id}/inspect', [AppHydrantController::class, 'storeInspection'])->whereNumber('id')->name('hydrant.inspect.store');
        Route::get('/hydrant/{id}/inspections', [AppHydrantInspectionController::class, 'index'])->whereNumber('id')->name('hydrant.inspections.index');
        Route::get('/hydrant/{id}/qr-pdf', [AppHydrantDocumentController::class, 'qrPdf'])->whereNumber('id')->name('hydrant.qr-pdf');
        Route::get('/hydrant/inspections/{inspection}', [AppHydrantInspectionController::class, 'show'])->whereNumber('inspection')->name('hydrant.inspections.show');
        Route::get('/hydrant/inspection-photos/{photo}', [AppHydrantInspectionController::class, 'photo'])->whereNumber('photo')->name('hydrant.inspections.photo');
        Route::get('/patrol', [AppPatrolController::class, 'index'])->name('patrol.index');
        Route::get('/patrol/{patrolSession}', [AppPatrolController::class, 'show'])->name('patrol.show');
        Route::get('/patrol/{patrolSession}/scan', [AppPatrolController::class, 'scanPage'])->name('patrol.scan.page');
        Route::post('/patrol/{patrolSession}/start', [AppPatrolController::class, 'start'])->name('patrol.start');
        Route::post('/patrol/{patrolSession}/preview', [AppPatrolController::class, 'preview'])->name('patrol.preview');
        Route::post('/patrol/{patrolSession}/scan', [AppPatrolController::class, 'scan'])->name('patrol.scan');
        Route::get('/patrol/{patrolSession}/scans/{scan}/photo', [AppPatrolController::class, 'incidentPhoto'])->name('patrol.scan.photo');
        Route::get('/patrol/{patrolSession}/scans/{scan}/photos/{photo}', [AppPatrolController::class, 'incidentPhotoItem'])->name('patrol.scan.photo-item');
    });
});










