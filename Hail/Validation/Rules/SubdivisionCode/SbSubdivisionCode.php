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
 * Validator for Solomon Islands subdivision code.
 *
 * ISO 3166-1 alpha-2: SB
 *
 * @link http://www.geonames.org/SB/administrative-division-solomon-islands.html
 */
class SbSubdivisionCode extends AbstractSearcher
{
    public $haystack = [
        'CE', // Central
        'CH', // Choiseul
        'CT', // Capital Territory
        'GU', // Guadalcanal
        'IS', // Isabel
        'MK', // Makira
        'ML', // Malaita
        'RB', // Rennell and Bellona
        'TE', // Temotu
        'WE', // Western
    ];

    public $compareIdentical = true;
}
