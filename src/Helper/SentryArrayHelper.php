<?php
declare(strict_types=1);

namespace Szemul\SentryErrorHandler\Helper;

use JsonSerializable;

class SentryArrayHelper
{
    public const REDACTED_MESSAGE  = 'REDACTED BY CLASS DENY LIST';
    public const MAX_NESTING_LEVEL = 10;
    public const TRUNCATED_MESSAGE = 'TRUNCATED - max nesting level reached';

    /** @var string[] */
    protected array $classDenyList;

    public function __construct(string ...$classDenyList)
    {
        $this->classDenyList = $classDenyList;
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>|string
     */
    public function cleanUpArray(array $context, int $level = 0): array|string
    {
        if ($level > self::MAX_NESTING_LEVEL) {
            return self::TRUNCATED_MESSAGE;
        }

        $result = [];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->cleanUpArray($value, $level + 1);
            } elseif (is_resource($value)) {
                $result[$key] = 'Resource of type ' . get_resource_type($value);
            } elseif (is_object($value)) {
                $valueArray = [
                    'class' => get_class($value),
                ];

                if (in_array(get_class($value), $this->classDenyList)) {
                    $valueArray['contents'] = self::REDACTED_MESSAGE;
                } elseif ($value instanceof JsonSerializable) {
                    $valueArray['jsonSerialized'] = $value->jsonSerialize();
                } else {
                    $valueArray['variables'] = $this->cleanUpArray((array)$value, $level + 1);
                }

                $result[$key] = $valueArray;
            } elseif (is_scalar($value)) {
                $result[$key] = $value;
            } else {
                $result[$key] = (string)$value;
            }
        }

        return $result;
    }
}
