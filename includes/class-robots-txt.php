<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPMazic_Robots_Txt {

    public function __construct() {
        add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
    }

    /**
     * Override virtual robots.txt with saved plugin content.
     *
     * @param string $output Current robots content.
     * @param bool   $public Site visibility.
     * @return string
     */
    public function filter_robots_txt( $output, $public ) {
        $saved = get_option( 'wpmazic_robots_txt', '' );
        if ( '' === trim( (string) $saved ) ) {
            return $output;
        }

        return (string) $saved;
    }
}
