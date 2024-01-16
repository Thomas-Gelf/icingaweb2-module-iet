<?php

namespace Icinga\Module\Iet\Web\Form;

use gipfl\Json\JsonString;
use Icinga\Application\Config as WebConfig;
use Icinga\Module\Icingadb\Model\Customvar;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service as MonitoringService;
use ipl\Orm\Model;

class FormUtil
{
    /**
     * Helper method, get a default value according an eventually configured pattern
     */
    public static function getDefaultFromConfig($object, $property, $enum = null, $default = null): ?string
    {
        $setting = WebConfig::module('iet')->get('defaults', $property);
        if ($setting !== null) {
            $setting = self::fillPlaceholders($setting, $object);
            if ($enum !== null) {
                if ($idx = \array_search($setting, $enum)) {
                    $setting = $idx;
                } elseif (! \array_key_exists($setting, $enum)) {
                    $setting = null;
                }
            }
        }

        if ($setting === null) {
            return $default;
        }

        return $setting;
    }

    protected static function getObjectProperty(object $object, string $property)
    {
        if ($object instanceof MonitoredObject) {
            if (preg_match('/^(host|service)\.vars\.([^.]+)$/', $property, $pMatch)) {
                return $object->{'_' . $pMatch[1] . '_' . $pMatch[2]};
            } elseif (preg_match('/^vars\.([^.]+)$/', $property, $pMatch)) {
                if ($object instanceof MonitoringService && $object->{'_service_' . $pMatch[2]} !== null) {
                    return $object->{'_service_' . $pMatch[2]};
                }

                return $object->{'_host_' . $pMatch[2]};
            }
        } elseif ($object instanceof Model) {
            if (preg_match('/^(host|service)\.vars\.([^.]+)$/', $property, $pMatch)) {
                if ($pMatch[1] === 'host' && $object instanceof Service) {
                    $object = $object->host;
                }

                return static::getIcingaDbCustomVar($object, $pMatch[2]);
            } elseif (preg_match('/^vars\.([^.]+)$/', $property, $pMatch)) {
                if ($object instanceof Service) {
                    if ($value = self::getIcingaDbCustomVar($object, $pMatch[1])) {
                        return $value;
                    }

                    $object = $object->host;
                }

                return static::getIcingaDbCustomVar($object, $pMatch[1]);
            }
        }

        return $object->$property ?? null;
    }

    protected static function getIcingaDbCustomVar(Model $object, $varname)
    {
        // WTF?!
        $vars = $object->customvar->execute();
        /** @var Customvar $var */
        foreach ($vars as $var) {
            if ($var->name === $varname) {
                $decoded = JsonString::decodeOptional($var->value);
                if (is_string($decoded) || is_int($decoded) || is_float($decoded)) {
                    return $decoded;
                } else {
                    return $var->value; // TODO: flat / nested?
                }
            }
        }

        return null;
    }

    /**
     * @param $string
     * @param MonitoredObject|Model|object $object
     * @param callable|null $callback
     * @return string|null
     */
    protected static function fillPlaceholders($string, $object, callable $callback = null): ?string
    {
        $replace = function ($match) use ($object) {
            $propertyString = \trim($match[1], '{}');
            $parts = explode('|', $propertyString);

            while (! empty($parts)) {
                $property = array_shift($parts);
                if (substr($property, 0, 1) === '"' && substr($property, -1, 1) === '"' && strlen($property) > 1) {
                    return substr($property, 1, -1);
                }

                list($property, $modifier) = static::extractPropertyModifier($property);
                $value = static::getObjectProperty($object, $property);
                if ($value === null) {
                    continue;
                }

                static::applyPropertyModifier($value, $modifier);

                return $value;
            }

            return null;
        };

        if ($callback !== null) {
            $_replace = $replace;
            $replace = function ($match) use ($callback, $_replace) {
                $value = $_replace($match);

                return $callback($value);
            };
        }

        return \preg_replace_callback('/({[^}]+})/', $replace, $string);
    }

    protected static function applyPropertyModifier(&$value, $modifier)
    {
        // Hint: $modifier could be null
        switch ($modifier) {
            case 'lower':
                $value = \strtolower($value);
                break;
            case 'stripTags':
                $value = \strip_tags($value);
                break;
        }
    }

    protected static function extractPropertyModifier($property): array
    {
        $modifier = null;
        // TODO: make property modifiers dynamic
        if (\preg_match('/:lower$/', $property)) {
            $property = \preg_replace('/:lower$/', '', $property);
            $modifier = 'lower';
        }
        if (\preg_match('/:stripTags$/', $property)) {
            $property = \preg_replace('/:stripTags$/', '', $property);
            $modifier = 'stripTags';
        }

        return [$property, $modifier];
    }
}
