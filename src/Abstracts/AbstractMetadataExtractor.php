<?php

declare(strict_types=1);

namespace mvbplugins\Abstracts;

use mvbplugins\Interfaces\MetadataExtractorInterface;

abstract class AbstractMetadataExtractor implements MetadataExtractorInterface
{
    /**
     * @var list<string>
     */
    protected array $supportedFileTypes = [
        'jpg',
        'jpeg',
        'webp',
        'avif',
    ];

    /**
     * @return list<string>
     */
    public function getSupportedFileTypes(): array
    {
        return $this->supportedFileTypes;
    }

    public function isFileSupported(string $file): bool
    {
        $extension = $this->getFileExtension($file);

        if ($extension === null) {
            return false;
        }

        return \in_array($extension, $this->supportedFileTypes, true);
    }

    protected function getFileExtension(string $file): ?string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : null;
    }

    protected function getMimeType(string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return $finfo->file($file) ?: null;
    }

    abstract public function getMetadata(string $file, ?string $filter): array;
}