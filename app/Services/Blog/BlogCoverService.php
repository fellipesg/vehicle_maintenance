<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Support\AppStorage;
use App\Support\UploadedImageExtension;
use Illuminate\Http\UploadedFile;

class BlogCoverService
{
    public function store(BlogPost $post, UploadedFile $file): BlogPost
    {
        $extension = UploadedImageExtension::for($file);
        $filePath = AppStorage::BLOG_COVERS_PREFIX.$post->id.'_'.time().'.'.$extension;

        $this->delete($post->cover_photo_path);

        AppStorage::putPublic($filePath, (string) file_get_contents($file->getRealPath()));

        $post->update(['cover_photo_path' => $filePath]);

        return $post->fresh();
    }

    public function delete(?string $coverPhotoPath): void
    {
        if ($coverPhotoPath === null || $coverPhotoPath === '') {
            return;
        }

        if (AppStorage::coversDisk()->exists($coverPhotoPath)) {
            AppStorage::coversDisk()->delete($coverPhotoPath);
        }
    }
}
