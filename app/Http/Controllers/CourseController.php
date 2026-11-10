<?php

namespace App\Http\Controllers;

use App\Services\CourseContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private CourseContent $content) {}

    public function home(): View
    {
        return view('home', ['courses' => $this->content->courses()]);
    }

    public function show(string $course): View
    {
        return view('courses.toc', ['course' => $this->content->course($course)]);
    }

    public function chapter(Request $request, string $course, string $chapter): View
    {
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';

        return view('courses.chapter', $this->content->chapter($course, $chapter, $lang));
    }
}
