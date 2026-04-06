<?php

declare(strict_types=1);

namespace mvbplugins\Extractors;

final class XmpExtractor
{


    /**
     * Liest sinnvolle Metadaten aus einem XMP-XML-String.
     *
     * Unterstützte Quellen/Namensräume u. a.:
     * - dc
     * - photoshop
     * - xmp
     * - xmpRights
     * - tiff
     * - exif / exifEX
     * - aux
     * - Iptc4xmpCore
     *
     * @return array{
     *     make?: string,
     *     model?: string,
     *     camera?: string,
     *     lens?: string,
     *     exposure_time?: float|string,
     *     aperture?: float,
     *     iso?: int,
     *     datetime_original?: string,
     *     created_timestamp?: int,
     *     focal_length?: float,
     *     focal_length_in_35mm?: int,
     *     gps?: array{
     *         lat?: float,
     *         lon?: float,
     *         altitude?: float
     *     },
     *     title?: string,
     *     headline?: string,
     *     caption?: string,
     *     description?: string,
     *     creator?: string,
     *     credit?: string,
     *     copyright?: string,
     *     keywords?: list<string>,
     *     photoshop_authors_position?: string,
     *     photoshop_caption_writer?: string,
     *     photoshop_city?: string,
     *     photoshop_country?: string,
     *     photoshop_instructions?: string,
     *     photoshop_source?: string,
     *     photoshop_state?: string,
     *     photoshop_transmission_reference?: string,
     *     iptc_core_country_code?: string,
     *     iptc_core_location?: string,
     *     iptc_ext_city?: string,
     *     iptc_ext_country_code?: string,
     *     iptc_ext_country_name?: string,
     *     iptc_ext_event?: string,
     *     iptc_ext_organisation_in_image_code?: string,
     *     iptc_ext_organisation_in_image_name?: string,
     *     iptc_ext_person_in_image?: string,
     *     iptc_ext_province_state?: string,
     *     iptc_ext_sublocation?: string
     * }
     */
    public function parseXmpMetadata(string $xmp): array
    {
        $xmp = trim($xmp);
        if ($xmp === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmp);
        libxml_clear_errors();

        if ($xml === false) {
            return [];
        }

        $namespaces = $xml->getNamespaces(true);

        // Wichtige Namespaces für XPath registrieren
        foreach ($namespaces as $prefix => $uri) {
            if ($prefix !== '') {
                $xml->registerXPathNamespace($prefix, $uri);
            }
        }

        $rdfNs = $namespaces['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $xml->registerXPathNamespace('rdf', $rdfNs);

        // 1. Versuche rdf:RDF per XPath zu finden
        $rdfNodes = $xml->xpath('//rdf:RDF');

        if ($rdfNodes === false || $rdfNodes === [] || $rdfNodes === null) {
            return [];
        }

        $descriptions = [];

        foreach ($rdfNodes as $rdfNode) {
            if (!$rdfNode instanceof \SimpleXMLElement) {
                continue;
            }

            // Direkter Zugriff auf rdf:Description als Child
            $rdfChildren = $rdfNode->children($rdfNs);
            foreach ($rdfChildren->Description as $desc) {
                if ($desc instanceof \SimpleXMLElement) {
                    $descriptions[] = $desc;
                }
            }

            // XPath-Fallback relativ zum RDF-Knoten
            $more = $rdfNode->xpath('./rdf:Description');
            if (is_array($more)) {
                foreach ($more as $desc) {
                    if ($desc instanceof \SimpleXMLElement) {
                        $descriptions[] = $desc;
                    }
                }
            }
        }

        if ($descriptions === []) {
            return [];
        }

        $meta = [];
        $keywords = [];

        foreach ($descriptions as $desc) {
            // --- Dublin Core ---
            $this->self_like_add_string($meta, 'title', $this->xmp_lang_alt_first($desc, 'dc', 'title'));
            $this->self_like_add_string($meta, 'description', $this->xmp_lang_alt_first($desc, 'dc', 'description'));
            $this->self_like_add_string($meta, 'caption', $this->xmp_lang_alt_first($desc, 'dc', 'description'));
            $this->self_like_add_string($meta, 'creator', $this->xmp_seq_first($desc, 'dc', 'creator'));
            $this->self_like_add_string($meta, 'copyright', $this->xmp_lang_alt_first($desc, 'dc', 'rights'));

            $dcSubjects = $this->xmp_bag_values($desc, 'dc', 'subject');
            if ($dcSubjects !== []) {
                $keywords = array_merge($keywords, $dcSubjects);
            }

            // --- Photoshop ---
            $this->self_like_add_string($meta, 'headline', $this->xmp_read_any($desc, 'photoshop', 'Headline'));
            $this->self_like_add_string($meta, 'credit', $this->xmp_read_any($desc, 'photoshop', 'Credit'));
            $this->self_like_add_string($meta, 'datetime_original', $this->normalize_xmp_datetime($this->xmp_read_any($desc, 'photoshop', 'DateCreated')));

            // --- XMP Basic ---
            $this->self_like_add_string($meta, 'datetime_original', $this->normalize_xmp_datetime($this->xmp_read_any($desc, 'xmp', 'CreateDate')));
            $this->self_like_add_string($meta, 'datetime_original', $this->normalize_xmp_datetime($this->xmp_read_any($desc, 'xmp', 'ModifyDate')));

            // --- Rights ---
            $this->self_like_add_string($meta, 'copyright', $this->xmp_lang_alt_first($desc, 'xmpRights', 'UsageTerms'));

            // xmpRights:Owner ist oft Seq/Bag, manchmal Attribut
            $owner = $this->xmp_seq_first($desc, 'xmpRights', 'Owner')
                ?? $this->xmp_bag_first($desc, 'xmpRights', 'Owner')
                ?? $this->xmp_read_any($desc, 'xmpRights', 'Owner');
            $this->self_like_add_string($meta, 'credit', $owner);

            // --- TIFF / Kamera ---
            $this->self_like_add_string($meta, 'make', $this->xmp_read_any($desc, 'tiff', 'Make'));
            $this->self_like_add_string($meta, 'model', $this->xmp_read_any($desc, 'tiff', 'Model'));

            // --- EXIF technisch ---
            $this->self_like_add_float($meta, 'aperture', $this->xmp_fraction_or_float($this->xmp_read_any($desc, 'exif', 'FNumber')));
            $this->self_like_add_int($meta, 'iso', $this->xmp_first_int_from_candidates($desc, [
                ['exif', 'ISOSpeedRatings'],
                ['exifEX', 'PhotographicSensitivity'],
                ['exif', 'PhotographicSensitivity'],
            ]));
            $this->self_like_add_float($meta, 'focal_length', $this->xmp_fraction_or_float($this->xmp_read_any($desc, 'exif', 'FocalLength')));
            $this->self_like_add_int($meta, 'focal_length_in_35mm', $this->xmp_to_int($this->xmp_read_any($desc, 'exif', 'FocalLengthIn35mmFilm')));
            $this->self_like_add_string($meta, 'exposure_time', $this->xmp_exposure_time($this->xmp_read_any($desc, 'exif', 'ExposureTime')));
            $this->self_like_add_string($meta, 'datetime_original', $this->normalize_xmp_datetime($this->xmp_read_any($desc, 'exif', 'DateTimeOriginal')));

            $this->self_like_add_string($meta, 'photoshop_authors_position', $this->xmp_read_any($desc, 'photoshop', 'AuthorsPosition'));
            $this->self_like_add_string($meta, 'photoshop_caption_writer', $this->xmp_read_any($desc, 'photoshop', 'CaptionWriter'));
            $this->self_like_add_string($meta, 'photoshop_city', $this->xmp_read_any($desc, 'photoshop', 'City'));
            $this->self_like_add_string($meta, 'photoshop_country', $this->xmp_read_any($desc, 'photoshop', 'Country'));
            $this->self_like_add_string($meta, 'photoshop_instructions', $this->xmp_read_any($desc, 'photoshop', 'Instructions'));
            $this->self_like_add_string($meta, 'photoshop_source', $this->xmp_read_any($desc, 'photoshop', 'Source'));
            $this->self_like_add_string($meta, 'photoshop_state', $this->xmp_read_any($desc, 'photoshop', 'State'));
            $this->self_like_add_string($meta, 'photoshop_transmission_reference', $this->xmp_read_any($desc, 'photoshop', 'TransmissionReference'));

            // --- Aux / Objektiv ---
            $this->self_like_add_string($meta, 'lens', $this->xmp_first_string_from_candidates($desc, [
                ['aux', 'Lens'],
                ['aux', 'LensInfo'],
                ['exifEX', 'LensModel'],
            ]));

            // --- IPTC Core / Ext ---
            $this->self_like_add_string($meta, 'headline', $this->xmp_read_any($desc, 'Iptc4xmpCore', 'Title'));
            $this->self_like_add_string($meta, 'description', $this->xmp_read_any($desc, 'Iptc4xmpCore', 'Description'));

            $this->self_like_add_string($meta, 'iptc_core_country_code', $this->xmp_read_any($desc, 'Iptc4xmpCore', 'CountryCode'));
            $this->self_like_add_string($meta, 'iptc_core_location', $this->xmp_read_any($desc, 'Iptc4xmpCore', 'Location'));
            $this->self_like_add_string($meta, 'iptc_ext_city', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'City'));
            $this->self_like_add_string($meta, 'iptc_ext_country_code', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'CountryCode'));
            $this->self_like_add_string($meta, 'iptc_ext_country_name', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'CountryName'));
            $this->self_like_add_string($meta, 'iptc_ext_event', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'Event'));
            $this->self_like_add_string($meta, 'iptc_ext_organisation_in_image_code', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'OrganisationInImageCode'));
            $this->self_like_add_string($meta, 'iptc_ext_organisation_in_image_name', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'OrganisationInImageName'));
            $this->self_like_add_string($meta, 'iptc_ext_person_in_image', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'PersonInImage'));
            $this->self_like_add_string($meta, 'iptc_ext_province_state', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'ProvinceState'));
            $this->self_like_add_string($meta, 'iptc_ext_sublocation', $this->xmp_read_any($desc, 'Iptc4xmpExt', 'Sublocation'));

            $gps = $this->xmp_extract_gps($desc);
            if ($gps !== []) {
                $meta['gps'] = \array_merge($meta['gps'] ?? [], $gps);
            }
        }

        $keywords = array_values(array_unique(array_filter(array_map('trim', $keywords), static fn($v) => $v !== '')));
        if ($keywords !== []) {
            $meta['keywords'] = $keywords;
        }

        if (!empty($meta['make']) || !empty($meta['model'])) {
            $meta['camera'] = trim(($meta['make'] ?? '') . ' ' . ($meta['model'] ?? ''));
        }

        if (!empty($meta['datetime_original']) && !isset($meta['created_timestamp'])) {
            $ts = strtotime($meta['datetime_original']);
            if ($ts !== false) {
                $meta['created_timestamp'] = $ts;
            }
        }

        return $meta;
    }

    /**
     * Setzt String nur, wenn Feld noch leer ist und Wert ein nicht-leerer String ist.
     * 
     * @param array<string, mixed> $meta Referenz auf Metadaten-Array, das implizit gefüllt wird
     * @param string $key Schlüssel im Metadaten-Array, der mit $value gefüllt werden soll, falls noch nicht gesetzt
     * @param mixed $value Wert, der verwendet werden soll, wenn $meta[$key] noch nicht gesetzt ist und $value ein nicht-leerer String ist
     */
    private function self_like_add_string(array &$meta, string $key, mixed $value): void
    {
        if (!isset($meta[$key]) || $meta[$key] === '') {
            if ( \is_string($value)) {
                $value = trim($value);
                if ($value !== '') {
                    $meta[$key] = $value;
                }
            }
        }
    }

    /**
     * Setzt Float nur, wenn Feld noch leer ist.
     * @param array<string, mixed> $meta Referenz auf Metadaten-Array, das implizit gefüllt wird
     * @param string $key Schlüssel im Metadaten-Array, der mit $value gefüllt werden soll, falls noch nicht gesetzt
     * @param mixed $value Wert, der verwendet werden soll, wenn $meta[$key] noch nicht gesetzt ist und $value numerisch ist (wird dann als Float konvertiert)
     */
    private function self_like_add_float(array &$meta, string $key, mixed $value): void
    {
        if (!isset($meta[$key]) && is_numeric($value)) {
            $meta[$key] = (float)$value;
        }
    }

    /**
     * Setzt Int nur, wenn Feld noch leer ist.
     * @param array<string, mixed> $meta Referenz auf Metadaten-Array, das implizit gefüllt wird
     * @param string $key Schlüssel im Metadaten-Array, der mit $value gefüllt werden soll, falls noch nicht gesetzt
     * @param mixed $value Wert, der verwendet werden soll, wenn $meta[$key] noch nicht gesetzt ist und $value numerisch ist (wird dann als Int konvertiert)
     */
    private function self_like_add_int(array &$meta, string $key, mixed $value): void
    {
        if (!isset($meta[$key]) && is_numeric($value)) {
            $meta[$key] = (int)$value;
        }
    }

    /**
     * Liest ein einfaches XMP-Property als String.
     */
    private function xmp_prop(\SimpleXMLElement $desc, string $prefix, string $name): ?string
    {
        $ns = $desc->getNamespaces(true)[$prefix] ?? null;
        if ($ns === null) {
            return null;
        }

        $children = $desc->children($ns);
        if (!isset($children->{$name})) {
            return null;
        }

        $value = trim((string)$children->{$name});
        return $value !== '' ? $value : null;
    }

    /**
     * Liest den ersten Eintrag aus rdf:Seq.
     */
    private function xmp_seq_first(\SimpleXMLElement $desc, string $prefix, string $name): ?string
    {
        $ns = $desc->getNamespaces(true)[$prefix] ?? null;
        $rdfNs = $desc->getNamespaces(true)['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

        if ($ns === null) {
            return null;
        }

        $children = $desc->children($ns);
        if (!isset($children->{$name})) {
            return null;
        }

        $node = $children->{$name};
        $rdf = $node->children($rdfNs);

        if (!isset($rdf->Seq)) {
            $value = trim((string)$node);
            return $value !== '' ? $value : null;
        }

        foreach ($rdf->Seq->li as $li) {
            $value = trim((string)$li);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Liest alle Einträge aus rdf:Bag. Behandelt auch den Fall, dass kein Bag vorhanden ist und der Wert direkt im Element steht.
     * @param \SimpleXMLElement $desc Das rdf:Description-Element, in dem gesucht werden soll
     * @param string $prefix Der Namespace-Präfix, z. B. "dc" oder "photoshop"
     * @param string $name Der Name des Elements, z. B. "subject" oder "Owner"
     * @return list<string> Liste der Werte, oder leeres Array, wenn nicht gefunden oder leer
     */
    private function xmp_bag_values(\SimpleXMLElement $desc, string $prefix, string $name): array
    {
        $ns = $desc->getNamespaces(true)[$prefix] ?? null;
        $rdfNs = $desc->getNamespaces(true)['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

        if ($ns === null) {
            return [];
        }

        $children = $desc->children($ns);
        if (!isset($children->{$name})) {
            return [];
        }

        $node = $children->{$name};
        $rdf = $node->children($rdfNs);

        $values = [];

        if (isset($rdf->Bag)) {
            foreach ($rdf->Bag->li as $li) {
                $value = trim((string)$li);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        } else {
            $value = trim((string)$node);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Liest bevorzugt x-default oder den ersten Wert aus rdf:Alt.
     */
    private function xmp_lang_alt_first(\SimpleXMLElement $desc, string $prefix, string $name): ?string
    {
        $ns = $desc->getNamespaces(true)[$prefix] ?? null;
        $rdfNs = $desc->getNamespaces(true)['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $xmlNs = 'http://www.w3.org/XML/1998/namespace';

        if ($ns === null) {
            return null;
        }

        $children = $desc->children($ns);
        if (!isset($children->{$name})) {
            return null;
        }

        $node = $children->{$name};
        $rdf = $node->children($rdfNs);

        if (!isset($rdf->Alt)) {
            $value = trim((string)$node);
            return $value !== '' ? $value : null;
        }

        $fallback = null;

        foreach ($rdf->Alt->li as $li) {
            $attrs = $li->attributes($xmlNs);
            $lang = isset($attrs['lang']) ? strtolower(trim((string)$attrs['lang'])) : '';
            $value = trim((string)$li);

            if ($value === '') {
                continue;
            }

            if ($lang === 'x-default') {
                return $value;
            }

            if ($fallback === null) {
                $fallback = $value;
            }
        }

        return $fallback;
    }

    /**
     * Wandelt EXIF/XMP Brüche wie "28/10" oder "1/125" in Float.
     */
    private function xmp_fraction_or_float(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, '/')) {
            [$num, $den] = array_pad(explode('/', $value, 2), 2, null);
            if (is_numeric($num) && is_numeric($den) && (float)$den !== 0.0) {
                return (float)$num / (float)$den;
            }
        }

        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * Belässt Belichtungszeit sinnvoll formatiert:
     * - "1/125" bleibt "1/125"
     * - "0.008" bleibt "0.008"
     */
    private function xmp_exposure_time(?string $value): float|string|null
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, '/')) {
            [$num, $den] = array_pad(explode('/', $value, 2), 2, null);
            if (is_numeric($num) && is_numeric($den) && (float)$den !== 0.0) {
                if ((float)$num == 1.0 && (float)$den >= 1.0) {
                    return '1/' . (string)(int)round((float)$den);
                }
                return $value;
            }
        }

        return is_numeric($value) ? (float)$value : $value;
    }

    /**
     * Normiert ISO-Datum nach "Y-m-d H:i:s", soweit parsebar.
     */
    private function normalize_xmp_datetime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function xmp_to_int(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return is_numeric($value) ? (int)$value : null;
    }
    
    /**
     * Summary of xmp_first_string_from_candidates
     * @param \SimpleXMLElement $desc
     * @param   list<array{0: string, 1: string}> $candidates Array von Kandidaten, jeder Eintrag ist ein Array mit zwei Elementen: [Namespace-Präfix, Elementname]
     * @return string|null
     */
    private function xmp_first_string_from_candidates(\SimpleXMLElement $desc, array $candidates): ?string
    {
        foreach ($candidates as [$prefix, $name]) {
            $value = $this->xmp_prop($desc, $prefix, $name);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        return null;
    }

    /**
     * Summary of xmp_first_int_from_candidates
     * @param \SimpleXMLElement $desc
     * @param   list<array{0: string, 1: string}> $candidates Array von Kandidaten, jeder Eintrag ist ein Array mit zwei Elementen: [Namespace-Präfix, Elementname]
     * @return int|null
     */
    private function xmp_first_int_from_candidates(\SimpleXMLElement $desc, array $candidates): ?int
    {
        foreach ($candidates as [$prefix, $name]) {
            $value = $this->xmp_prop($desc, $prefix, $name);
            $int = $this->xmp_to_int($value);
            if ($int !== null) {
                return $int;
            }
        }
        return null;
    }

    /**
     * GPS-Koordinaten aus XMP/EXIF. Erwartet häufig Formate wie:
     * - "48,8N"
     * - "48.1371N"
     * - "11,5754E"
     * - "5126.123N" (wird hier bewusst nicht speziell dekodiert)
     * 
     * @return array<string, float> Array mit optionalen Schlüsseln 'lat', 'lon' und 'altitude', je nachdem, was gefunden wurde
     */
    private function xmp_extract_gps(\SimpleXMLElement $desc): array
    {
        $gps = [];

        $latRaw = $this->xmp_prop($desc, 'exif', 'GPSLatitude');
        $lonRaw = $this->xmp_prop($desc, 'exif', 'GPSLongitude');
        $altRaw = $this->xmp_prop($desc, 'exif', 'GPSAltitude');

        $lat = $this->xmp_parse_gps_coordinate($latRaw);
        $lon = $this->xmp_parse_gps_coordinate($lonRaw);
        $alt = $this->xmp_fraction_or_float($altRaw);

        if ($lat !== null) {
            $gps['lat'] = $lat;
        }
        if ($lon !== null) {
            $gps['lon'] = $lon;
        }
        if ($alt !== null) {
            $gps['altitude'] = $alt;
        }

        return $gps;
    }

    /**
     * Parsen einfacher XMP-GPS-Formate.
     */
    private function xmp_parse_gps_coordinate(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '.', $value);

        if (preg_match('/^\s*([+-]?\d+(?:\.\d+)?)\s*([NSEW])?\s*$/i', $value, $m)) {
            $coord = (float)$m[1];
            $ref = strtoupper($m[2] ?? '');

            if ($ref === 'S' || $ref === 'W') {
                $coord *= -1;
            }

            return $coord;
        }

        // Fallback: reine Zahl
        if (is_numeric($value)) {
            return (float)$value;
        }

        return null;
    }

    private function xmp_read_any(\SimpleXMLElement $desc, string $prefix, string $name): ?string
    {
        $namespaces = $desc->getNamespaces(true);
        $ns = $namespaces[$prefix] ?? null;

        if ($ns === null) {
            return null;
        }

        // 1. Als Attribut lesen
        $attrs = $desc->attributes($ns);
        if (isset($attrs[$name])) {
            $value = trim((string)$attrs[$name]);
            if ($value !== '') {
                return $value;
            }
        }

        // 2. Als Child-Element lesen
        $children = $desc->children($ns);
        if (isset($children->{$name})) {
            $value = trim((string)$children->{$name});
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function xmp_bag_first(\SimpleXMLElement $desc, string $prefix, string $name): ?string
    {
        $values = $this->xmp_bag_values($desc, $prefix, $name);
        return $values[0] ?? null;
    }

}