<?php

namespace App\Policies;

use App\Models\BlogComment;
use App\Models\User;

/**
 * Comentário do blog: a pessoa apaga o próprio; o administrador apaga e modera qualquer um.
 */
class BlogCommentPolicy
{
    public function delete(User $user, BlogComment $comment): bool
    {
        return $user->isAdmin() || $comment->user_id === $user->id;
    }
}
