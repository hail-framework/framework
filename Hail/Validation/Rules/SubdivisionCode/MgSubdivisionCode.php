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
 * Validator for Madagascar subdivision code.
 *
 * ISO 3166-1 alpha-2: MG
 *
 * @link http://www.geonames.org/MG/administrative-division-madagascar.html
 */
class MgSubdivisionCode extends AbstractSearcher
{
    public $haystack = [
        'A', // Toamasina province
        'D', // Antsiranana province
        'F', // Fianarantsoa province
        'M', // Mahajanga province
        'T', // Antananarivo province
        'U', // Toliara province
    ];

    public $compareIdentical = true;
}
