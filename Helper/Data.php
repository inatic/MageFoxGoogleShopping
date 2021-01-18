<?php

namespace Magefox\GoogleShopping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    private const GS_CONFIG_PATH = 'magefoxgoogleshopping/settings/';

    public function getConfig($configNode)
    {
        return $this->scopeConfig->getValue(
            self::GS_CONFIG_PATH.$configNode,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
