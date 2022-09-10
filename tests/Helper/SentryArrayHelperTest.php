<?php
declare(strict_types=1);

namespace Szemul\SentryErrorHandler\Test\Helper;

use JsonSerializable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use stdClass;
use PHPUnit\Framework\TestCase;
use Szemul\SentryErrorHandler\Helper\SentryArrayHelper;

class SentryArrayHelperTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testCleanUpArray(): void
    {
        $resource = fopen('php://temp', 'w');
        $denied   = Mockery::mock(stdClass::class);
        $jsonable = Mockery::mock(JsonSerializable::class);
        $object   = new stdClass();
        $jsonData = [
            'json' => 'data',
        ];

        // @phpstan-ignore-next-line
        $jsonable->shouldReceive('jsonSerialize')
            ->once()
            ->withNoArgs()
            ->andReturn($jsonData);

        $object->test = 'value';

        $arrayHelper = new SentryArrayHelper(get_class($denied));

        $testData = [
            'array'      => [
                'test' => 'value',
            ],
            'resource'   => $resource,
            'string'     => 'test',
            'int'        => 2,
            'denied'     => $denied,
            'object'     => $object,
            'jsonable'   => $jsonable,
            'float'      => 2.5,
            'bool'       => true,
            'null'       => null,
        ];

        $expectedData = [
            'array'      => [
                'test' => 'value',
            ],
            'resource'   => 'Resource of type ' . get_resource_type($resource),
            'string'     => 'test',
            'int'        => 2,
            'denied'     => [
                'class'    => get_class($denied),
                'contents' => SentryArrayHelper::REDACTED_MESSAGE,

            ],
            'object'     => [
                'class'     => get_class($object),
                'variables' => [
                    'test' => $object->test,
                ],
            ],
            'jsonable'   => [
                'class'          => get_class($jsonable),
                'jsonSerialized' => $jsonData,
            ],
            'float'      => 2.5,
            'bool'       => true,
            'null'       => '',
        ];

        $this->assertSame($expectedData, $arrayHelper->cleanUpArray($testData));
    }

    public function testWithTooHighLevel_shouldReturnTruncatedMessage(): void
    {
        $arrayHelper = new SentryArrayHelper();

        $this->assertSame(
            SentryArrayHelper::TRUNCATED_MESSAGE,
            $arrayHelper->cleanUpArray([], SentryArrayHelper::MAX_NESTING_LEVEL + 1),
        );
    }
}
