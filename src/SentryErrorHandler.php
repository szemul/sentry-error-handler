<?php
declare(strict_types=1);

namespace Szemul\SentryErrorHandler;

use ErrorException;
use Sentry\State\Scope;
use Szemul\ErrorHandler\Handler\ErrorHandlerInterface;
use Szemul\LoggingErrorHandlingContext\ContextInterface;
use Szemul\SentryErrorHandler\Factory\ClientHubFactory;
use Szemul\SentryErrorHandler\Helper\SentryArrayHelper;
use Throwable;

class SentryErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        protected ContextInterface $context,
        protected SentryArrayHelper $contextHelper,
        protected ClientHubFactory $clientHubFactory,
        protected ?string $errorViewerBaseUrl = null,
    ) {
    }

    public function handleError(
        int $errorLevel,
        string $message,
        string $file,
        int $line,
        string $errorId,
        bool $isErrorFatal,
        array $backTrace = [],
    ): void {
        $this->sendExceptionToSentry(new ErrorException($message, 0, $errorLevel, $file, $line), $errorId);
    }

    public function handleException(Throwable $exception, string $errorId): void
    {
        $this->sendExceptionToSentry($exception, $errorId);
    }

    public function handleShutdown(int $errorLevel, string $message, string $file, int $line, string $errorId): void
    {
        $this->sendExceptionToSentry(new ErrorException($message, 0, $errorLevel, $file, $line), $errorId);
    }

    protected function sendExceptionToSentry(Throwable $exception, string $errorId): void
    {
        $sentryClientHub = $this->clientHubFactory->getSentryClientHub();
        $sentryClientHub->configureScope(
            // Not using type hinting as Scope is final and doesn't implement an interface so it'd make this untestable
            function ($scope) use ($errorId) {
                /** @var Scope $scope */
                $scope->clear();

                $scope->setTag('error_id', $errorId);

                $errorContext = ['id' => $errorId];

                if (!empty($this->errorViewerBaseUrl)) {
                    $errorContext['link'] = $this->errorViewerBaseUrl . $errorId;
                }

                $scope->setContext('error', $errorContext);

                $this->addGlobalContextDataToScope($scope);
            },
        );

        $sentryClientHub->captureException($exception);
    }

    /**
     * @param Scope $scope Not typehinted to help with testing
     */
    protected function addGlobalContextDataToScope($scope): void
    {
        $user = $this->context->getErrorHandlerUser();

        if (!empty($user)) {
            $scope->setUser($user);
        }

        foreach ($this->context->getErrorHandlerTags() as $key => $value) {
            $scope->setTag($key, $value);
        }

        foreach ($this->context->getErrorHandlerContexts() as $key => $value) {
            $scope->setContext($key, $value);
        }

        foreach ($this->context->getErrorHandlerExtras() as $key => $value) {
            $scope->setExtra($key, $value);
        }
    }
}
