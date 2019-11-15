<?php

namespace Icinga\Module\Iet;

use Icinga\Application\Config as IcingaConfig;
use Icinga\Exception\ConfigurationError;

class Config
{
    /** @var IcingaConfig */
    protected static $instances;

    /**
     * @param bool $required
     * @return array
     * @throws ConfigurationError
     */
    public static function enumInstances($required = true)
    {
        $enum = [];
        /** @var  $config \Icinga\Data\ConfigObject */
        foreach (static::instances() as $title => $config) {
            $enum[$title] = "$title: " . $config->get('host');
        }

        if ($required && empty($enum)) {
            static::failUnconfigured();
        }

        return $enum;
    }

    /**
     * @throws ConfigurationError
     */
    protected static function failUnconfigured()
    {
        $config = '[ICINGAWEB_CONFIGDIR]/modules/iet/instances.ini';
        throw new ConfigurationError(
            "Please configure at least one iET instance in $config"
        );
    }

    /**
     * @param string $name
     * @param string|null $property
     * @throws ConfigurationError
     */
    protected static function failMissing($name, $property = null)
    {
        $config = '[ICINGAWEB_CONFIGDIR]/modules/iet/instances.ini';
        if ($property === null) {
            throw new ConfigurationError(
                "There is no [$name] in  $config"
            );
        } else {
            throw new ConfigurationError(
                "[$name] has no $property in $config"
            );
        }
    }

    /**
     * @param string|null $name
     * @return mixed
     * @throws ConfigurationError
     */
    public static function getWebServiceUrl($name = null)
    {
        return static::getRequiredSetting($name, 'webservice');
    }

    /**
     * @param string $name
     * @param string $property
     * @return mixed
     * @throws ConfigurationError
     */
    public static function getRequiredSetting($name, $property)
    {
        $value = static::getSetting($name, $property);
        if ($value === null) {
            static::failMissing($name, $property);
        }

        return $value;
    }

    /**
     * @param string $name
     * @param string $property
     * @param mixed $default
     * @return mixed
     * @throws ConfigurationError
     */
    public static function getSetting($name, $property, $default = null)
    {
        return static::getInstance($name)->get($property, $default);
    }

    /**
     * @param string|null $name
     * @return mixed
     * @throws ConfigurationError
     */
    public static function getInstance($name = null)
    {
        if ($name === null) {
            $name = static::getDefaultName();
        }

        $config = static::instances();
        if (! $config->hasSection($name)) {
            static::failMissing($name);
        }

        return $config->getSection($name);
    }

    /**
     * @return int|string
     * @throws ConfigurationError
     */
    public static function getDefaultName()
    {
        foreach (static::enumInstances() as $title => $config) {
            return $title;
        }

        static::failUnconfigured();
    }

    /**
     * @param string|null $name
     * @return Api
     * @throws ConfigurationError
     */
    public static function getApi($name = null)
    {
        if ($name === null) {
            $name = static::getDefaultName();
        }

        $config = static::getInstance($name);

        $url = $config->get('webservice');
        if ($url === null) {
            static::failMissing($name, 'webservice');
        }

        $api = new Api(
            $url,
            $config->get('username', 'ietws'),
            $config->get('password', 'ietws')
        );

        if (static::makeBoolean($config->get('ignore_certificate', false))) {
            $api->setVerifyPeer(false);
        }

        if ($ns = $config->get('namespace')) {
            $api->setNamespace($ns);
        }
        if ($config->get('cert')) {
            $api->setSslCert($config->get('cert'), $config->get('key'));
        }

        return $api;
    }

    /**
     * @param mixed $value
     * @return bool
     * @throws ConfigurationError
     */
    protected static function makeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        } elseif (is_string($value)) {
            if (in_array($value, ['y', 'yes', 'true', '1'])) {
                return true;
            } elseif (in_array($value, ['n', 'no', 'false', '0'])) {
                return false;
            }
        }

        throw new ConfigurationError("$value is not a valid boolean");
    }

    /**
     * @return IcingaConfig
     */
    protected static function instances()
    {
        if (static::$instances === null) {
            static::$instances = IcingaConfig::module('iet', 'instances');
        }

        return static::$instances;
    }
}
