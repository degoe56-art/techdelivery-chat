<?php

if (!defined('ABSPATH')) exit;

class TDAI_Database
{
    const DB_VERSION = '1.1';

    public static function install()
    {
        global $wpdb;

        $table = $wpdb->prefix . TDAI_EMBED_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            content MEDIUMTEXT NOT NULL,
            content_hash CHAR(64) NOT NULL DEFAULT '',
            embedding MEDIUMTEXT NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY content_hash (content_hash)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);

        update_option('tdai_db_version', self::DB_VERSION);
    }

    public static function maybe_upgrade()
    {
        $installed = get_option('tdai_db_version', '1.0');

        if (version_compare($installed, self::DB_VERSION, '<')) {
            self::install();
        }
    }
}