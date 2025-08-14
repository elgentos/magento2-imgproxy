<?php

declare(strict_types=1);

namespace Elgentos\Imgproxy\Model;

use Exception;
use Imgproxy\OptionSet;
use Imgproxy\UrlBuilder;
use InvalidArgumentException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

// phpcs:disable Magento2.Functions.DiscouragedFunction.Discouraged

class Image
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
    public function getCustomUrl(
        string $currentUrl,
        int $width,
        int $height,
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
            // Log the exception message and stack trace
            $this->logger->error(
                '[IMGPROXY] Failed to create UrlBuilder.',
                [
                'exception' => $e,
                ]
            );

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
                $arguments = explode(':', $option);
                $method = sprintf('with%s', array_shift($arguments));

                $reflectionClass = new \ReflectionClass($url->options());
                if (! $reflectionClass->hasMethod($method)) {
                    continue;
                }

                $url->options()->{$method}(...$this->castArguments($url->options(), $method, $arguments));
            }
        }

        return $url->toString();
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    private function castArguments(OptionSet $class, string $methodName, array $arguments): array
    {
        $reflectionMethod = new ReflectionMethod($class, $methodName);
        $parameters = $reflectionMethod->getParameters();

        return array_map(
            function (ReflectionParameter $parameter, $argument) {
                $paramType = $parameter->getType();

                if ($paramType instanceof ReflectionNamedType) {
                    settype($argument, $paramType->getName());
                    return $argument;
                }

                if ($paramType instanceof ReflectionUnionType) {
                    foreach ($paramType->getTypes() as $type) {
                        if ($type instanceof \ReflectionNamedType) {
                            settype($argument, $type->getName());
                            return $argument;
                        }
                    }
                }

                throw new InvalidArgumentException(
                    sprintf(
                        'Unable to cast argument for parameter $%s in %s()',
                        $parameter->getName(),
                        $parameter->getDeclaringFunction()->getName()
                    )
                );
            },
            $parameters,
            $arguments
        );
    }
}
