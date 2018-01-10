<?php

namespace Hail\I18n\Gettext\Extractors;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Translation;
use SimpleXMLElement;

/**
 * Class to get gettext strings from xliff format.
 */
class Xliff extends Extractor
{
    /**
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations, array $options = [])
    {
        $xml = new SimpleXMLElement($string, null, false);

        foreach ($xml->file as $file) {
            if (isset($file->notes)) {
                foreach ($file->notes->note as $note) {
                    $translations->setHeader($note['id'], (string) $note);
                }
            }

            foreach ($file->unit as $unit) {
                foreach ($unit->segment as $segment) {
                    $targets = [];

                    foreach ($segment->target as $target) {
                        $targets[] = (string) $target;
                    }

                    $translation = new Translation(null, (string) $segment->source);
                    $translation->setTranslation(\array_shift($targets));
                    $translation->setPluralTranslations($targets);

                    if (isset($unit->notes)) {
                        foreach ($unit->notes->note as $note) {
                            switch ($note['category']) {
                                case 'context':
                                    $translation = $translation->getClone((string) $note);
                                    break;

                                case 'extracted-comment':
                                    $translation->addExtractedComment((string) $note);
                                    break;

                                case 'flag':
                                    $translation->addFlag((string) $note);
                                    break;

                                case 'reference':
                                    $ref = \explode(':', (string) $note, 2);
                                    $translation->addReference($ref[0], $ref[1] ?? null);
                                    break;

                                default:
                                    $translation->addComment((string) $note);
                                    break;
                            }
                        }
                    }

                    $translations[] = $translation;
                }
            }
        }
    }
}
