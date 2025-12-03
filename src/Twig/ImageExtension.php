<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ImageExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(service: 'assets.packages')]
        private $assetsPackages
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('image_url', [$this, 'getImageUrl']),
        ];
    }

    /**
     * Intelligently returns image URL - works with both Cloudinary URLs and local paths
     */
    public function getImageUrl(?string $filename, string $folder = 'gallery'): string
    {
        if (!$filename) {
            return '/images/placeholder.png'; // Fallback placeholder
        }

        // If it's already a full URL (Cloudinary), return as-is
        if (str_starts_with($filename, 'https://') || str_starts_with($filename, 'http://')) {
            return $filename;
        }

        // Otherwise, it's a local path - use asset() helper
        return $this->assetsPackages->getUrl('uploads/' . $folder . '/' . $filename);
    }
}
