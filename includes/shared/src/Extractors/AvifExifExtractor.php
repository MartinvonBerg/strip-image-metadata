<?php

namespace mvbplugins\Extractors;

final class AvifExifExtractor
{
    public function locate(string $blob): ?string
    {
        $top = $this->parseBoxes($blob, 0, strlen($blob));

        $meta = $this->findFirstBox($top, 'meta');
        if ($meta === null) {
            return null;
        }

        // meta ist eine FullBox: 4 Byte version/flags vor den Child-Boxes
        if (strlen($meta['data']) < 4) {
            return null;
        }
        $metaChildren = $this->parseBoxes($meta['data'], 4, strlen($meta['data']));

        $iinf = $this->findFirstBox($metaChildren, 'iinf');
        $iloc = $this->findFirstBox($metaChildren, 'iloc');

        if ($iinf === null || $iloc === null) {
            return null;
        }

        $idat = $this->findFirstBox($metaChildren, 'idat');

        $exifItemIds = $this->extractExifItemIdsFromIinf($iinf['data']);
        if (!$exifItemIds) {
            return null;
        }

        $locations = $this->parseIloc($iloc['data']);
        if (!$locations) {
            return null;
        }

        foreach ($exifItemIds as $itemId) {
            if (!isset($locations[$itemId])) {
                continue;
            }

            $raw = $this->readItemPayload($blob, $locations[$itemId], $idat['data'] ?? null);
            if ($raw === null || strlen($raw) < 4) {
                continue;
            }

            $normalized = $this->normalizeHeifExifPayload($raw);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeHeifExifPayload(string $payload): ?string
    {
        // HEIF/AVIF EXIF-Item: 4 Byte Offset bis zum TIFF-Header.
        if (strlen($payload) < 4) {
            return null;
        }

        $tiffOffset = unpack('N', substr($payload, 0, 4))[1] ?? null;

        if ($tiffOffset > strlen($payload) - 4) {
            return null;
        }

        $exif = substr($payload, 4 + $tiffOffset);
        if ($this->looksLikeTiffHeader($exif)) {
            return $exif;
        }

        return null;
    }

    /**
     * Summary of readItemPayload
     * @param string $fileBlob
     * @param array<string, mixed> $loc
     * @param string|null $idatData
     * @return string|null
     */
    private function readItemPayload(string $fileBlob, array $loc, ?string $idatData): ?string
    {
        $baseOffset = $loc['base_offset'];
        $constructionMethod = $loc['construction_method'];
        $extents = $loc['extents'];

        $out = '';

        foreach ($extents as $extent) {
            $offset = $baseOffset + $extent['extent_offset'];
            $length = $extent['extent_length'];

            if ($length < 0) {
                return null;
            }

            if ($constructionMethod === 1) {
                // Daten liegen in idat
                if ($idatData === null || $offset + $length > strlen($idatData)) {
                    return null;
                }
                $out .= substr($idatData, $offset, $length);
            } else {
                // construction_method 0 oder unbekannt -> Dateioffset
                if ($offset + $length > strlen($fileBlob)) {
                    return null;
                }
                $out .= substr($fileBlob, $offset, $length);
            }
        }

        return $out === '' ? null : $out;
    }

    /**
     * Summary of extractExifItemIdsFromIinf
     * @param string $data
     * @return array<int>
     */
    private function extractExifItemIdsFromIinf(string $data): array
    {
        if (strlen($data) < 4) {
            return [];
        }

        $version = ord($data[0]);
        $offset = 4; // FullBox header

        if ($version === 0) {
            if (strlen($data) < $offset + 2) {
                return [];
            }
            $entryCount = unpack('n', substr($data, $offset, 2))[1] ?? null;
            $offset += 2;
        } else {
            if (strlen($data) < $offset + 4) {
                return [];
            }
            $entryCount = unpack('N', substr($data, $offset, 4))[1] ?? null;
            $offset += 4;
        }

        $children = $this->parseBoxes($data, $offset, strlen($data));
        $ids = [];

        foreach ($children as $box) {
            if ($box['type'] !== 'infe') {
                continue;
            }

            $id = $this->parseInfeForExifItemId($box['data']);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function parseInfeForExifItemId(string $data): ?int
    {
        if (strlen($data) < 4) {
            return null;
        }

        $version = ord($data[0]);
        $offset = 4; // version (1) + flags (3)

        if ($version === 2) {
            if (strlen($data) < $offset + 2 + 2 + 4) {
                return null;
            }

            $itemId = unpack('n', substr($data, $offset, 2))[1] ?? null;
            $offset += 2;

            $offset += 2; // item_protection_index

            $itemType = substr($data, $offset, 4);

            return $itemType === 'Exif' ? $itemId : null;
        }

        if ($version === 3) {
            if (strlen($data) < $offset + 4 + 2 + 4) {
                return null;
            }

            $itemId = unpack('N', substr($data, $offset, 4))[1] ?? null;
            $offset += 4;

            $offset += 2; // item_protection_index

            $itemType = substr($data, $offset, 4);

            return $itemType === 'Exif' ? $itemId : null;
        }

        return null;
    }

    /**
     * Summary of parseIloc
     * @param string $data
     * @return array<int, array{
     *      construction_method: int,
     *      base_offset: int,
     *      extents: list<array{
     *          extent_offset: int,
     *          extent_length: int
     *      }>
     * }>
     */
    private function parseIloc(string $data): array
    {
        if ( \strlen($data) < 8) {
            return [];
        }

        $version = \ord($data[0]);
        $offset = 4; // FullBox header

        $tmp = \ord($data[$offset]);
        $offsetSize = ($tmp >> 4) & 0x0F;
        $lengthSize = $tmp & 0x0F;
        $offset++;

        $tmp = \ord($data[$offset]);
        $baseOffsetSize = ($tmp >> 4) & 0x0F;
        $indexSize = ($version === 1 || $version === 2) ? ($tmp & 0x0F) : 0;
        $offset++;

        if ($version < 2) {
            
            $itemCount = unpack('n', substr($data, $offset, 2))[1] ?? null;
            $offset += 2;
        } else {
            if (strlen($data) < $offset + 4) {
                return [];
            }
            $itemCount = unpack('N', substr($data, $offset, 4))[1] ?? null;
            $offset += 4;
        }

        $result = [];

        for ($i = 0; $i < $itemCount; $i++) {
            if ($version < 2) {
                if (strlen($data) < $offset + 2) {
                    return [];
                }
                $itemId = unpack('n', substr($data, $offset, 2))[1] ?? null;
                $offset += 2;
            } else {
                if (strlen($data) < $offset + 4) {
                    return [];
                }
                $itemId = unpack('N', substr($data, $offset, 4))[1] ?? null;
                $offset += 4;
            }

            $constructionMethod = 0;
            if ($version === 1 || $version === 2) {
                if (strlen($data) < $offset + 2) {
                    return [];
                }
                $tmp = unpack('n', substr($data, $offset, 2))[1] ?? null;
                $constructionMethod = $tmp & 0x000F;
                $offset += 2;
            }

            if (strlen($data) < $offset + 2) {
                return [];
            }
            $offset += 2; // data_reference_index

            $baseOffset = $this->readUIntBySize($data, $offset, $baseOffsetSize);
            if ($baseOffset === null) {
                return [];
            }
            $offset += $baseOffsetSize;

            if (strlen($data) < $offset + 2) {
                return [];
            }
            $extentCount = unpack('n', substr($data, $offset, 2))[1] ?? null;
            if ($extentCount === null) {
                return [];
            }
            $offset += 2;

            $extents = [];

            for ($j = 0; $j < $extentCount; $j++) {
                if ($indexSize > 0) {
                    $extentIndex = $this->readUIntBySize($data, $offset, $indexSize);
                    if ($extentIndex === null) {
                        return [];
                    }
                    $offset += $indexSize;
                }

                $extentOffset = $this->readUIntBySize($data, $offset, $offsetSize);
                if ($extentOffset === null) {
                    return [];
                }
                $offset += $offsetSize;

                $extentLength = $this->readUIntBySize($data, $offset, $lengthSize);
                if ($extentLength === null) {
                    return [];
                }
                $offset += $lengthSize;

                $extents[] = [
                    'extent_offset' => $extentOffset,
                    'extent_length' => $extentLength,
                ];
            }

            $result[$itemId] = [
                'construction_method' => $constructionMethod,
                'base_offset' => $baseOffset,
                'extents' => $extents,
            ];
        }

        return $result;
    }

    private function readUIntBySize(string $data, int $offset, int $size): ?int
    {
        if ($size === 0) {
            return 0;
        }

        if ($offset + $size > strlen($data)) {
            return null;
        }

        $v = 0;
        for ($i = 0; $i < $size; $i++) {
            $v = ($v << 8) | ord($data[$offset + $i]);
        }

        return $v;
    }

    /**
     * Summary of parseBoxes
     * @param string $blob
     * @param int $start
     * @param int $end
     * @return list<array{type: string, start: (float|int), end: (float|int), data: string}>
     *  
     */
    private function parseBoxes(string $blob, int $start, int $end): array
    {
        $boxes = [];
        $offset = $start;

        while ($offset + 8 <= $end) {
            $boxStart = $offset;
            $size32 = unpack('N', substr($blob, $offset, 4))[1] ?? null;
            $type = substr($blob, $offset + 4, 4);
            $offset += 8;

            if ($size32 === 0) {
                $boxEnd = $end;
            } elseif ($size32 === 1) {
                if ($offset + 8 > $end) {
                    break;
                }
                $large = $this->readUInt64(substr($blob, $offset, 8));
                $offset += 8;
                if ($large < 16) {
                    break;
                }
                $boxEnd = $boxStart + $large;
            } else {
                if ($size32 < 8) {
                    break;
                }
                $boxEnd = $boxStart + $size32;
            }

            if ($boxEnd > $end || $boxEnd < $offset) {
                break;
            }

            $headerSize = $offset - $boxStart;
            $data = substr($blob, $boxStart + $headerSize, $boxEnd - ($boxStart + $headerSize));

            $boxes[] = [
                'type' => $type,
                'start' => $boxStart,
                'end' => $boxEnd,
                'data' => $data,
            ];

            $offset = $boxEnd;
        }

        return $boxes;
    }

    /**
     * Summary of findFirstBox
     * @param list<array{type: string, start: (float|int), end: (float|int), data: string}> $boxes
     * @param string $type
     * @return array{type: string, start: (float|int), end: (float|int), data: string}|null
     *
     */
    private function findFirstBox(array $boxes, string $type): ?array
    {
        foreach ($boxes as $box) {
            if ($box['type'] === $type) {
                return $box;
            }
        }
        return null;
    }

    private function readUInt64(string $bytes): int
    {
        $parts = unpack('Nhi/Nlo', $bytes);

        if ($parts === false) {
            return 0;
        }

        $hi = $parts['hi'] ?? 0;
        $lo = $parts['lo'] ?? 0;

        return ($hi << 32) | $lo;
    }

    private function looksLikeTiffHeader(string $data): bool
    {
        return strlen($data) >= 8
            && (
                strncmp($data, "II\x2A\x00", 4) === 0 ||
                strncmp($data, "MM\x00\x2A", 4) === 0
            );
    }
}