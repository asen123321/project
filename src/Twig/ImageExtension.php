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
     * For Cloudinary URLs, automatically optimizes for mobile performance
     *
     * @param string|null $filename The image filename or full URL
     * @param string $folder The folder for local images (default: 'gallery')
     * @param int|null $width Optional width for responsive images (null = auto)
     * @return string Optimized image URL
     */
    public function getImageUrl(?string $filename, string $folder = 'gallery', ?int $width = null): string
    {
        if (!$filename) {
            return '/images/placeholder.png'; // Fallback placeholder
        }

        // If it's already a full URL (Cloudinary), optimize it
        if (str_starts_with($filename, 'https://') || str_starts_with($filename, 'http://')) {
            return $this->optimizeCloudinaryUrl($filename, $width);
        }

        // Otherwise, it's a local path - use asset() helper
        return $this->assetsPackages->getUrl('uploads/' . $folder . '/' . $filename);
    }

    /**
     * Optimize Cloudinary URL by injecting transformation parameters
     * Automatically converts to WebP, compresses quality, and optionally sets width
     *
     * @param string $url Original Cloudinary URL
     * @param int|null $width Optional width constraint (null = auto width)
     * @return string Optimized Cloudinary URL with transformations
     */
    private function optimizeCloudinaryUrl(string $url, ?int $width = null): string
    {
        // Check if this is a Cloudinary URL
        if (!str_contains($url, '/upload/')) {
            return $url; // Not a Cloudinary URL, return as-is
        }

        // Build transformation string
        // f_auto = automatic format (WebP on supported browsers)
        // q_auto = automatic quality compression
        $transformations = 'f_auto,q_auto';

        // Add width parameter if provided
        if ($width !== null && $width > 0) {
            $transformations .= ',w_' . $width;
        }

        // Inject transformations after '/upload/'
        $optimizedUrl = str_replace(
            '/upload/',
            '/upload/' . $transformations . '/',
            $url
        );

        return $optimizedUrl;
    }
}
