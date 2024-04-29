<?php
declare(strict_types=1);

namespace Szemul\SentryErrorHandler\Test;

use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Sentry\State\HubInterface;
use Szemul\LoggingErrorHandlingContext\ContextInterface;
use Szemul\SentryErrorHandler\Factory\ClientHubFactory;
use Szemul\SentryErrorHandler\Helper\SentryArrayHelper;
use Szemul\SentryErrorHandler\SentryErrorHandler;
use Throwable;

class SentryErrorHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const ERROR_ID = 'error1';
    private const BASE_URL = 'https://www.example.com/';

    private HubInterface | MockInterface | LegacyMockInterface      $hubInterface;
    private ContextInterface | MockInterface | LegacyMockInterface  $context;
    private SentryArrayHelper | MockInterface | LegacyMockInterface $sentryArrayHelper;
    private ClientHubFactory | MockInterface | LegacyMockInterface $clientHubFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hubInterface      = Mockery::mock(HubInterface::class);
        $this->context           = Mockery::mock(ContextInterface::class);
        $this->sentryArrayHelper = Mockery::mock(SentryArrayHelper::class);
        $this->clientHubFactory  = Mockery::mock(ClientHubFactory::class);
    }

    public function testHandleError(): void
    {
        $scope = $this->expectConfigureScopeCalled();
        $this->expectStandardValuesSet($scope);
        $this->expectContextValuesSet($scope);
        $this->expectExceptionSent(Exception::class, 'test', 0);

        $handler = $this->getHandler();
        $handler->handleError(E_USER_WARNING, 'test', 'test.php', 5, self::ERROR_ID, false);
    }

    public function testHandleException(): void
    {
        $exceptionData = [
            'message' => 'test',
            'code'    => 2,
        ];
        $scope = $this->expectConfigureScopeCalled();
        $this->expectStandardValuesSet($scope);
        $this->expectContextValuesSet($scope);
        $this->expectExceptionSent(Exception::class, $exceptionData['message'], $exceptionData['code']);

        $exception = new Exception($exceptionData['message'], $exceptionData['code']);

        $handler = $this->getHandler();
        $handler->handleException($exception, self::ERROR_ID);
    }

    public function testHandleExceptionWithErrorLink(): void
    {
        $exceptionData = [
            'message' => 'test',
            'code'    => 2,
        ];
        $scope = $this->expectConfigureScopeCalled();
        $this->expectStandardValuesSetWithErrorLink($scope);
        $this->expectContextValuesSet($scope);
        $this->expectExceptionSent(Exception::class, $exceptionData['message'], $exceptionData['code']);

        $exception = new Exception($exceptionData['message'], $exceptionData['code']);

        $handler = $this->getHandler(self::BASE_URL);
        $handler->handleException($exception, self::ERROR_ID);
    }

    public function testHandleShutdown(): void
    {
        $scope = $this->expectConfigureScopeCalled();
        $this->expectStandardValuesSet($scope);
        $this->expectContextValuesSet($scope);
        $this->expectExceptionSent(Exception::class, 'test', 0);

        $handler = $this->getHandler();
        $handler->handleShutdown(E_ERROR, 'test', 'test.php', 5, self::ERROR_ID);
    }

    private function expectConfigureScopeCalled(): MockInterface | LegacyMockInterface
    {
        $scope = Mockery::mock();

        // @phpstan-ignore-next-line
        $scope->shouldReceive('clear')
            ->once()
            ->withNoArgs();

        // @phpstan-ignore-next-line
        $this->hubInterface->shouldReceive('configureScope')
            ->once()
            ->withArgs(function (callable $callable) use ($scope) {
                $callable($scope);

                return true;
            });

        // @phpstan-ignore-next-line
        $this->clientHubFactory->shouldReceive('getSentryClientHub')
            ->once()
            ->andReturn($this->hubInterface);

        return $scope;
    }

    private function expectStandardValuesSet(MockInterface | LegacyMockInterface $scope): void
    {
        // @phpstan-ignore-next-line
        $scope->shouldReceive('setTag')
            ->with('error_id', self::ERROR_ID);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setContext')
            ->with('error', ['id' => self::ERROR_ID]);
    }

    private function expectContextValuesSet(MockInterface | LegacyMockInterface $scope): void
    {
        $this->expectUserContextValuesSet($scope);
        $this->expectTagContextValuesSet($scope);
        $this->expectContextContextValuesSet($scope);
        $this->expectExtraContaxtValuesSet($scope);
    }

    private function expectUserContextValuesSet(MockInterface | LegacyMockInterface $scope): void
    {
        $user = [
            'id'    => 1,
            'email' => 'test@example.com',
        ];

        // @phpstan-ignore-next-line
        $this->context->shouldReceive('getErrorHandlerUser')
            ->once()
            ->withNoArgs()
            ->andReturn($user);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setUser')
            ->once()
            ->with($user);
    }

    private function expectTagContextValuesSet(MockInterface | LegacyMockInterface $scope): void
    {
        $tags = [
            'testTag1' => 'value1',
            'testTag2' => 'value2',
        ];

        // @phpstan-ignore-next-line
        $this->context->shouldReceive('getErrorHandlerTags')
            ->once()
            ->withNoArgs()
            ->andReturn($tags);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setTag')
            ->once()
            ->with('testTag1', $tags['testTag1']);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setTag')
            ->once()
            ->with('testTag2', $tags['testTag2']);
    }

    private function expectContextContextValuesSet(MockInterface | LegacyMockInterface $scope): void
    {
        $contexts = [
            'testContext1' => [
                'test1' => 'value',
            ],
            'testContext2' => [
                'test2' => 'value',
            ],
        ];

        // @phpstan-ignore-next-line
        $this->context->shouldReceive('getErrorHandlerContexts')
            ->once()
            ->withNoArgs()
            ->andReturn($contexts);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setContext')
            ->once()
            ->with('testContext1', $contexts['testContext1']);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setContext')
            ->once()
            ->with('testContext2', $contexts['testContext2']);
    }

    private function expectExtraContaxtValuesSet(MockInterface | LegacyMockInterface $scope): void
    {
        $extras = [
            'testExtra1' => 'value1',
            'testExtra2' => [
                'test3' => 'value',
            ],
        ];

        // @phpstan-ignore-next-line
        $this->context->shouldReceive('getErrorHandlerExtras')
            ->once()
            ->withNoArgs()
            ->andReturn($extras);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setExtra')
            ->once()
            ->with('testExtra1', $extras['testExtra1']);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setExtra')
            ->once()
            ->with('testExtra2', $extras['testExtra2']);
    }

    private function expectExceptionSent(string $exceptionClass, string $message, int $code): void
    {
        // @phpstan-ignore-next-line
        $this->hubInterface->shouldReceive('captureException')
            ->once()
            ->with(Mockery::on(function (Throwable $e) use ($exceptionClass, $message, $code) {
                $this->assertInstanceOf($exceptionClass, $e);
                $this->assertSame($message, $e->getMessage());
                $this->assertSame($code, $e->getCode());

                return true;
            }));
    }

    private function expectStandardValuesSetWithErrorLink(MockInterface | LegacyMockInterface $scope): void
    {
        // @phpstan-ignore-next-line
        $scope->shouldReceive('setTag')
            ->with('error_id', self::ERROR_ID);

        // @phpstan-ignore-next-line
        $scope->shouldReceive('setContext')
            ->with('error', ['id' => self::ERROR_ID, 'link' => self::BASE_URL . self::ERROR_ID]);
    }

    private function getHandler(?string $baseUrl = null): SentryErrorHandler
    {
        return new SentryErrorHandler($this->hubInterface, $this->context, $this->sentryArrayHelper, $this->clientHubFactory, $baseUrl);
    }
}
