<?php

namespace App\Notifications;

use App\Models\StudyGroupPost;
use Illuminate\Notifications\Notification;

/** A new question, comment, or reply was posted in a study group this user belongs to. */
class StudyGroupActivity extends Notification
{
    public function __construct(private StudyGroupPost $post) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $group = $this->post->studyGroup;

        return [
            'study_group_id' => $group->id,
            'group_name' => $group->name,
            'post_id' => $this->post->id,
            'author' => $this->post->user->name,
            'is_reply' => $this->post->parent_id !== null,
            'excerpt' => \Illuminate\Support\Str::limit($this->post->body, 80),
        ];
    }
}
