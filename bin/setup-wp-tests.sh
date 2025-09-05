#!/usr/bin/env bash
set -e
T=${TMPDIR:-/tmp};WPT="$T/wordpress-tests-lib";WPC="$T/wordpress"
get(){ for i in 1 2 3; do if command -v curl >/dev/null 2>&1; then curl -Lfs "$1" -o "$2" && return; elif command -v wget >/dev/null 2>&1; then wget -q "$1" -O "$2" && return; fi; sleep 2; done; }
[ -d "$WPT" ]||{ mkdir -p "$WPT";cat>"$WPT/functions.php"<<'P'
<?php function tests_add_filter($h,$c,$p=10,$a=1){if(function_exists('add_action'))return add_action($h,$c,$p,$a);global$_wp_test_filters;$_wp_test_filters[]=[$h,$c,$p,$a];}
P
cat>"$WPT/bootstrap.php"<<'P'
<?php require_once __DIR__.'/functions.php';global$_wp_test_filters;foreach($_wp_test_filters??[] as$f){add_action($f[0],$f[1],$f[2],$f[3]);}
P
}
[ -d "$WPC" ]||{ mkdir -p "$WPC";if get https://wordpress.org/latest.tar.gz /tmp/wp.tgz;then tar -zxf /tmp/wp.tgz --strip-components=1 -C "$WPC";rm /tmp/wp.tgz;else mkdir -p "$WPC/wp-includes" "$WPC/wp-content";echo "<?php define('WPINC','wp-includes');" > "$WPC/wp-settings.php";fi;}
cat>wp-tests-config.php<<P
<?php
define('ABSPATH','$WPC/');
define('DB_NAME','wordpress_test');
define('DB_USER','root');
define('DB_PASSWORD','');
define('DB_HOST','localhost');
define('WP_TESTS_DOMAIN','example.org');
define('WP_TESTS_EMAIL','admin@example.org');
define('WP_TESTS_TITLE','Test Blog');
define('WP_DEBUG',true);
P
export WP_TESTS_DIR="$WPT" WP_CORE_DIR="$WPC"
[ -f "$WPC/wp-settings.php" ]&&echo "WP core ready at $WPC"||echo "Minimal WP core created"
[ -f "$WPT/bootstrap.php" ]&&echo "WP tests ready at $WPT"||{ echo "Failed to prepare WP tests";exit 1;}
