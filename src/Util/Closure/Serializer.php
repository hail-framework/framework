<?php

namespace Hail\Util\Closure;

use Hail\Util\Serialize;

/**
 * This is the serializer class used for serializing Closure objects.
 *
 * We're abstracting away all the details, impossibilities, and scary things
 * that happen within.
 */
class Serializer
{
    /**
     * The special value marking a recursive reference to a closure.
     *
     * @var string
     */
    public const RECURSION = '{{RECURSION}}';

    /**
     * The keys of closure data required for serialization.
     *
     * @var array
     */
    private static $dataToKeep = [
        'code' => true,
        'context' => true,
        'binding' => true,
        'scope' => true,
        'isStatic' => true,
    ];

    /**
     * @var string
     */
    private static $serializeType;

    public static function getSerializeType(): ?string
    {
        return self::$serializeType ?? null;
    }

    /**
     * Takes a Closure object, decorates it with a SerializableClosure object,
     * then performs the serialization.
     *
     * @param \Closure    $closure Closure to serialize.
     * @param string|null $type
     *
     * @return string Serialized closure.
     * @throws \RuntimeException
     */
    public static function serialize(\Closure $closure, string $type = null): string
    {
        self::$serializeType = $type;

        $serialized = Serialize::encode(new SerializableClosure($closure), $type);

        if ($serialized === null) {
            throw new \RuntimeException('The closure could not be serialized.');
        }

        return $serialized;
    }

    /**
     * Takes a serialized closure, performs the unserialization, and then
     * extracts and returns a the Closure object.
     *
     * @param string      $serialized Serialized closure.
     * @param string|null $type
     *
     * @throws \RuntimeException if unserialization fails.
     * @return \Closure Unserialized closure.
     */
    public static function unserialize($serialized, string $type = null): \Closure
    {
        self::$serializeType = $type;
        $unserialized = Serialize::decode($serialized, $type);

        if (!$unserialized instanceof SerializableClosure) {
            throw new \RuntimeException(
                'The closure did not unserialize to a SuperClosure.'
            );
        }

        return $unserialized->getClosure();
    }

    /**
     * Retrieves data about a closure including its code, context, and binding.
     *
     * The data returned is dependant on the `ClosureAnalyzer` implementation
     * used and whether the `$forSerialization` parameter is set to true. If
     * `$forSerialization` is true, then only data relevant to serializing the
     * closure is returned.
     *
     * @param \Closure $closure          Closure to analyze.
     * @param bool     $forSerialization Include only serialization data.
     *
     * @return array
     */
    public static function getData(\Closure $closure, $forSerialization = false): array
    {
        // Use the closure analyzer to get data about the closure.
        $data = static::analyze($closure);

        // If the closure data is getting retrieved solely for the purpose of
        // serializing the closure, then make some modifications to the data.
        if ($forSerialization) {
            // If there is no reference to the binding, don't serialize it.
            if (!$data['hasThis']) {
                $data['binding'] = null;
            }

            // Remove data about the closure that does not get serialized.
            $data = \array_intersect_key($data, self::$dataToKeep);

            // Wrap any other closures within the context.
            foreach ($data['context'] as &$value) {
                if ($value instanceof \Closure) {
                    $value = ($value === $closure)
                        ? self::RECURSION
                        : new SerializableClosure($value);
                }
            }
        }

        return $data;
    }

    /**
     * Recursively traverses and wraps all Closure objects within the value.
     *
     * NOTE: THIS MAY NOT WORK IN ALL USE CASES, SO USE AT YOUR OWN RISK.
     *
     * @param mixed      $data       Any variable that contains closures.
     * @param Serializer $serializer The serializer to use.
     */
    public static function wrapClosures(&$data, Serializer $serializer)
    {
        if ($data instanceof \Closure) {
            // Handle and wrap closure objects.
            $reflection = new \ReflectionFunction($data);
            if ($binding = $reflection->getClosureThis()) {
                self::wrapClosures($binding, $serializer);
                $scope = $reflection->getClosureScopeClass();
                $scope = $scope ? $scope->getName() : 'static';
                $data = $data->bindTo($binding, $scope);
            }
            $data = new SerializableClosure($data);
        } elseif (\is_array($data) || $data instanceof \stdClass || $data instanceof \Traversable) {
            // Handle members of traversable values.
            foreach ($data as &$value) {
                self::wrapClosures($value, $serializer);
            }
        } elseif (\is_object($data) && !$data instanceof \Serializable) {
            // Handle objects that are not already explicitly serializable.
            $reflection = new \ReflectionObject($data);
            if (!$reflection->hasMethod('__sleep')) {
                foreach ($reflection->getProperties() as $property) {
                    if ($property->isPrivate() || $property->isProtected()) {
                        $property->setAccessible(true);
                    }
                    $value = $property->getValue($data);
                    self::wrapClosures($value, $serializer);
                    $property->setValue($data, $value);
                }
            }
        }
    }

    /**
     * Analyzer a given closure.
     *
     * @param \Closure $closure
     *
     * @return array
     */
    private static function analyze(\Closure $closure)
    {
        $reflection = new ReflectionClosure($closure);
        $scope = $reflection->getClosureScopeClass();

        $data = [
            'reflection' => $reflection,
            'code' => $reflection->getCode(),
            'hasThis' => $reflection->isBindingRequired(),
            'context' => $reflection->getUseVariables(),
            'hasRefs' => false,
            'binding' => $reflection->getClosureThis(),
            'scope' => $scope ? $scope->getName() : null,
            'isStatic' => $reflection->isStatic(),
        ];

        return $data;
    }
}
