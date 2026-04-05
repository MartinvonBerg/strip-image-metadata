<?php
namespace  mvbplugins\stripmetadata;

/**
 * Concatenate multidimensional-array-to-string with glue separator.
 *
 * @source https://stackoverflow.com/questions/12309047/multidimensional-array-to-string multidimensional-array-to-string
 * @param  string $glue the separator for the string concetantion of array contents.
 * @param  string|array<int, string> $arr input array
 * @return string|mixed return string on success or the input if it is not a string
 */
function implode_all( string $glue, string|array $arr ) {
	if( \is_array( $arr ) ){

		foreach( $arr as $key => &$value ){
  
			if( \is_array( $value ) ){
				$arr[ $key ] = implode_all( $glue, $value );
			}
		}
  
		return implode( $glue, $arr );
	}

	// Not array
	return $arr;
}
