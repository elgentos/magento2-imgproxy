<?php

declare(strict_types=1);

namespace Elgentos\Imgproxy\Service;

class Curl extends \Magento\Framework\HTTP\Client\Curl
{
    public function head(string $uri, array $params = []): void
    {
        $this->setOptions(
            [
                CURLOPT_URL => $uri,
                CURLOPT_RETURNTRANSFER => true,
                //CURLOPT_CUSTOMREQUEST => 'HEAD',
                //CURLOPT_NOBODY => true
            ]
        );
        $this->makeRequest('GET', $uri, $params);
    }
}
