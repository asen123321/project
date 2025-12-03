<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CloudinaryUploader
{
    private Cloudinary $cloudinary;

    public function __construct(
        #[Autowire(env: 'CLOUDINARY_URL')]
        private string $cloudinaryUrl
    ) {
        $this->cloudinary = new Cloudinary($this->cloudinaryUrl);
    }

    /**
     * Upload image to Cloudinary and return the public URL
     */
    public function uploadImage(UploadedFile $file, string $folder = 'gallery'): string
    {
        try {
            $result = $this->cloudinary->uploadApi()->upload(
                $file->getPathname(),
                [
                    'folder' => $folder,
                    'resource_type' => 'image',
                    'transformation' => [
                        'quality' => 'auto',
                        'fetch_format' => 'auto'
                    ]
                ]
            );

            return $result['secure_url'];
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload image to Cloudinary: ' . $e->getMessage());
        }
    }

    /**
     * Delete image from Cloudinary by URL
     */
    public function deleteImage(string $url): void
    {
        // Extract public_id from URL
        // Example: https://res.cloudinary.com/your-cloud/image/upload/v123/gallery/image.jpg
        // Extract: gallery/image

        preg_match('/\/upload\/(?:v\d+\/)?(.+)\.[^.]+$/', $url, $matches);

        if (isset($matches[1])) {
            $publicId = $matches[1];

            try {
                $this->cloudinary->uploadApi()->destroy($publicId);
            } catch (\Exception $e) {
                // Log error but don't fail - image might already be deleted
            }
        }
    }
}
