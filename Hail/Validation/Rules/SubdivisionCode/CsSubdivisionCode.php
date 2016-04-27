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
 * Validator for Serbia And Montenegro subdivision code.
 *
 * ISO 3166-1 alpha-2: CS
 *
 * @link http://www.geonames.org/CS/administrative-division-serbia-and-montenegro.html
 */
class CsSubdivisionCode extends AbstractSearcher
{
    public $haystack = [
        'KOS', // Kosovo
        'MON', // Montenegro
        'SER', // Serbia
        'VOJ', // Vojvodina
    ];

    public $compareIdentical = true;
}
