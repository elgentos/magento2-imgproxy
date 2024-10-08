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
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly Curl $curl
    ) {
    }

    public function getCustomUrl(
        string $currentUrl,
        int $width,
        int $height
    ): string {
        if (!$this->config->isEnabled()) {
            return $currentUrl;
        }

        $serviceUrl = $this->config->getImgproxyHost();

        if (empty($serviceUrl)) {
            return $currentUrl;
        }

        /** @var Store $store */
        $store = $this->storeManager->getStore();

        if ($this->config->getDevMode() && $this->config->getProductionMediaUrl()) {
            $baseDomain = parse_url(
                $store->getBaseUrl(),
                PHP_URL_HOST
            );
            $productionMediaDomain = parse_url(
                $this->config->getProductionMediaUrl(),
                PHP_URL_HOST
            );
            $currentUrl = str_replace($baseDomain, $productionMediaDomain, $currentUrl);
            // Strip out the cache hash
            $currentUrl = preg_replace('/\/cache\/[a-f0-9]+/', '', $currentUrl);
        }

        try {
            $builder = new UrlBuilder(
                $serviceUrl,
                $this->config->getSignKey(),
                $this->config->getSignSalt()
            );
        } catch (Exception $e) {
            return $currentUrl;
        }

        $url = $builder->build($currentUrl, $width, $height);

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
                $arguments = $this->convertCustomProcessingOptionsToInt(explode(':', $option));

                $name = array_shift($arguments);

        // @todo We need to use reflection here
                $method = sprintf('with%s', $name);
                $url->options()->{$method}(...$arguments);
            }
        }

        $imgProxyUrl = $url->toString();

        try {
            $this->curl->head($imgProxyUrl);
            if ($this->curl->getStatus() !== 200) {
                return $currentUrl;
            }
        } catch (Exception $e) {
            return $currentUrl;
        }

        return $imgProxyUrl;
    }

    private function convertCustomProcessingOptionsToInt(array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (is_numeric($value) && ctype_digit($value)) {
                $arguments[$key] = (int)$value;
            }
        }

        return $arguments;
    }
}
