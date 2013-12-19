<?php
class CmsPluginView
{

    public function _e($message, $domain)
    {
        return _e($message, $domain);
    }

    public function __($message, $domain)
    {
        return __($message, $domain);
    }

    public function getFormTempKey($name)
    {
        return wp_create_nonce($name);
    }

    public function verifyFormTempKey($value, $name)
    {
        return wp_verify_nonce($value, $name);
    }

    public function getAppUrl()
    {
        return 'http://app.cms2cms.com'; /* no trailing slash */
    }

    public function getVideoLink()
    {
        return 'http://www.youtube.com/watch?feature=player_detailpage&v=DQK01NbrCdw#t=25s';
    }

    public function getPluginSourceName()
    {
        return $this->__('HTML', 'cms2cms-mirgation');
    }

    public function getPluginSourceType()
    {
        return 'HTML';
    }

    public function getPluginTargetName()
    {
        return $this->__('WordPress', 'cms2cms-mirgation');
    }

    public function getPluginTargetType()
    {
        return 'WordPress';
    }

    public function getPluginNameLong()
    {
        return sprintf(
            $this->__('CMS2CMS: Automated %s to %s Migration ', 'cms2cms-mirgation'),
            $this->getPluginSourceName(),
            $this->getPluginTargetName()
        );
    }

    public function getPluginNameShort()
    {
        return sprintf(
            $this->__('%s to %s', 'cms2cms-mirgation'),
            $this->getPluginSourceName(),
            $this->getPluginTargetName()
        );
    }

    public function getPluginReferrerId()
    {
        return sprintf(
            'Plugin | %s | %s to %s',
            $this->getPluginTargetType(),
            $this->getPluginSourceType(),
            $this->getPluginTargetType()
        );
    }

    public function getRegisterUrl()
    {
        return $this->getAppUrl() . '/auth/register';
    }

    public function getLoginUrl()
    {
        return $this->getAppUrl() . '/auth/login';
    }

    public function getForgotPasswordUrl()
    {
        return $this->getAppUrl() . '/auth/forgot-password';
    }

    public function getVerifyUrl()
    {
        return $this->getAppUrl() . '/wizard/verify';
    }

    public function getWizardUrl()
    {
        return $this->getAppUrl() . '/wizard';
    }

    public function getDownLoadBridgeUrl($cms2cms_authentication)
    {
        return $this->getAppUrl() . '/wizard/get-bridge?callback=plugin&authentication=' . urlencode(json_encode($cms2cms_authentication));
    }

}