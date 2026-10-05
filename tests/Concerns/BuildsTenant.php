<?php

namespace Tests\Concerns;

use App\Enums\ActionCode;
use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use App\Models\Action;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserType;
use App\Support\CompanyContext;

trait BuildsTenant
{
    protected Company $company;

    protected User $admin;

    protected User $student;

    protected function buildTenant(): void
    {
        $this->company = Company::factory()->create();

        $adminType = UserType::factory()->create([
            'company_id' => $this->company->id,
            'code' => UserTypeCode::Admin->value,
        ]);

        $studentType = UserType::factory()->create([
            'company_id' => $this->company->id,
            'code' => UserTypeCode::Student->value,
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $adminType->id,
        ]);

        $this->student = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $studentType->id,
        ]);

        Action::factory()->create([
            'company_id' => $this->company->id,
            'code' => ActionCode::Completed->value,
        ]);

        Action::factory()->create([
            'company_id' => $this->company->id,
            'code' => ActionCode::Started->value,
        ]);

        Action::factory()->create([
            'company_id' => $this->company->id,
            'code' => ActionCode::Viewed->value,
        ]);

        app(CompanyContext::class)->forget();
    }

    /**
     * Creates a course with one module -> topic -> lesson and links everything.
     *
     * @return array{course: Course, module: Module, topic: Topic, lesson: Lesson}
     */
    protected function buildCourse(int $companyId): array
    {
        $course = Course::factory()->create(['company_id' => $companyId, 'status' => GeneralStatus::Active->value]);
        $module = Module::factory()->create(['company_id' => $companyId, 'status' => GeneralStatus::Active->value]);
        $topic = Topic::factory()->create(['company_id' => $companyId, 'module_id' => $module->id]);
        $lesson = Lesson::factory()->create([
            'company_id' => $companyId,
            'topic_id' => $topic->id,
            'status' => GeneralStatus::Active->value,
        ]);

        $course->modules()->attach($module->id, [
            'company_id' => $companyId,
            'status' => GeneralStatus::Active->value,
            'sort_order' => 0,
        ]);
        Certificate::factory()->create(['company_id' => $companyId, 'course_id' => $course->id]);

        return compact('course', 'module', 'topic', 'lesson');
    }

    protected function enroll(User $user, Course $course, int $companyId): Team
    {
        $team = Team::factory()->create(['company_id' => $companyId, 'status' => GeneralStatus::Active->value]);

        $team->courses()->attach($course->id, [
            'company_id' => $companyId,
            'status' => GeneralStatus::Active->value,
        ]);
        $team->users()->attach($user->id, [
            'company_id' => $companyId,
            'status' => GeneralStatus::Active->value,
            'expired_at' => null,
        ]);

        return $team;
    }

    protected function apiLogin(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }
}
