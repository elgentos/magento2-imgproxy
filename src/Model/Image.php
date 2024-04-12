<?php

declare(strict_types=1);

namespace Elgentos\Imgproxy\Model;

use Elgentos\Imgproxy\Service\Curl;
use Exception;
use Imgproxy\UrlBuilder;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

// phpcs:disable Magento2.Functions.DiscouragedFunction.Discouraged

class Image
{
    public function __construct(
        private readonly Config                $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly Curl                  $curl
    ) {
    }

    public function getCustomUrl(
        string $currentUrl,
        int    $width,
        int    $height
    ): string {
        if (!$this->config->isEnabled()) {
            return $currentUrl;
        }

        $serviceUrl = $this->config->getImgproxyHost();

        if (empty($serviceUrl)) {
            return $currentUrl;
        }

        $strippedUrl = $this->getImageUrl($currentUrl);

        try {
            $builder = new UrlBuilder(
                $serviceUrl,
                $this->config->getSignKey(),
                $this->config->getSignSalt()
            );
        } catch (Exception $e) {
            return $currentUrl;
        }

        $url = $builder->build($strippedUrl, $width, $height);

        $url->useAdvancedMode();

        if ($this->config->getEnlargeMode()) {
            $url->options()->withEnlarge();
        }

        $url->options()->withResizingType($this->config->getResizingType());
        $url->options()->withExtend('ce');

        $customProcessingOptions = $this->config->getCustomProcessingOptions();
        if ($customProcessingOptions) {
            $options = array_map('trim', explode('/', $customProcessingOptions));
            foreach ($options as $option) {
                $arguments = explode(':', $option);
                $name = array_shift($arguments);

                $url->options()->setUnsafe($name, ...$arguments);
            }
        }

        return $url->toString();
    }

    private function getImageUrl($url): string
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();

        $baseDomain = parse_url(
            $store->getBaseUrl(),
            PHP_URL_HOST
        );

        if ($this->config->getDevMode() && $this->config->getUseLocalFileSystem()) {

            $parsedUrlScheme = parse_url(
                $store->getBaseUrl(),
                PHP_URL_SCHEME
            );


            $urlScheme = sprintf('%s%s%s', $parsedUrlScheme, '://', $baseDomain);

            $currentUrl = str_replace($urlScheme, 'local://', $url);

            return $this->getStrippedUrl($currentUrl);
        }

        if ($this->config->getDevMode() && $this->config->getProductionMediaUrl()) {

            $productionMediaDomain = parse_url(
                $this->config->getProductionMediaUrl(),
                PHP_URL_HOST
            );
            $currentUrl = str_replace($baseDomain, $productionMediaDomain, $url);

            return $this->getStrippedUrl($currentUrl);
        }

        return $url;
    }

    private function getStrippedUrl(string $url): string
    {
        return preg_replace('/\/cache\/[a-f0-9]+/', '', $url);
    }
}
