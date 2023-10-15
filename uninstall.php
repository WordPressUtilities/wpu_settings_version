<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/* Delete options */
$options = array(
    'wpusettingsversion_version',
);
foreach ($options as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}
