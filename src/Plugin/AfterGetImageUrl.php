<?php

declare(strict_types=1);

namespace Elgentos\Imgproxy\Plugin;

use Elgentos\Imgproxy\Model\Config;
use Elgentos\Imgproxy\Model\Image as ImgproxyImage;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Image;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Elgentos\Imgproxy\Helper\ViewConfigHelper as ViewConfig;
use Psr\Log\LoggerInterface;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps

class AfterGetImageUrl
{
    protected ImgproxyImage $image;

    protected ProductRepositoryInterface $productRepository;

    protected ImageFactory $imageHelperFactory;

    protected ViewConfig $viewConfigHelper;

    private Config $config;

    private LoggerInterface $logger;

    public function __construct(
        ImgproxyImage $image,
        ProductRepositoryInterface $productRepository,
        ImageFactory $imageHelperFactory,
        ViewConfig $viewConfigHelper,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->image = $image;
        $this->productRepository = $productRepository;
        $this->imageHelperFactory = $imageHelperFactory;
        $this->viewConfigHelper = $viewConfigHelper;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function after__call(
        Image $image,
        $result,
        string $method
    ) {
        if ($method !== 'getImageUrl') {
            return $result;
        }

        if (!$this->config->isEnabled()) {
            return $result;
        }

        if ($image->getData('product_id') === 0) {
            return $result;
        }

        $imageId    = $image->getData('image_id');
        $dimensions = $this->viewConfigHelper->getImageSize($imageId);

        try {
            $defaultImage = $this->getDefaultImageUrl(+$image->getData('product_id'));
            if (!$defaultImage) {
                return $result;
            }

            return $this->image->getCustomUrl(
                $defaultImage,
                $dimensions['width'],
                $dimensions['height']
            );
        } catch (Exception $e) {
            $this->logger->error(
                '[IMGPROXY] Error occurred while processing custom image URL.',
                [
                'product_id' => $image->getData('product_id'),
                'image_id' => $imageId,
                'exception' => $e,
                ]
            );

            return $result;
        }
    }

    public function getDefaultImageUrl(int $productId): ?string
    {
        $product = $this->getProductById($productId);

        if (!$product instanceof ProductInterface) {
            return null;
        }

        return $this->imageHelperFactory->create()
            ->init($product, 'product_page_main_image')
            ->getUrl();
    }

    public function getProductById(int $id): ?ProductInterface
    {
        try {
            return $this->productRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            // Log the exception as a warning with the product ID
            $this->logger->warning(
                '[IMGPROXY] Product not found.',
                [
                'product_id' => $id,
                'exception' => $e->getMessage(),
                ]
            );

            return null;
        }
    }
}
