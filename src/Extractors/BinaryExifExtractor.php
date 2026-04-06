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
     * Extract GPS-Data from the EXIF-Header
     *
     * @param  string  $gpsbuffer the binary string buffer with gpsdata taken from the EXIF-header
     * @param  string  $buffer the complete EXIF-header as binary string
     * @param  boolean $isIntel is the buffer input a intel 'II' representation. Actually this defines the Endianess.
     * @return array<string, mixed>|false the GPS-Data as associative array or false if no GPS-Data found
     */
    private function get_gps_data( string $gpsbuffer, string $buffer, bool $isIntel ) 
    {
        $meta = [];

        // define the gps-tags to search for
        $tags = array( 
            '0x0000' => array(
                'text' => 'GPSVersionID',
                'type' => 1, // n int8 values, number n is taken from $count, usually 4
                'nBytes' => 1, // Bytes per component: taken from data field
            ), 
            '0x0001' => array(
                'text' => 'GPSLatitudeRef',
                'type' => 2, // ascii string
                'nBytes' => 2, // Bytes per string value, so two asciis each 2 Bytes long
            ), 
            '0x0002' => array(
                'text' => 'GPSLatitude',
                'type' => 5, // rational uint64, number n is taken from $count, usually 3
                'nBytes' => 4, // relative address pointer to the data, 4 Bytes long
            ), 
            '0x0003' => array(
                'text' => 'GPSLongitudeRef',
                'type' => 2, // ascii string, 
                'nBytes' => 2, // Bytes per string value, so two asciis each 2 Bytes long
            ), 
            '0x0004' => array(
                'text' => 'GPSLongitude',
                'type' => 5,  // rational uint64, number n is taken from $count, usually 3
                'nBytes' => 4, // relative address pointer to the data, 4 Bytes long
            ), 
            '0x0005' => array(
                'text' => 'GPSAltitudeRef',
                'type' => 1, // n int8 values, number n is taken from $count, usually 4
                'nBytes' => 1, // Bytes per component: taken from data field
            ), 
            '0x0006' => array(
                'text' => 'GPSAltitude',
                'type' => 5, // rational uint64, number n is taken from $count, usually 3
                'nBytes' => 4, // relative address pointer to the data, 4 Bytes long
            ), 
        );
        // get the total number of tags
        $nGpsTags = hexdec( $this->frombuffer( $gpsbuffer, 0, 2, $isIntel) );
        
        if ( ( $nGpsTags < 1 ) || ( $nGpsTags > 31) ) { 
            // no GPS data or wrong buffer selected
            return false; 
        }

        $bufflen = \strlen( $gpsbuffer );
        $bufoffs = 2;

        while ( $bufoffs <= $bufflen) {
            $piece = $this->frombuffer( $gpsbuffer, $bufoffs, 2, $isIntel) ;
            $bufoffs += 2;

            if ( \array_key_exists( $piece, $tags ) ) {
                // init data array 
                $data = [];

                // get the type of the tag first
                $type = hexdec( $this->frombuffer( $gpsbuffer, $bufoffs, 2, $isIntel) );
                $expectedType = $tags[ $piece ]['type']; 
                $bufoffs += 2;

                // do only if the type is correct
                if ( $type === $expectedType){
                    // get the number of values
                    $count = hexdec( $this->frombuffer( $gpsbuffer, $bufoffs, 4, $isIntel) );
                    if ($count > $bufflen) break;
                    $nvalues = $count;
                    $bufoffs += 4;

                    if ( 5 == $type ) { // correct number of values for pointers, it's only one pointer
                        //$nvalues = $count;
                        $count = 1;
                    }

                    // get the data or relative pointer
                    $lendata = $tags[ $piece ]['nBytes'];
                    for ($i=1; $i <= $count ; $i++) { 
                        $data[] = $this->frombuffer( $gpsbuffer, $bufoffs, $lendata, $isIntel);
                        $bufoffs += $lendata;
                    }

                    // special treatment of the Lat/Long-Ref
                    if ( 2 == $type ) {
                        $data = \strtoupper($data[0]);
                        $data = \str_replace('0','', $data);
                        $data = \str_replace('X','', $data);
                        $data = \chr((int) hexdec($data) );
                        $found = strpos( ' NSEW', $data);
                        if ( $found === false ) $data = false;
                    }

                    // special treatment of the Lat- / Long- / Alt-itude
                    if ( 5 == $type ) {
                        $rational = [];
                        for ($i=0; $i < $nvalues ; $i++) { 
                            $rational[] = $this->getrationale( $buffer, $data[0], $i, $isIntel, 'gps'); /** @phpstan-ignore-line */
                        }
                        $data = $rational;
                    }
                    
                    // store the new data in array
                    $value_of_tag = $data; 
                    $meta_key = $tags[ $piece ]['text'];
                    $meta[ $meta_key ] = $value_of_tag;
                }
            }
            
            if ( \sizeof ( $meta ) === $nGpsTags ) { break; }
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