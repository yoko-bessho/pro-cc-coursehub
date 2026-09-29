<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        if ($user->isCoach() || $user->isAdmin()) {
            return true;
        }

        return $this->hasCompletedCourse($user, $course);
    }

    public function create(User $user, Course $course): bool
    {
        if (! $this->hasCompletedCourse($user, $course)) {
            return false;
        }

        return $course->reviews()->where('user_id', $user->id)->doesntExist();
    }

    private function hasCompletedCourse(User $user, Course $course): bool
    {
        if (! $user->isStudent()) {
            return false;
        }

        return $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->exists();
    }
}
