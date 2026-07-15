<?php
namespace  mvbplugins\stripmetadata;

/**
 * Concatenate a (possibly multidimensional) array to a string using a glue separator.
 *
 * Accepts a string (returned cast) or an array whose values (recursively) are
 * scalars or objects implementing \Stringable; all values are cast to string.
 *
 * @param string $glue Separator for concatenation.
 * @param string|array<int|string, int|float|string|bool|\Stringable|array> $arr Input string or (possibly nested) array of values convertible to string.
 * @return string The concatenated string, or the input cast to string if not an array.
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
