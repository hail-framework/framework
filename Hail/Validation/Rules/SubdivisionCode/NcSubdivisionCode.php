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
 * Validator for New Caledonia subdivision code.
 *
 * ISO 3166-1 alpha-2: NC
 *
 * @link http://www.geonames.org/NC/administrative-division-new-caledonia.html
 */
class NcSubdivisionCode extends AbstractSearcher
{
    public $haystack = [
        'L', // Iles Loyaute
        'N', // Nord
        'S', // Sud
    ];

    public $compareIdentical = true;
}
