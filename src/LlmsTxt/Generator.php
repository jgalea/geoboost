<?php
namespace GeoBoost\LlmsTxt;

final class Generator {

    /**
     * @param array{site_title:string,tagline:string,description:string,home_url:string} $site
     * @param array<int,array{title:string,url:string,excerpt:string}>                    $posts
     */
    public function build( array $site, array $posts ): string {
        $lines = [];

        $lines[] = '# ' . $site['site_title'];
        $lines[] = '';

        if ( ! empty( $site['tagline'] ) ) {
            $lines[] = '> ' . $site['tagline'];
            $lines[] = '';
        }

        if ( ! empty( $site['description'] ) ) {
            $lines[] = $site['description'];
            $lines[] = '';
        }

        if ( ! empty( $posts ) ) {
            $lines[] = '## Posts';
            $lines[] = '';
            foreach ( $posts as $post ) {
                $excerpt = $this->flatten_whitespace( $post['excerpt'] );
                $entry   = sprintf( '- [%s](%s)', $post['title'], $post['url'] );
                if ( '' !== $excerpt ) {
                    $entry .= ': ' . $excerpt;
                }
                $lines[] = $entry;
            }
            $lines[] = '';
        }

        return implode( "\n", $lines );
    }

    private function flatten_whitespace( string $text ): string {
        return trim( preg_replace( '/\s+/', ' ', $text ) );
    }
}
