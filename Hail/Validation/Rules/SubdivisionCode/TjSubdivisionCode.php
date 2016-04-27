<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the "LICENSE.md"
 * file that was distributed with this source code.
 */

namespace Hail\Validation\Rules\SubdivisionCode;

use Hail\Validation\Rules\AbstractSearcher;

/**
 * Validator for Tajikistan subdivision code.
 *
 * ISO 3166-1 alpha-2: TJ
 *
 * @link http://www.geonames.org/TJ/administrative-division-tajikistan.html
 */
class TjSubdivisionCode extends AbstractSearcher
{
    public $haystack = [
        'GB', // Gorno-Badakhstan
        'KT', // Khatlon
        'SU', // Sughd
    ];

    public $compareIdentical = true;
}
