<?php

namespace Icinga\Module\Iet\Api;

use Icinga\Data\ConfigObject;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Iet\Config;

class SslContext
{
    protected $verifyPeer = true;
    protected $sslCert;
    protected $sslKey;

    public function setVerifyPeer(bool $verifyPeer = true): void
    {
        $this->verifyPeer = $verifyPeer;
    }

    public function setSslCert(string $cert, string $key): void
    {
        $this->sslCert = $cert;
        $this->sslKey = $key;
    }

    /**
     * @return resource
     */
    public function createStreamContext()
    {
        return stream_context_create($this->getStreamContextProperties());
    }

    public function applyCurlOptions($curl)
    {
        assert(is_resource($curl));
        if ($this->verifyPeer) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        } else {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        }
        if ($this->sslKey) {
            curl_setopt($curl, CURLOPT_SSLCERT, $this->sslCert);
            curl_setopt($curl, CURLOPT_SSLKEY, $this->sslKey);
        }
    }

    public function getStreamContextProperties(): array
    {
        $params = ['ssl' => []];
        if (! $this->verifyPeer) {
            $params['ssl']['verify_peer'] = false;
            $params['ssl']['verify_peer_name'] = false;
        }

        if ($this->sslKey) {
            $params['ssl']['local_cert'] = $this->sslCert;
            $params['ssl']['local_pk'] = $this->sslKey;
        }

        return $params;
    }

    /**
     * @throws ConfigurationError
     */
    public static function fromConfig(ConfigObject $config): SslContext
    {
        $context = new SslContext();
        if (Config::makeBoolean($config->get('ignore_certificate', false))) {
            $context->setVerifyPeer(false);
        }
        if ($config->get('cert')) {
            $context->setSslCert($config->get('cert'), $config->get('key'));
        }

        return $context;
    }
}
