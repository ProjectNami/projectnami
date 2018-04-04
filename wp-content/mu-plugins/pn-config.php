<?php

/**
 * A place to store any miscellaneous config needed for Core or external plugin issues.
 */

/*
 * Stop Jetpack from writing to options with direct DB calls.
 */
if ( ! defined( 'JETPACK_DISABLE_RAW_OPTIONS' ) ) {
	define( 'JETPACK_DISABLE_RAW_OPTIONS', true );
}
