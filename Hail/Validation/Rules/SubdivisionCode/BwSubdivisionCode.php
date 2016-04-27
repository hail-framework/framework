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
 * Validator for Botswana subdivision code.
 *
 * ISO 3166-1 alpha-2: BW
 *
 * @link http://www.geonames.org/BW/administrative-division-botswana.html
 */
class BwSubdivisionCode extends AbstractSearcher
{
    public $haystack = [
        'CE', // Central
        'GH', // Ghanzi
        'KG', // Kgalagadi
        'KL', // Kgatleng
        'KW', // Kweneng
        'NE', // North East
        'NW', // North West
        'SE', // South East
        'SO', // Southern
    ];

    public $compareIdentical = true;
}
