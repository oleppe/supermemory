<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfPageCounter
{
    public function __construct(
        private readonly Parser $parser = new Parser,
    ) {}

    public function countPages(UploadedFile $file): int
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to read the uploaded PDF file.');
        }

        try {
            $document = $this->parser->parseFile($path);

            return count($document->getPages());
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to inspect the uploaded PDF file.', previous: $exception);
        }
    }
}