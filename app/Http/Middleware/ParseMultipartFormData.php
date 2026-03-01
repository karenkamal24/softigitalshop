<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parse multipart/form-data for PATCH/PUT requests.
 *
 * PHP only populates $_POST and $_FILES for POST requests.
 * This middleware manually parses the raw body for PATCH/PUT
 * so that form-data works with these HTTP methods.
 */
class ParseMultipartFormData
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            in_array($request->method(), ['PUT', 'PATCH']) &&
            str_contains((string) $request->header('Content-Type'), 'multipart/form-data')
        ) {
            $this->parseFormData($request);
        }

        return $next($request);
    }

    private function parseFormData(Request $request): void
    {
        $contentType = (string) $request->header('Content-Type');

        preg_match('/boundary=(.+)$/i', $contentType, $matches);
        if (empty($matches[1])) {
            return;
        }

        $boundary = $matches[1];
        $rawBody = $request->getContent();

        if (empty($rawBody)) {
            return;
        }

        $blocks = preg_split('/-+' . preg_quote($boundary, '/') . '/', $rawBody);
        if (! is_array($blocks)) {
            return;
        }

        $data = [];
        $files = [];

        foreach ($blocks as $block) {
            if (empty($block) || $block === "--\r\n") {
                continue;
            }

            // Split headers from body
            $parts = preg_split('/\r\n\r\n/', $block, 2);
            if (! is_array($parts) || count($parts) < 2) {
                continue;
            }

            $headers = $parts[0];
            $body = rtrim($parts[1], "\r\n");

            // Extract field name
            if (! preg_match('/name="([^"]+)"/', $headers, $nameMatch)) {
                continue;
            }

            $fieldName = $nameMatch[1];

            // Check if it's a file upload
            if (preg_match('/filename="([^"]*)"/', $headers, $fileMatch)) {
                $filename = $fileMatch[1];

                if (empty($filename)) {
                    continue;
                }

                // Get content type
                preg_match('/Content-Type:\s*(.+)\r?\n?/i', $headers, $typeMatch);
                $mimeType = trim($typeMatch[1] ?? 'application/octet-stream');

                // Save to temp file
                $tmpPath = tempnam(sys_get_temp_dir(), 'php_');
                if ($tmpPath !== false) {
                    file_put_contents($tmpPath, $body);
                    $files[$fieldName] = new \Illuminate\Http\UploadedFile(
                        $tmpPath,
                        $filename,
                        $mimeType,
                        null,
                        true
                    );
                }
            } else {
                // Regular form field — support array notation (e.g. items[0][id])
                $data[$fieldName] = $body;
            }
        }

        if (! empty($data)) {
            $request->merge($data);
        }

        if (! empty($files)) {
            $request->files->add($files);
        }
    }
}




