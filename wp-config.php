<?php
define('WP_CACHE', true);

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'l@9ail,Z>XD:&_&E;!.Qh*CxO8uT-X_5X:atpVvY,^&P@E)r:Uk~qeBw^V3#MuEi' );
define( 'SECURE_AUTH_KEY',  'o9S|0^;&r/z$9=Ff8UJDNM>vjZ0}f0Npa]y|RD&9% 1k,I)tuDQKSSO#x.^Eq9`H' );
define( 'LOGGED_IN_KEY',    'bG!*-ys ;=inOwDA?b*YT{:kuJvM}@$95e .0S mxS8T9!sn;p_jHF!6X!Vm5PPf' );
define( 'NONCE_KEY',        'fozVfdJsu)nN/BJW1+ w0M&Fo,J.tO6*:6tQb{@lpvZ$}(u<r)e%p3M0{.V|{P K' );
define( 'AUTH_SALT',        '+&&?N3lLgt[Cl`@5&=)4F4gB|q`Dbyx)*XHM?uEjZv*,3Cs6sw86h3%#qj!078pV' );
define( 'SECURE_AUTH_SALT', '{$eRxGsc#K,392M_gPZld~.YZ$h<;LJ)lB+GGUQui>Kwl#yiIv<KP9c?^wxMwo&7' );
define( 'LOGGED_IN_SALT',   'OqL$oFf`6AhBtsT0>IEc[>xl=6=DYiQ-wq/Gzf[z{]Qk*o`{,FNUxOa_rS#7XhK(' );
define( 'NONCE_SALT',       ')HWUR`@ >? <g?eyRLB6J;_57*xH^,SyB|)3Gdq/Z4ls*`&) VtG-a1H[_}.WC A' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
