<?php
namespace  mvbplugins\stripmetadata;

/**
 * Concatenate multidimensional-array-to-string with glue separator.
 * 
 * @param  string $glue the separator for the string concetantion of array contents.
 * @param  string|array<int, string> $arr input array
 * 
 * @return string return string on success or the input converted to string if it is not an array.
 */
function implode_all(string $glue, string|array $arr): string
{
    if (!\is_array($arr)) {
        return (string) $arr;
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveArrayIterator($arr)
    );

    $flat = [];

    foreach ($iterator as $value) {
        $flat[] = (string) $value;
    }

    return implode($glue, $flat);
}
