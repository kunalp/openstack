<?php

namespace OpenStack\Common\Transport;

use function GuzzleHttp\uri_template;
use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\modify_request;
use OpenStack\Common\Api\Operation;
use OpenStack\Common\Api\Parameter;

class RequestSerializer
{
    private $jsonSerializer;

    public function __construct(JsonSerializer $jsonSerializer = null)
    {
        $this->jsonSerializer = $jsonSerializer ?: new JsonSerializer();
    }

    public function serializeOptions(Operation $operation, array $userValues = [])
    {
        $options = ['headers' => []];

        foreach ($userValues as $paramName => $paramValue) {
            if (null === ($schema = $operation->getParam($paramName))) {
                continue;
            }

            $method = sprintf('stock%s', ucfirst($schema->getLocation()));
            $this->$method($schema, $paramValue, $options);
        }

        if (!empty($options['json'])) {
            if ($key = $operation->getJsonKey()) {
                $options['json'] = [$key => $options['json']];
            }
            if (strpos(json_encode($options['json']), '\/') !== false) {
                $options['body'] = json_encode($options['json'], JSON_UNESCAPED_SLASHES);
                $options['headers']['Content-Type'] = 'application/json';
                unset($options['json']);
            }
        }

        return $options;
    }

    private function stockUrl()
    {
    }

    private function stockQuery(Parameter $schema, $paramValue, array &$options)
    {
        $options['query'][$schema->getName()] = $paramValue;
    }

    private function stockHeader(Parameter $schema, $paramValue, array &$options)
    {
        $paramName = $schema->getName();

        if (stripos($paramName, 'metadata') !== false) {
            return $this->stockMetadataHeader($schema, $paramValue, $options);
        }

        $options['headers'] += is_scalar($paramValue) ? [$schema->getPrefixedName() => $paramValue] : [];
    }

    private function stockMetadataHeader(Parameter $schema, $paramValue, array &$options)
    {
        foreach ($paramValue as $key => $keyVal) {
            $schema = $schema->getItemSchema() ?: new Parameter(['prefix' => $schema->getPrefix(), 'name' => $key]);
            $this->stockHeader($schema, $keyVal, $options);
        }
    }

    private function stockJson(Parameter $schema, $paramValue, array &$options)
    {
        $json = isset($options['json']) ? $options['json'] : [];
        $options['json'] = $this->jsonSerializer->stockJson($schema, $paramValue, $json);
    }

    private function stockRaw(Parameter $schema, $paramValue, array &$options)
    {
        $options['body'] = $paramValue;
    }
}
