<?php

/**
 * Shared money formatting helper. Used across public pages and hx-control.
 */
function money(float $v): string
{
    return '$' . rtrim(rtrim(number_format($v, 2), '0'), '.');
}
