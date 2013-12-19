<?php
class CmsPluginData
{

    const CMS2CMS_OPTION_TABLE = 'cms2cms_options';

    public function getUserEmail()
    {
        $user_ID = get_current_user_id();
        $user_info = get_userdata($user_ID);

        $email = $user_info->user_email;

        return $email;
    }

    public function getSiteUrl()
    {
        return get_site_url();
    }

    public function getOption($name)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::CMS2CMS_OPTION_TABLE;
        $value = $wpdb->get_var( $wpdb->prepare(
            "
            SELECT option_value
            FROM $table_name
            WHERE option_name = %s
            LIMIT 1
	    ",
            $name
        ));

        return $value;
    }

    public function setOption($name, $value)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::CMS2CMS_OPTION_TABLE;
        $wpdb->insert( $table_name, array( 'option_name' => $name, 'option_value' => $value ) );
    }

    public function deleteOption($name)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::CMS2CMS_OPTION_TABLE;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE option_name = %s",
                $name
            )
        );
    }

    public function getFrontUrl()
    {
        $pluginurl = plugin_dir_url( __FILE__ );
        if ( preg_match( '/^https/', $pluginurl )
            && !preg_match( '/^https/', get_bloginfo('url') )
        ){
            $pluginurl = preg_replace( '/^https/', 'http', $pluginurl );
        }

        return $pluginurl;
    }


    public function getBridgeUrl()
    {
        $cms2cms_bridge_url = str_replace($this->getSiteUrl(), '', $this->getFrontUrl());
        $cms2cms_bridge_url = '/' . trim($cms2cms_bridge_url, DIRECTORY_SEPARATOR);

        return $cms2cms_bridge_url;
    }

    public function getAuthData()
    {
        $cms2cms_access_login = $this->getOption('cms2cms-login');
        $cms2cms_access_key = $this->getOption('cms2cms-key');

        return array(
            'email' => $cms2cms_access_login,
            'accessKey' => $cms2cms_access_key
        );
    }

    public function isActivated()
    {
        $cms2cms_access_key = $this->getOption('cms2cms-key');

        return ($cms2cms_access_key != false);
    }

    public function install()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::CMS2CMS_OPTION_TABLE;
        $sql = sprintf(
            "
                CREATE TABLE IF NOT EXISTS %s (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    option_name VARCHAR(64) DEFAULT '' NOT NULL,
                    option_value VARCHAR(64) DEFAULT '' NOT NULL,
                    UNIQUE KEY id (id)
                )
            ",
            $table_name
        );

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function getOptions()
    {
        $key = $this->getOption('cms2cms-key');
        $login = $this->getOption('cms2cms-login');

        $response = 0;

        if ( $key && $login ) {
            $response = array(
                'email' => $login,
                'accessKey' => $key,
            );
        }

        return $response;
    }

    public function saveOptions()
    {
        $key = substr( $_POST['accessKey'], 0, 64 );
        $login = sanitize_email( $_POST['login'] );

        $cms2cms_site_url = $this->getSiteUrl();
        $bridge_depth = str_replace($cms2cms_site_url, '', $this->getFrontUrl());
        $bridge_depth = trim($bridge_depth, DIRECTORY_SEPARATOR);
        $bridge_depth = explode(DIRECTORY_SEPARATOR, $bridge_depth);
        $bridge_depth = count( $bridge_depth );


        $response = array(
            'errors' => _('Provided credentials are not correct: ' . $key . ' = ' . $login )
        );

        if ( $key && $login ) {
            $this->deleteOption('cms2cms-key');
            $this->setOption('cms2cms-key', $key);

            $this->deleteOption('cms2cms-login');
            $this->setOption('cms2cms-login', $login);

            $this->deleteOption('cms2cms-depth');
            $this->setOption('cms2cms-depth', $bridge_depth);

            $response = array(
                'success' => true
            );
        }

        return $response;
    }

    public function clearOptions()
    {
        $this->deleteOption('cms2cms-login');
        $this->deleteOption('cms2cms-key');
        $this->deleteOption('cms2cms-depth');
    }


}