<?php

declare(strict_types=1);

namespace Elgentos\Imgproxy\ViewModel;

use Elgentos\Imgproxy\Model\Image;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Imgproxy implements ArgumentInterface
{
    public function __construct(
        private readonly Image $image,
    ) {
    }

    public function getCustomUrl(string $currentUrl, int $width, int $height): string
    {
        return $this->image->getCustomUrl($currentUrl, $width, $height);
    }
}
