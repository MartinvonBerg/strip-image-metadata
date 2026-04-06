<?php

declare(strict_types=1);

namespace mvbplugins\Interfaces;

/**
 * @phpstan-type WpFilteredImageMeta array{
 *     aperture: float,
 *     camera: string,
 *     caption: string,
 *     copyright: string,
 *     created_timestamp: int,
 *     credit: string,
 *     focal_length: float,
 *     iso: int,
 *     keywords: list<string>,
 *     orientation: int,
 *     shutter_speed: float,
 *     title: string
 * }
 * 
 * @phpstan-type WpRawMetadata array{
 *     ext?: string,
 * 
 *     orientation?: int,
 *     make?: string,
 *     model?: string,
 *     camera?: string,
 *     lens?: string,
 *     shutter_speed?: float|string,
 *     aperture?: float,
 *     iso?: int,
 *     datetime_original?: string,
 *     created_timestamp?: int,
 *     focal_length?: float,
 *     focal_length_in_35mm?: int, 
 *
 *     gps?: array{
 *         lat?: float,
 *         lon?: float,
 *         altitude?: float
 *     },
 *
 *     title?: string,
 *     caption?: string,
 *     description?: string,
 *     creator?: string,
 *     credit?: string,
 *     copyright?: string,
 *     keywords?: list<string>
 * }
 */

interface MetadataExtractorInterface
{
    /**
     * Extract file-based metadata from an image file.
     *
     * Preferred source priority:
     * 1. XMP
     * 2. EXIF
     * 
     * @param string $file The full path to the image file to extract metadata from.
     * @param string|null $filter Optional filter to limit the metadata extraction to an array that is equal to WordPress metadata format.
     *
     * @return WpRawMetadata
     */
    public function getMetadata(string $file, ?string $filter): array;

    /**
     * @return list<string>
     */
    public function getSupportedFileTypes(): array;

    public function isFileSupported(string $file): bool;
    
}