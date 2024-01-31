<?php declare(strict_types=1);

namespace Bref\Event\Http;

use Bref\Context\Context;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Riverline\MultiPartParser\Part;
use RuntimeException;

use function str_starts_with;

/**
 * Bridges PSR-7 requests and responses with API Gateway or ALB event/response formats.
 */
final class Psr7Bridge
{
    private const UPLOADED_FILES_PREFIX = 'bref_upload_';

    /**
     * Create a PSR-7 server request from an AWS Lambda HTTP event.
     */
    public static function convertRequest(HttpRequestEvent $event, Context $context): ServerRequestInterface
    {
        $headers = $event->getHeaders();

        [$files, $parsedBody] = self::parseBodyAndUploadedFiles($event);
        [$user, $password] = $event->getBasicAuthCredentials();

        $server = array_filter([
            'CONTENT_LENGTH' => $headers['content-length'][0] ?? null,
            'CONTENT_TYPE' => $event->getContentType(),
            'DOCUMENT_ROOT' => getcwd(),
            'QUERY_STRING' => $event->getQueryString(),
            'REQUEST_METHOD' => $event->getMethod(),
            'SERVER_NAME' => $event->getServerName(),
            'SERVER_PORT' => $event->getServerPort(),
            'SERVER_PROTOCOL' => $event->getProtocol(),
            'PATH_INFO' => $event->getPath(),
            'HTTP_HOST' => $headers['host'] ?? null,
            'REMOTE_ADDR' => $event->getSourceIp(),
            'REMOTE_PORT' => $event->getRemotePort(),
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'REQUEST_URI' => $event->getUri(),
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => $password,
        ]);

        foreach ($headers as $name => $values) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', (string) $name))] = $values[0];
        }

        /**
         * Nyholm/psr7 does not rewind body streams, we do it manually
         * so that users can fetch the content of the body directly.
         */
        $bodyStream = Stream::create($event->getBody());
        $bodyStream->rewind();

        $request = new ServerRequest(
            $event->getMethod(),
            $event->getUri(),
            $event->getHeaders(),
            $bodyStream,
            $event->getProtocolVersion(),
            $server
        );

        foreach ($event->getPathParameters() as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request->withUploadedFiles($files)
            ->withCookieParams($event->getCookies())
            ->withQueryParams($event->getQueryParameters())
            ->withParsedBody($parsedBody)
            ->withAttribute('lambda-event', $event)
            ->withAttribute('lambda-context', $context);
    }

    /**
     * Create a ALB/API Gateway response from a PSR-7 response.
     */
    public static function convertResponse(ResponseInterface $response): HttpResponse
    {
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        return new HttpResponse($body, $response->getHeaders(), $response->getStatusCode());
    }

    /**
     * @return array{0: array<string, UploadedFile>, 1: array<string, mixed>|null}
     */
    private static function parseBodyAndUploadedFiles(HttpRequestEvent $event): array
    {
        $contentType = $event->getContentType();
        if ($contentType === null || $event->getMethod() !== 'POST') {
            return [[], null];
        }

        if (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            $parsedBody = [];
            parse_str($event->getBody(), $parsedBody);
            return [[], $parsedBody];
        }

        // Parse the body as multipart/form-data
        $document = new Part("Content-type: $contentType\r\n\r\n" . $event->getBody());
        if (!$document->isMultiPart()) {
            return [[], null];
        }
        $files = [];
        $queryString = '';
        foreach ($document->getParts() as $part) {
            if ($part->isFile()) {
                            $tmpPath = tempnam(sys_get_temp_dir(), self::UPLOADED_FILES_PREFIX);
                if ($tmpPath === false) {
                    throw new RuntimeException('Unable to create a temporary directory');
                }
                file_put_contents($tmpPath, $part->getBody());
                $file = new UploadedFile($tmpPath, filesize($tmpPath), UPLOAD_ERR_OK, $part->getFileName(), $part->getMimeType());
                self::parseKeyAndInsertValueInArray($files, $part->getName(), $file);
            } else {
                // Temporarily store as a query string so that we can use PHP's native parse_str function to parse keys
                $queryString .= urlencode($part->getName()) . '=' . urlencode($part->getBody()) . '&';
            }
        }
        if ($queryString !== '') {
            $parsedBody = [];
            parse_str($queryString, $parsedBody);
        } else {
            $parsedBody = null;
        }
        return [$files, $parsedBody];
    }

    /**
     * Parse a string key like "files[id_cards][jpg][]" and do $array['files']['id_cards']['jpg'][] = $value
     */
    private static function parseKeyAndInsertValueInArray(array &$array, string $key, mixed $value): void
    {
        if (! str_contains($key, '[')) {
            $array[$key] = $value;

            return;
        }

        $parts = explode('[', $key); // files[id_cards][jpg][] => [ 'files',  'id_cards]', 'jpg]', ']' ]
        $pointer = &$array;

        foreach ($parts as $k => $part) {
            if ($k === 0) {
                $pointer = &$pointer[$part];

                continue;
            }

            // Skip two special cases:
            // [[ in the key produces empty string
            // [test : starts with [ but does not end with ]
            if ($part === '' || ! str_ends_with($part, ']')) {
                // Malformed key, we use it "as is"
                $array[$key] = $value;

                return;
            }

            $part = substr($part, 0, -1); // The last char is a ] => remove it to have the real key

            if ($part === '') { // [] case
                $pointer = &$pointer[];
            } else {
                $pointer = &$pointer[$part];
            }
        }

        $pointer = $value;
    }

    /**
     * Cleanup previously uploaded files.
     */
    public static function cleanupUploadedFiles(): void
    {
        // See https://github.com/brefphp/bref/commit/c77d9f5abf021f29fa96b5720b7b84adbd199092#r137983026
        $tmpFiles = glob(sys_get_temp_dir() . '/' . self::UPLOADED_FILES_PREFIX . '[A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9]');

        if ($tmpFiles !== false) {
            foreach ($tmpFiles as $file) {
                if (is_file($file)) {
                    // Silence warnings, we don't want to crash the whole runtime
                    @unlink($file);
                }
            }
        }
    }
}
