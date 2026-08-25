<?php

namespace mvbplugins\Extractors;

const EXIF_OFFSET = 8;

final class BinaryExifExtractor {

    /**
     * Extract the EXIF Metadata from a binary string a return as array.
     *
     * @param  string $buffer binary string buffer. The data with EXIF data.
     * @return array<string, mixed>|false the extracted metadata as associative array or false if no EXIF data found
     */
    public function get_exif_meta( string $buffer ): array|false
    {

        $meta = [];

        $tags = array( 
            '0x010F' => array(
                'text' => 'make',
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            '0x0110' => array(
                'text' => 'camera', // model in EXIF
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            
            '0x0131' => array(
                'text' => 'software',
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ),
            '0x013b' => array(
                'text' => 'artist',
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ),
            
            '0x0112' => array(
                'text' => 'orientation',
                'type' => 3, // unsigned short
                'Byte' => 2, // Bytes per component
                'comps'=> 2, // Number of components per data-field 
                'offs' => 0, // offset for type 2, 5, 10, 12
            ), 
            '0xA434' => array(
                'text' => 'lens', // model in EXIF
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ),
            '0x8825' => array(
                'text' => 'GPS',
                'type' => 4, // unsigned short
                'Byte' => 2, // Bytes per component
                'comps'=> 160, // Number of components per data-field 
                'offs' => 0, // offset for type 2, 5, 10, 12
            ), 
            '0x8827' => array(
                'text' => 'iso',
                'type' => 3, // unsigned short
                'Byte' => 2, // Bytes per component
                'comps'=> 2, // Number of components per data-field 
                'offs' => 0, // offset for type 2, 5, 10, 12
            ), 
            '0x8298' => array(
                'text' => 'copyright',
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            '0x829A' => array(
                'text' => 'exposure_time',
                'type' => 5, // unsigned long rational, means 2 rational numbers
                'Byte' => 8, // Bytes per component: taken from data field
                'comps'=> 2, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            '0x829D' => array(
                'text' => 'aperture', // EXIF: FNumber
                'type' => 5, // unsigned long rational
                'Byte' => 8, // Bytes per component: taken from data field
                'comps'=> 2, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            /*
            '0x9202' => array(
                'text' => 'aperture', // FNumber
                'type' => 5, // unsigned long rational
                'Byte' => 8, // Bytes per component: taken from data field
                'comps'=> 2, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ),
            */ 
            '0x9003' => array(
                'text' => 'created_timestamp', // DateTimeOriginal
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            '0x920A' => array(
                'text' => 'focal_length',
                'type' => 5, // ascii string
                'Byte' => 8, // Bytes per component: taken from data field
                'comps'=> 2, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            '0xA405' => array(
                'text' => 'focal_length_in_35mm',
                'type' => 3, // unsigned short
                'Byte' => 2, // Bytes per component
                'comps'=> 2, // Number of components per data-field 
                'offs' => 0, // offset for type 2, 5, 10, 12
            ),
        
            '0xA431' => array(
                'text' => 'serial',
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            /*
            '0xA433' => array(
                'text' => 'lensmake',
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ), 
            '0xA434' => array(
                'text' => 'lensmodel',
                'type' => 2, // ascii string
                'Byte' => 0, // Bytes per component: taken from data field
                'comps'=> 1, // Number of components per data-field 
                'offs' => -1, // offset for type 2, 5, 10, 12: taken from data field
            ),
            */
        );

        $head = strtoupper( substr( $buffer, 0, 4) );

        if ( 'EXIF' != $head ) { 
            // no EXIF data
            return false; 
        }

        $type = strtoupper( substr( $buffer, 8, 2) );
        $check = strtoupper( bin2hex ( substr( $buffer, 10, 2) ) );

        if ( ('II' == $type) && ('2A00' == $check) ) {
            $isIntel = true; // use for Endianess
        
        } elseif ( 'MM' == $type && ('002A' == $check) ) {
            $isIntel = false; // use for Endianess
            
        } else {
            // intel or Motorola type not detected
            return false;
        }

        $bufflen = strlen( $buffer );
        $bufoffs = EXIF_OFFSET + 4;

        while ( $bufoffs <= $bufflen) {
            $piece = $this->frombuffer( $buffer, $bufoffs, 2, $isIntel );
            
            if ( array_key_exists( $piece, $tags ) ) {
                // found one tag
                $value_of_tag = $this->get_meta_from_piece( $isIntel, $buffer, $bufoffs );
                $meta_key = $tags[ $piece ]['text'];

                if ( 'created_timestamp' == $meta_key) {
                    $meta[ 'DateTimeOriginal' ] = $value_of_tag;
                    $value_of_tag = strtotime ( $value_of_tag);
                }
                
                if ( $value_of_tag )
                    $meta[ $meta_key ] = $value_of_tag;
            }
            $bufoffs += 1;
            if ( sizeof ( $meta ) === \sizeof( $tags) ) { break; }
        }
        return $meta;
    }

    /**
     * Extract metadata from a binary string with metadata and return with dedicated type. Use a byte offset to do so. 
     *
     * @param  boolean $isIntel is the buffer input a intel 'II' representation. Actually the defines the Endianess.
     * @param  string  $buffer the buffer with metadata that will be used for extraction
     * @param  integer $bufoffs the offset where to start the extraction in $buffer
     *
     * @return mixed the extracted metadata in different types
     */
    private function get_meta_from_piece( bool $isIntel, string $buffer, int $bufoffs ) 
    { // @codeCoverageIgnore
        $type = substr( $buffer, $bufoffs +2, 2);
        $ncomps = substr( $buffer, $bufoffs +4, 4);
        $data = substr( $buffer, $bufoffs +8, 4);

        if ( $isIntel ) { // revert byte order first
            $type = $this->binrevert( $type );
            $ncomps = $this->binrevert( $ncomps );
            $data = $this->binrevert( $data );
        } else { // extract data from pieces
            $type = '0x' . strtoupper( bin2hex ( $type) );
            $ncomps = '0x' . strtoupper( bin2hex ( $ncomps ) );
            $data = '0x' . strtoupper( bin2hex ( $data ) );
        }

        if ( '0x0002' == $type ) { // this is a ascii string with one component
            $ascii =  substr( $buffer, EXIF_OFFSET + (int) hexdec($data), (int) hexdec($ncomps) -1 );
            return $ascii;
        } elseif ( '0x0003' == $type ) { // this is a integer with 2 components
            if ( ! $isIntel) {
                $data = substr( $data, 0, 6);
            }
            $data = \hexdec( $data);
            return $data;
        } elseif ( '0x0004' == $type ) { // this is a 
            $ascii =  substr( $buffer, EXIF_OFFSET + (int) hexdec($data), 160 );
            $gps = $this->get_gps_data( $ascii, $buffer, $isIntel);
            return $gps;
        } elseif ( '0x0005' == $type ) { // this is a 
            $value_of_tag = $this->getrationale( $buffer, $data, 0, $isIntel);
            return $value_of_tag;
        } else { 
            return false; 
        }
    }

    /**
     * Extract GPS-Data from the EXIF-Header.
     *
     * @param string  $gpsbuffer Binary string buffer beginning with the GPS IFD.
     * @param string  $buffer    Complete EXIF header as binary string.
     * @param boolean $isIntel   True for little endian ("II"), false for big endian ("MM").
     *
     * @return array<string, mixed>|false GPS data as associative array
     *                                    or false if no valid GPS IFD was found.
     */
    private function get_gps_data( string $gpsbuffer, string $buffer, bool $isIntel )
    {
        $meta = [];

        /*
        * GPS tags supported by this extractor.
        *
        * Important:
        * Every TIFF/EXIF IFD entry is always exactly 12 bytes:
        *
        *   2 bytes  Tag
        *   2 bytes  Type
        *   4 bytes  Count
        *   4 bytes  Value or offset
        */
        $tags = [
            '0x0000' => [
                'text' => 'GPSVersionID',
                'type' => 1, // BYTE
            ],
            '0x0001' => [
                'text' => 'GPSLatitudeRef',
                'type' => 2, // ASCII
            ],
            '0x0002' => [
                'text' => 'GPSLatitude',
                'type' => 5, // RATIONAL
            ],
            '0x0003' => [
                'text' => 'GPSLongitudeRef',
                'type' => 2, // ASCII
            ],
            '0x0004' => [
                'text' => 'GPSLongitude',
                'type' => 5, // RATIONAL
            ],
            '0x0005' => [
                'text' => 'GPSAltitudeRef',
                'type' => 1, // BYTE
            ],
            '0x0006' => [
                'text' => 'GPSAltitude',
                'type' => 5, // RATIONAL
            ],
        ];

        $bufflen = \strlen( $gpsbuffer );

        /* The GPS IFD starts with a 2 byte entry count. Plausibility check. */
        if ( $bufflen < 2 ) { return false; }

        $nGpsTags = (int) \hexdec( $this->frombuffer( $gpsbuffer, 0, 2, $isIntel ) );
        if ( $nGpsTags < 1 || $nGpsTags > 31 ) { return false; }

        /*
        * We need at least:
        *  2 bytes entry count + n * 12 bytes IFD entries
        *  The following 4 byte "next IFD" pointer is not required for extracting the GPS tags themselves.
        */
        $requiredLength = 2 + ( $nGpsTags * 12 );

        if ( $bufflen < $requiredLength ) { return false; }

        // Process exactly the number of entries specified by the GPS IFD.
        for ( $i = 0; $i < $nGpsTags; $i++ ) {

            $entryOffset = 2 + ( $i * 12 );

            /*
            * Structure of one IFD entry:
            *
            * +0  Tag          2 bytes
            * +2  Type         2 bytes
            * +4  Count        4 bytes
            * +8  Value/Offset 4 bytes
            */
            $piece = $this->frombuffer(
                $gpsbuffer,
                $entryOffset,
                2,
                $isIntel
            );

            /*
            * Unknown GPS tags are completely valid.
            *
            * Simply skip them. The next entry is still found at
            * entryOffset + 12.
            */
            if ( ! \array_key_exists( $piece, $tags ) ) {
                continue;
            }

            $type = (int) \hexdec(
                $this->frombuffer(
                    $gpsbuffer,
                    $entryOffset + 2,
                    2,
                    $isIntel
                )
            );

            $expectedType = $tags[ $piece ]['type'];

            /*
            * Ignore an entry with an unexpected TIFF type.
            *
            * Most importantly: this does not affect the offset of
            * the next IFD entry.
            */
            if ( $type !== $expectedType ) {
                continue;
            }

            $count = (int) \hexdec(
                $this->frombuffer(
                    $gpsbuffer,
                    $entryOffset + 4,
                    4,
                    $isIntel
                )
            );

            if ( $count < 1 ) {
                continue;
            }

            $valueOffset = $entryOffset + 8;

            /*
            * TYPE 1: BYTE
            *
            * Used here for:
            *   GPSVersionID
            *   GPSAltitudeRef
            *
            * If the complete value occupies <= 4 bytes, TIFF stores it
            * directly in the 4 byte Value field.
            *
            * Both GPSVersionID and GPSAltitudeRef fall into this category.
            */
            if ( 1 === $type ) {

                if ( $count > 4 ) {
                    continue;
                }

                $raw = \substr(
                    $gpsbuffer,
                    $valueOffset,
                    $count
                );

                if ( \strlen( $raw ) !== $count ) {
                    continue;
                }

                $data = [];

                /*
                * BYTE values themselves are not endian dependent.
                * Therefore they must not be passed through binrevert().
                */
                for ( $j = 0; $j < $count; $j++ ) {
                    $data[] = '0x' . \strtoupper(
                        \bin2hex( $raw[ $j ] )
                    );
                }

                $meta_key = $tags[ $piece ]['text'];
                $meta[ $meta_key ] = $data;

                continue;
            }

            /*
            * TYPE 2: ASCII
            *
            * Used here only for:
            *   GPSLatitudeRef
            *   GPSLongitudeRef
            *
            * Normally Count = 2:
            *
            *   "N\0"
            *   "S\0"
            *   "E\0"
            *   "W\0"
            *
            * This fits directly into the 4 byte Value field.
            */
            if ( 2 === $type ) {

                if ( $count > 4 ) {
                    continue;
                }

                $raw = \substr(
                    $gpsbuffer,
                    $valueOffset,
                    $count
                );

                if ( \strlen( $raw ) !== $count ) {
                    continue;
                }

                $data = \strtoupper(
                    \rtrim( $raw, "\0" )
                );

                /*
                * Only these values make sense for the two reference tags
                * supported by this extractor.
                */
                if ( ! \in_array( $data, [ 'N', 'S', 'E', 'W' ], true ) ) {
                    continue;
                }

                $meta_key = $tags[ $piece ]['text'];
                $meta[ $meta_key ] = $data;

                continue;
            }

            /*
            * TYPE 5: RATIONAL
            *
            * A RATIONAL consists of:
            *
            *   4 bytes numerator
            *   4 bytes denominator
            *
            * Therefore even one RATIONAL requires 8 bytes and cannot fit
            * into the 4 byte IFD Value field.
            *
            * The Value field therefore contains a pointer relative to
            * the TIFF header.
            */
            if ( 5 === $type ) {

                $pointer = $this->frombuffer(
                    $gpsbuffer,
                    $valueOffset,
                    4,
                    $isIntel
                );

                $pointerValue = (int) \hexdec( $pointer );

                /*
                * getrationale() uses the same EXIF_OFFSET convention:
                *
                *     EXIF_OFFSET + pointer
                *
                * Check the referenced area here before calling it.
                */
                $dataStart = EXIF_OFFSET + $pointerValue;

                if ( $dataStart < 0 || $dataStart > \strlen( $buffer ) ) {
                    continue;
                }

                /*
                * Check whether all requested RATIONAL values are actually
                * contained in the complete EXIF buffer.
                */
                $bytesAvailable = \strlen( $buffer ) - $dataStart;
                $valuesAvailable = \intdiv( $bytesAvailable, 8 );

                if ( $count > $valuesAvailable ) {
                    continue;
                }

                $rational = [];

                for ( $j = 0; $j < $count; $j++ ) {
                    $rational[] = $this->getrationale(
                        $buffer,
                        $pointer,
                        $j,
                        $isIntel,
                        'gps'
                    );
                }

                $meta_key = $tags[ $piece ]['text'];
                $meta[ $meta_key ] = $rational;
            }
        }

        return $meta;
    }

    /**
     * Convert a string buffer to its binary representation depending on given parameters. 
     * For an alphanumeric string the output is its character code, which is reverted if it isIntel=true.
     * Example 'AB' -> 0x4142 or 0x4241
     *
     * @param  string  $buffer input that should be converted to a binary.  
     * @param  integer $offset where to start the conversion within the buffer
     * @param  integer $length length of the string that sould be converted 
     * @param  boolean $isIntel is the buffer input a intel 'II' representation. Actually the defines the Endianess.
     * @return string the piece of the data as hex-string
     */
    private function frombuffer(string $buffer, int $offset, int $length, bool $isIntel) :string
    { // @codeCoverageIgnore
        if ( (strlen( $buffer) < ( $offset + $length )) || ($length == 0) ) return '0x00';

        $binary = substr( $buffer, $offset, $length);

        if ( $isIntel ) {
            $piece = $this->binrevert( $binary );
        } else {
            $piece = '0x' . strtoupper( bin2hex ( $binary ) );
        }

        return $piece;
    }

    /**
     * get the rational value out of the string buffer
     *
     * @param string $buffer the data buffer which contains the values
     * @param string $pointer the relative pointer as hex value like 'AF'. For Exif the offset is marked by 'MM' or 'II'.
     * @param integer $count the n'th value to search for, '0' means 1st value
     * @param boolean $isIntel whether the byte field is to revert
     * @return string|float $value_of_tag the calculated rational value = nominator / denominator or as string.
     */
    private function getrationale (string $buffer, string $pointer, int $count, bool $isIntel, string $type = 'number')
    { // @codeCoverageIgnore
        $value_of_tag = 0.0;
        $explength = EXIF_OFFSET + hexdec($pointer) + 8 + $count*8;

        if ( strlen( $buffer ) < $explength ) return $value_of_tag;

        $numerator =   substr( $buffer, EXIF_OFFSET + (int) hexdec($pointer)     + $count*8 , 4 ); // Zähler
        $denominator = substr( $buffer, EXIF_OFFSET + (int) hexdec($pointer) + 4 + $count*8 , 4 ); // Nenner
        
        if ( $isIntel ) {
            // revert byte order first
            $numerator   = $this->binrevert( $numerator );
            $denominator = $this->binrevert( $denominator );
            $numerator   =    hexdec( $numerator ); // Zähler
            $denominator =    hexdec( $denominator ); // Nenner
        } else {
            $numerator =   hexdec( '0x' . bin2hex( $numerator   ) ); // Zähler
            $denominator = hexdec( '0x' . bin2hex( $denominator ) ); // Nenner
        }

        if ( 'number' == $type ) {
            $value_of_tag = $numerator / $denominator;
        } elseif ( 'gps' == $type ) {
            $value_of_tag = strval( $numerator ) . '/' . strval( $denominator );
        }

        return $value_of_tag;
    }

    /**
     * Revert a binary string to a reverted hex-string. The output of this private function is inconsistent!
     * For length=(2 / 4) the function provides the reverted character codes. Example 'AZ' -> 0x5A41. 
     * But for length = 1 the function provides the digit to hex conversion. So '1' -> 0x01. And for anything else than [0-9] it responds with 0x00.
     *
     * @param string $binary binary-data as string taken from the binary buffer with EXIF-data
     * @return string the inverted binary data as hex-string
     */
    private function binrevert (string $binary) :string
    { // @codeCoverageIgnore
        switch ( \strlen( $binary) ) {
            case 1:
                $val = dechex( \intval( $binary ) ) ;
                $bin = '0x' . \strtoupper( sprintf('%02s', $val ) );
                return $bin;
                
            case 2:
                $val = dechex( unpack( 'v', $binary )[1] ?? null);
                $bin = '0x' . \strtoupper( sprintf('%04s', $val ) );
                return $bin;
                
            case 4:
                $val = dechex( unpack( 'V', $binary )[1] ?? null);
                $bin = '0x' . \strtoupper( sprintf('%08s', $val ) );
                return $bin;
                
            default:
                return '0x00';
                
        }
    }
}