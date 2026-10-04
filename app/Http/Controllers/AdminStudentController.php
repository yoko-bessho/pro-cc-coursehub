<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminStudentController extends Controller
{
    public function index()
    {
        $students = User::where('role', 'student')
            ->withCount('enrollments')
            ->paginate(20);

        return view('admin.students.index', compact('students'));
    }
}
