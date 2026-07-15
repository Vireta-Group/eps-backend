<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BehaviourController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\DropoutController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IdCardController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\OnboardingWizardController;
use App\Http\Controllers\Api\ParentPortalController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentDocumentController;
use App\Http\Controllers\Api\StudentPhotoController;
use App\Http\Controllers\Api\StudentTimelineController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherAttendanceController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TenantRegistrationController;
use App\Http\Controllers\Api\TransferCertificateController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [TenantRegistrationController::class, 'register']);
Route::post('/check/email', [TenantRegistrationController::class, 'checkEmail']);
Route::post('/check/phone', [TenantRegistrationController::class, 'checkPhone']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [FileUploadController::class, 'upload']);

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::prefix('setup')->group(function () {
        Route::get('/status', [OnboardingWizardController::class, 'status']);
        Route::get('/step/{step}', [OnboardingWizardController::class, 'showStep']);
        Route::post('/step/1', [OnboardingWizardController::class, 'saveStep1']);
        Route::post('/step/2', [OnboardingWizardController::class, 'saveStep2']);
        Route::post('/step/3', [OnboardingWizardController::class, 'saveStep3']);
        Route::post('/step/4', [OnboardingWizardController::class, 'saveStep4']);
        Route::post('/step/{step}', [OnboardingWizardController::class, 'saveStep']);
        Route::post('/finish', [OnboardingWizardController::class, 'finish']);
    });

    Route::prefix('academic')->name('academic.')->group(function () {
        Route::get('/years', [AcademicYearController::class, 'index'])->name('years.index');
        Route::post('/years', [AcademicYearController::class, 'store'])->name('years.store');
        Route::get('/years/{year}', [AcademicYearController::class, 'show'])->name('years.show');
        Route::post('/years/{year}/activate', [AcademicYearController::class, 'activate'])->name('years.activate');
        Route::post('/years/{year}/archive', [AcademicYearController::class, 'archive'])->name('years.archive');
        Route::post('/years/{year}/copy', [AcademicYearController::class, 'copy'])->name('years.copy');

        Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
        Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
        Route::get('/classes/{school_class}', [ClassController::class, 'show'])->name('classes.show');
        Route::post('/classes/{school_class}/update', [ClassController::class, 'update'])->name('classes.update');
        Route::post('/classes/{school_class}/activate', [ClassController::class, 'activate'])->name('classes.activate');
        Route::post('/classes/{school_class}/inactivate', [ClassController::class, 'inactivate'])->name('classes.inactivate');
        Route::post('/classes/{school_class}/delete', [ClassController::class, 'delete'])->name('classes.delete');

        Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::get('/sections/{section}', [SectionController::class, 'show'])->name('sections.show');
        Route::post('/sections/{section}/update', [SectionController::class, 'update'])->name('sections.update');
        Route::post('/sections/{section}/activate', [SectionController::class, 'activate'])->name('sections.activate');
        Route::post('/sections/{section}/inactivate', [SectionController::class, 'inactivate'])->name('sections.inactivate');
        Route::post('/sections/{section}/delete', [SectionController::class, 'delete'])->name('sections.delete');

        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::get('/subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');
        Route::post('/subjects/{subject}/update', [SubjectController::class, 'update'])->name('subjects.update');
        Route::post('/subjects/{subject}/activate', [SubjectController::class, 'activate'])->name('subjects.activate');
        Route::post('/subjects/{subject}/inactivate', [SubjectController::class, 'inactivate'])->name('subjects.inactivate');
        Route::post('/subjects/{subject}/delete', [SubjectController::class, 'delete'])->name('subjects.delete');
    });

    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/by-class', [StudentController::class, 'byClass'])->name('byClass');
        Route::get('/by-section', [StudentController::class, 'bySection'])->name('bySection');
        Route::get('/{student}', [StudentController::class, 'show'])->name('show');
        Route::post('/{student}/activate', [StudentController::class, 'activate'])->name('activate');
        Route::post('/{student}/inactivate', [StudentController::class, 'inactivate'])->name('inactivate');
        Route::post('/{student}/delete', [StudentController::class, 'delete'])->name('delete');
        Route::post('/{student}/update', [StudentController::class, 'update'])->name('update');
        Route::post('/{student}/change-class', [StudentController::class, 'changeClass'])->name('changeClass');
        Route::post('/{student}/change-section', [StudentController::class, 'changeSection'])->name('changeSection');
        Route::post('/{student}/assign-section', [StudentController::class, 'assignSection'])->name('assignSection');
        Route::post('/{student}/set-roll', [StudentController::class, 'setRoll'])->name('setRoll');
        Route::get('/{student}/guardians', [StudentController::class, 'guardians'])->name('guardians');
        Route::post('/{student}/guardians', [StudentController::class, 'addGuardian'])->name('addGuardian');
        Route::post('/{student}/guardians/{guardian}/primary', [StudentController::class, 'setPrimaryGuardian'])->name('setPrimaryGuardian');
        Route::post('/{student}/guardians/{guardian}/delete', [StudentController::class, 'deleteGuardian'])->name('deleteGuardian');

        Route::get('/{student}/documents', [StudentDocumentController::class, 'index'])->name('documents');
        Route::post('/{student}/documents', [StudentDocumentController::class, 'store'])->name('documents.store');
        Route::post('/{student}/documents/{document}/delete', [StudentDocumentController::class, 'delete'])->name('documents.delete');

        Route::get('/{student}/photo', [StudentPhotoController::class, 'show'])->name('photo');
        Route::post('/{student}/photo', [StudentPhotoController::class, 'store'])->name('photo.store');
        Route::get('/{student}/photos/history', [StudentPhotoController::class, 'history'])->name('photo.history');

        Route::get('/{student}/timeline', [StudentTimelineController::class, 'index'])->name('timeline');

        Route::get('/id-card/templates', [IdCardController::class, 'templates'])->name('idCard.templates');
        Route::post('/id-card/templates', [IdCardController::class, 'storeTemplate'])->name('idCard.templates.store');
        Route::get('/{student}/id-card', [IdCardController::class, 'generate'])->name('idCard.generate');
        Route::post('/id-card/bulk-generate', [IdCardController::class, 'bulkGenerate'])->name('idCard.bulkGenerate');
    });

    Route::prefix('parent')->group(function () {
        Route::post('/link', [ParentPortalController::class, 'link'])->name('parent.link');
        Route::get('/children', [ParentPortalController::class, 'children'])->name('parent.children');
        Route::get('/dashboard', [ParentPortalController::class, 'dashboard'])->name('parent.dashboard');
        Route::get('/notifications', [ParentPortalController::class, 'notificationPrefs'])->name('parent.notifications');
        Route::post('/notifications', [ParentPortalController::class, 'updateNotificationPrefs'])->name('parent.notifications.update');
    });

    Route::prefix('behaviour')->group(function () {
        Route::get('/incidents', [BehaviourController::class, 'incidents']);
        Route::post('/incidents', [BehaviourController::class, 'storeIncident']);
        Route::get('/incidents/{incident}', [BehaviourController::class, 'showIncident']);
        Route::post('/incidents/{incident}/update', [BehaviourController::class, 'updateIncident']);
        Route::post('/incidents/{incident}/delete', [BehaviourController::class, 'deleteIncident']);
        Route::get('/actions', [BehaviourController::class, 'actions']);
        Route::post('/actions', [BehaviourController::class, 'storeAction']);
        Route::post('/actions/{action}/complete', [BehaviourController::class, 'completeAction']);
        Route::get('/positive', [BehaviourController::class, 'positiveBehaviours']);
        Route::post('/positive', [BehaviourController::class, 'storePositiveBehaviour']);
    });

    Route::prefix('health')->group(function () {
        Route::get('/records', [HealthController::class, 'healthRecords']);
        Route::post('/records', [HealthController::class, 'storeHealthRecord']);
        Route::get('/records/growth', [HealthController::class, 'growthChart']);
        Route::get('/vaccinations', [HealthController::class, 'vaccinations']);
        Route::post('/vaccinations', [HealthController::class, 'storeVaccination']);
        Route::get('/vaccinations/due', [HealthController::class, 'dueVaccinations']);
        Route::get('/medical-conditions', [HealthController::class, 'medicalConditions']);
        Route::post('/medical-conditions', [HealthController::class, 'storeMedicalCondition']);
        Route::get('/allergies', [HealthController::class, 'allergies']);
        Route::post('/allergies', [HealthController::class, 'storeAllergy']);
        Route::get('/allergies/critical', [HealthController::class, 'criticalAllergies']);
        Route::get('/checkups', [HealthController::class, 'checkups']);
        Route::post('/checkups', [HealthController::class, 'storeCheckup']);
        Route::get('/emergency-contacts', [HealthController::class, 'emergencyContacts']);
        Route::post('/emergency-contacts', [HealthController::class, 'storeEmergencyContact']);
        Route::post('/emergency-contacts/{contact}/set-primary', [HealthController::class, 'setPrimaryContact']);
        Route::get('/students/{student}/summary', [HealthController::class, 'studentHealthSummary']);
    });

    Route::prefix('tc')->group(function () {
        Route::get('/', [TransferCertificateController::class, 'index']);
        Route::post('/issue', [TransferCertificateController::class, 'issue']);
        Route::get('/{transferCertificate}', [TransferCertificateController::class, 'show']);
        Route::post('/{transferCertificate}/duplicate', [TransferCertificateController::class, 'issueDuplicate']);
        Route::post('/receive', [TransferCertificateController::class, 'receive']);
        Route::post('/{transferCertificate}/verify', [TransferCertificateController::class, 'verify']);
        Route::post('/verify-by-number', [TransferCertificateController::class, 'verifyByNumber']);
        Route::post('/{transferCertificate}/cancel', [TransferCertificateController::class, 'cancel']);
    });

    Route::prefix('dropouts')->group(function () {
        Route::get('/', [DropoutController::class, 'index']);
        Route::post('/', [DropoutController::class, 'store']);
        Route::get('/analytics', [DropoutController::class, 'analytics']);
        Route::get('/{dropoutRecord}', [DropoutController::class, 'show']);
        Route::post('/{dropoutRecord}/readmit', [DropoutController::class, 'readmit']);
        Route::get('/{dropoutRecord}/readmissions', [DropoutController::class, 'readmissionHistory']);
    });

    Route::prefix('attendance')->group(function () {
        Route::get('/periods', [AttendanceController::class, 'periods']);
        Route::post('/periods', [AttendanceController::class, 'storePeriod']);
        Route::post('/periods/{period}/update', [AttendanceController::class, 'updatePeriod']);
        Route::post('/periods/{period}/delete', [AttendanceController::class, 'deletePeriod']);

        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::post('/bulk', [AttendanceController::class, 'markBulk']);

        Route::get('/regularizations', [AttendanceController::class, 'regularizationRequests']);
        Route::post('/regularizations', [AttendanceController::class, 'requestRegularization']);
        Route::post('/regularizations/{regularization}/review', [AttendanceController::class, 'reviewRegularization']);

        Route::get('/fingerprint-devices', [AttendanceController::class, 'devices']);
        Route::post('/fingerprint-devices', [AttendanceController::class, 'storeDevice']);
        Route::post('/fingerprint-devices/{device}/update', [AttendanceController::class, 'updateDevice']);
        Route::get('/fingerprint-logs', [AttendanceController::class, 'fingerprintLogs']);
        Route::post('/fingerprint-logs', [AttendanceController::class, 'storeFingerprintLog']);

        Route::get('/reports/daily', [AttendanceController::class, 'dailyReport']);
        Route::get('/reports/monthly', [AttendanceController::class, 'monthlyReport']);

        Route::get('/{attendance}', [AttendanceController::class, 'show']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/admission', [ReportController::class, 'admission']);
        Route::get('/student-summary', [ReportController::class, 'studentSummary']);
        Route::get('/health-summary', [ReportController::class, 'healthSummary']);
        Route::get('/attendance-summary', [ReportController::class, 'attendanceSummary']);
        Route::get('/behaviour-summary', [ReportController::class, 'behaviourSummary']);
    });

    Route::prefix('teachers')->group(function () {
        Route::get('/', [TeacherController::class, 'index']);
        Route::post('/', [TeacherController::class, 'store']);

        Route::post('/assign-subject', [TeacherController::class, 'assignSubject']);
        Route::post('/subject-assignments/{assignment}/remove', [TeacherController::class, 'removeSubjectAssignment']);

        Route::post('/assign-class-teacher', [TeacherController::class, 'assignClassTeacher']);
        Route::get('/class-teacher-assignments', [TeacherController::class, 'classTeacherAssignments']);
        Route::post('/class-teacher-assignments/{assignment}/remove', [TeacherController::class, 'removeClassTeacherAssignment']);

        Route::get('/workload', [TeacherController::class, 'workload']);

        Route::get('/{teacher}', [TeacherController::class, 'show']);
        Route::post('/{teacher}/update', [TeacherController::class, 'update']);
        Route::post('/{teacher}/activate', [TeacherController::class, 'activate']);
        Route::post('/{teacher}/inactivate', [TeacherController::class, 'inactivate']);

        Route::get('/{teacher}/qualifications', [TeacherController::class, 'qualifications']);
        Route::post('/{teacher}/qualifications', [TeacherController::class, 'addQualification']);
        Route::post('/{teacher}/qualifications/{qualification}/delete', [TeacherController::class, 'deleteQualification']);

        Route::get('/{teacher}/trainings', [TeacherController::class, 'trainings']);
        Route::post('/{teacher}/trainings', [TeacherController::class, 'addTraining']);
        Route::post('/{teacher}/trainings/{training}/delete', [TeacherController::class, 'deleteTraining']);

        Route::get('/{teacher}/experiences', [TeacherController::class, 'experiences']);
        Route::post('/{teacher}/experiences', [TeacherController::class, 'addExperience']);
        Route::post('/{teacher}/experiences/{experience}/delete', [TeacherController::class, 'deleteExperience']);

        Route::get('/{teacher}/documents', [TeacherController::class, 'documents']);
        Route::post('/{teacher}/documents', [TeacherController::class, 'addDocument']);
        Route::post('/{teacher}/documents/{document}/verify', [TeacherController::class, 'verifyDocument']);
        Route::post('/{teacher}/documents/{document}/delete', [TeacherController::class, 'deleteDocument']);

        Route::get('/{teacher}/subject-assignments', [TeacherController::class, 'subjectAssignments']);
    });

    Route::prefix('teacher-attendance')->controller(TeacherAttendanceController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'mark');
        Route::get('/{attendance}', 'show')->whereUuid('attendance');
        Route::get('/regularizations', 'regularizations');
        Route::post('/regularizations', 'requestRegularization');
        Route::post('/regularizations/{regularization}/review', 'reviewRegularization')->whereUuid('regularization');
        Route::get('/reports/daily', 'dailyReport');
        Route::get('/reports/monthly', 'monthlyReport');
    });

    Route::prefix('leaves')->controller(LeaveController::class)->group(function () {
        Route::get('/types', 'leaveTypes');
        Route::post('/types', 'storeLeaveType');
        Route::post('/types/{leaveType}/update', 'updateLeaveType')->whereUuid('leaveType');
        Route::post('/types/{leaveType}/delete', 'deleteLeaveType')->whereUuid('leaveType');
        Route::get('/balances', 'balances');
        Route::post('/balances', 'createBalance');
        Route::get('/applications', 'applications');
        Route::post('/apply', 'apply');
        Route::post('/applications/{application}/review', 'review')->whereUuid('application');
        Route::post('/applications/{application}/cancel', 'cancel')->whereUuid('application');
    });
});
